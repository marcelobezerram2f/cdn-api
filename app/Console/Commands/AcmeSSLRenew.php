<?php

namespace App\Console\Commands;

use App\Models\CdnLetsencryptAcmeRegister;
use App\Models\CdnProvisioningStep;
use App\Models\CdnResource;
use App\Repositories\AcmeLetsEncrypt\AcmeStorageRepository;
use App\Repositories\StepProvisioningRepository;
use App\Services\EventLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AcmeSSLRenew extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ssl:renew';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Renew Certificate SSL';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $logSys = new EventLogService();
        $provisioningStep = new StepProvisioningRepository();
        try {
            $today = date('Y-m-d');
            /**
             * Recupera todos os certificados lets encrypt
             */
            $certificates = CdnLetsencryptAcmeRegister::where('company', 'lets_encrypt')->get();
            $countCerts = 0;
            foreach ($certificates as $certificate) {
                /**
                 * Se o certificado foi gerado fullchain não deve ser nulo
                 */
                if (!is_null($certificate->fullchain)) {

                    /**
                     * verifica se o certificado atende as regras de removação
                     * SSL_RENEWAL_DAYS é a quantidade em dias que o certificado deve ser renovado antes de expirar
                     */
                    $renew = verifyRenew($today, $certificate->certificate_expires, env('SSL_RENEWAL_DAYS'));
                    /**
                     * Se a verificação retornar verdadeira (TRUE) inicia o processo de renovação do certificado
                     */
                    if ($renew) {
                        // Recupera o resource
                        $resource = CdnResource::find($certificate->cdn_resource_id);
                        $acmeStorage = new AcmeStorageRepository();
                        $this->stepAdjustment($resource->id);

                        /**
                         * Verifica se o o DNS continua sem resttrição ao CAA
                         *
                         */

                        $caaCheck = CAACheck($resource->cdn_resource_hostname);
                        if (!$caaCheck) {
                            $provisioningStep->updateStep($resource->id, 6, 'failed', "Invalid CAA entry for Let's Encrypt SSL certificate renewal");
                        } else {
                            $provisioningStep->updateStep($resource->id, 6, 'finished', "Valid CAA entry for Let's Encrypt SSL certificate renewal");

                            /**
                             * Repete a validação da entrada CNAME SSL para renovação do certificado
                             */
                            $checkCnameSSL = CNAMEValidate("_acme-challenge." . $resource->cdn_resource_hostname, $certificate->fulldomain);
                            if (!$checkCnameSSL) {
                                $provisioningStep->updateStep($resource->id, 7, 'failed', 'SSL Cname entry invalid or not configured in dns - certificate renewal');
                            } else {
                                $provisioningStep->updateStep($resource->id, 7, 'finished', 'SSL Cname entry valid for certificate renewal');

                                /**
                                 * invoca o método de captura de certificado o mesmo método de requisição inicial pois o funcionamento é o mesmo
                                 * tanto para certificado novo quanto para renovação
                                 */
                                $startRenew = $acmeStorage->certGeneration($resource);
                                /**
                                 * Se [code] for igual a 200 significa que o certificado foi gerado e persistido na tabela
                                 */
                                if ($startRenew['code'] == 200) {
                                    // (A) - altera a flag published para null
                                    $certificate->published = null;
                                    $certificate->save();
                                    // (B) - altera a flag cname_ssl_verify para null
                                    $resource->cname_ssl_verify = null;
                                    $resource->save();
                                    rmCertFolder(base_path() . "/" . ".acme/" . $resource->cdn_resource_hostname . "_ecc");
                                    /**
                                     * Com (A) e (B) alterados um o cron ssl:install irá dar sequencia na publicação do certificado no cdn-dispatcher
                                     * no proximo mínuto após a esse processo.
                                     *
                                     *
                                     */
                                    $provisioningStep->updateStep($resource->id, 8, 'pending', 'SSL certificate renewed, wait for publication on cdn resource');

                                    Log::info('[CDN-API | ACME-LETSENCRYPT - RENEW] Renovação do Certificado ACME-LETSENCRYPT do domínio ' . $resource->cdn_resource_hostname . ' efetuado com sucesso!');
                                    $logSys->syslog(
                                        '[CDN-API | ACME-LETSENCRYPT - RENEW] Renovação do Certificado ACME-LETSENCRYPT do domínio ' . $resource->cdn_resource_hostname . ' efetuado com sucesso!',
                                        'Message ACME :' . json_encode($startRenew),
                                        'ERROR',
                                        basename(__FILE__) . ':' . basename(__FUNCTION__)
                                    );
                                    $countCerts++;
                                } else {
                                    /**
                                     * Se [code] for diferente a 200 é gerado Log no syslog
                                     */
                                    $provisioningStep->updateStep($resource->id, 8, 'failed', $startRenew['message']);

                                    Log::info('[CDN-API | ACME-LETSENCRYPT - RENEW] Ocorreu um erro na renovação do Certificado ACME-LETSENCRYPT do domínio ' . $resource->cdn_resource_hostname);
                                    $logSys->syslog(
                                        '[CDN-API | ACME-LETSENCRYPT - RENEW] Ocorreu um erro na renovação do Certificado ACME-LETSENCRYPT do domínio ' . $resource->cdn_resource_hostname,
                                        'Message ACME :' . json_encode($startRenew),
                                        'ERROR',
                                        basename(__FILE__) . ':' . basename(__FUNCTION__)
                                    );
                                }
                            }
                        }
                    }
                }
            }
            if ($countCerts == 0) {
                Log::info('[CDN-API | ACME-LETSENCRYPT - RENEW] Não há certificado SSL para atualizar para atualizar em  ' . $today);
                $logSys->syslog(
                    '[CDN-API | ACME-LETSENCRYPT - RENEW] Não há certificado SSL para atualizar em  ' . $today,
                    null,
                    'INFO',
                    basename(__FILE__) . ':' . basename(__FUNCTION__)
                );
            }
        } catch (\Exception $e) {
            Log::info('[CDN-API | ACME-LETSENCRYPT - RENEW] Ocorreu um falha fatal no stript de renovação do Certificado ACME-LETSENCRYPT ->' . $e->getMessage());
            $logSys->syslog(
                '[CDN-API | ACME-LETSENCRYPT - RENEW] Ocorreu um falha fatal no stript de renovação do Certificado ACME-LETSENCRYPT',
                'Erro de Exceção :' . $e->getMessage(),
                'ERROR',
                basename(__FILE__) . ':' . basename(__FUNCTION__)
            );
        }
    }

    public function stepAdjustment($resourceID) {
        $steps = CdnProvisioningStep::where('cdn_resource_id', $resourceID);
        if($steps->count() <= 6 ){
            $provisioningStep = new StepProvisioningRepository();
            $provisioningStep->createSLLSteps($resourceID,'letsencrypt');
        }
    }
}
