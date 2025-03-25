<?php

namespace App\Repositories\AcmeLetsEncrypt;

use App\Services\EventLogService;
use Exception;
use Illuminate\Support\Facades\Log;

class AcmeDnsClientRepository
{
    private $acmedns_url;
    private $logSys;
    protected $facilityLog;
    private $client;
    private $account;
    private $order;
    private $credentials;
    public function __construct()
    {
        $this->acmedns_url = env('ACME_DNS_URL');
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);

    }

    /**
     * Método responsável por registrar uma nova conta no ACME-DNS
     *
     * @param mixed $allowfrom
     *
     * @return array $response
     */

    public function RegisterAccount()
    {
        try {
            $ch = curl_init($this->acmedns_url . "/register");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpcode == 201) {
                $response = json_decode($result, true);
                $response['company'] = "lets_encrypt";
            } else {
                $this->logSys->syslog(
                    '[CDN-API | ACME-LETSENCRYPT] Falha na criação da conta ACME-LETSENCRYPT!',
                    'Endpoint ' . $this->acmedns_url . '/register   -  DADOS : ' . json_encode($result) . 'ERROR :' . $httpcode,
                    'ERROR',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                $response = [
                    "code" => 400,
                    "message" => "ACME-DNS account creation failed on endpoint SSL API",
                    "errors" => json_decode($result, true)
                ];
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | ACME-LETSENCRYPT] Falha fatal na criação da conta ACME-LETSEMCRYPT!',
                'Endpoint ' . $this->acmedns_url . '/register   -  ERROR :' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                "code" => 400,
                "message" => "ACME-DNS account creation fatal failed on endpoint",
                "errors" => [$e->getMessage()]
            ];
        }
        return $response;

    }
    public function updateTxtRecord($account, $txt)
    {
        try {

            $update = ["subdomain" => $account['subdomain'], "txt" => $txt];
            $headers = [
                "X-Api-User: " . $account['username'],
                "X-Api-Key: " . $account['password'],
                "Content-Type: application/json"
            ];
            $ch = curl_init($this->acmedns_url . "/update");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($update));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpcode == 200) {
                $response = ['code' => $httpcode];
            } else {
                $s_headers = json_encode($headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $s_update = json_encode($update, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $s_body = json_encode(json_decode($result, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $this->logSys->syslog(
                    '[CDN-API | ACME-LETSENCRYPT] Falha na atualização de registro ACME-LETSENCRYPT!',
                    'Ocorreu um erro ao tentar atualizar o registro TXT no acme-dns DADOS : ' . json_encode($result) . 'ERROR :' . $httpcode .
                    "Request headers:\n{$s_headers} <--> Request body:{$s_update} <--> Response body: {$s_body}",
                    'ERROR',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                $response = ["code" => 400, 'message' => 'Failed to update TXT record in acme-dns, wait for retry'];
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | ACME-LETSENCRYPT] Falha fatal na inclusão do TXT no registro ACME-LETSENCRYPT!',
                ' Ocorreu um erro ao tentar atualizar o registro TXT no acme-dns DADOS' . json_encode($update) . 'ERROR :' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = ["code" => 400, 'message' => 'Fatal failure updating TXT record in acme-dns, contact support.'];
        }
        return $response;
    }

    public function requestCertificate($account, $domain)
    {
        try {
            $order = $this->getTXT($domain);
            Log::info("Retorno do TXT" . json_encode($order));
            if ($order['code'] == 200) {
                $updateTXT = $this->updateTxtRecord($account, $order['txt_value']);
                if ($updateTXT['code'] == 400) {
                    return $updateTXT;
                } else {
                    $certificate = $this->getCertificate($domain);
                    Log::info("Retorno do Certificado" . json_encode($certificate));
                    if (isset($certificate['code'])) {
                        $this->logSys->syslog(
                            '[CDN-API | ACME-LETSENCRYPT] Ocorreu um erro na geração do certificado ACME-LETSENCRYPT do cdn_resource ' . $domain,
                            "Dados da conta :" . json_encode($account) . " Resposta ACME.SH :" . json_encode($certificate),
                            'ERROR',
                            $this->facilityLog . ':' . basename(__FUNCTION__)
                        );
                    }
                    return $certificate;
                }

                /**
                 * Caso ja haja uma entrada TXT gerada ocorre o retorno do certificado
                 *
                 */
            } else if ($order['code'] == 202) {
                return $this->loadCertificateFiles($domain);
            } else {
                $this->logSys->syslog(
                    '[CDN-API | ACME-LETSENCRYPT] Ocorreu um erro na geração da entrada TXT do certificado ACME-LETSENCRYPT do cdn_resource ' . $domain,
                    "Dados da conta :" . json_encode($account) . " Resposta ACME.SH :" . json_encode($order),
                    'INFO',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                return $order;
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | ACME-LETSENCRYPT] Falha fatal na geração do certificado ACME-LETSENCRYPT!',
                ' Ocorreu um erro na geração do certificado DADOS' . json_encode($domain) . 'ERROR :' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = ['code' => 400, "message" => "Fatal failure when requesting certiticate the Let's Encrypt certificate.", "error" => $e->getMessage(), "trace" => $e->getTraceAsString()];
        }
        return $response;
    }

    public function getTXT($domain)
    {
        $scriptPath = base_path("Shell/get_acme_txt.sh");
        $command = "bash -c 'source ~/.bashrc; " . escapeshellcmd($scriptPath . " " . $domain . " " . base_path() . "/.acme.sh") . "'";
        $order = shell_exec($command . ' 2>&1');
        $this->logSys->syslog(
            '[CDN-API | ACME-LETSENCRYPT] Requisição da entraga TXT ao ACME-LETSENCRYPT!',
            ' Comando de requisição :' . $command . 'Retorno do comando :' . $order,
            'INFO',
            $this->facilityLog . ':' . basename(__FUNCTION__)
        );
        Log::info("Retorno do get TXT" . json_encode($order));
        $txtValue = extractTxtValue($order);
        return $txtValue;
    }

    public function getCertificate($domain)
    {
        $scriptPath = base_path("Shell/get_acme_certificate.sh");
        $command = escapeshellcmd($scriptPath . " " . $domain . " " . base_path() . "/.acme.sh");
        $certificate = shell_exec($command . ' 2>&1');

        if (strpos($certificate, 'Cert success')) {
            return $this->loadCertificateFiles($domain);
        } else {
            return ['code' => 400, 'message' => 'Certificate not generated, try again.', "error" => $certificate];
        }
    }


    public function loadCertificateFiles($domain)
    {
        $basePath = base_path() . "/.acme.sh/{$domain}_ecc/";
        Log::info("Path dos arquivos : " . $basePath);
        // Definindo os caminhos dos arquivos
        $files = [
            'certificate' => "{$basePath}{$domain}.cer",
            'private_key' => "{$basePath}{$domain}.key",
            'intermediate_certificate' => "{$basePath}ca.cer",
            'fullchain' => "{$basePath}fullchain.cer",
            'config' => "{$basePath}{$domain}.conf"
        ];

        Log::info("Lista de arquivos " . json_encode($files));
        $cert = [];

        // Verificando a existência dos arquivos e lendo o conteúdo
        foreach ($files as $key => $filePath) {
            if (file_exists($filePath)) {
                $cert[$key] = file_get_contents($filePath);
                Log::info("FILEPATH : => " . $filePath);
            } else {
                throw new Exception("{$filePath} not found.");
            }
        }

        $validate = $this->getvalidateCert($cert['config']);

        $cert['certificate_created'] = $validate['certificate_created'];
        $cert['certificate_expires'] = $validate['certificate_expires'];
        $cert['csr'] = null;

        unset($cert['config']);
        return $cert;
    }


    public function getvalidateCert($certConf)
    {

        $dados = [];

        // Expressão regular para extrair as chaves e valores
        preg_match_all("/(\w+)=\'(.*?)\'/", $certConf, $matches, PREG_SET_ORDER);

        // Percorre os resultados e extrai os valores desejados
        foreach ($matches as $match) {
            $chave = $match[1];
            $valor = $match[2];

            if ($chave === 'Le_CertCreateTimeStr') {
                $dados['certificate_created'] = str_replace("Z", "", (str_replace("T", " ", $valor)));
            } elseif ($chave === 'Le_NextRenewTimeStr') {
                $dados['certificate_expires'] = str_replace("Z", "", (str_replace("T", " ", $valor)));
            }
        }

        return $dados;
    }


    public function revokeCert($domain)
    {
        $scriptPath = base_path("Shell/get_acme_revoke.sh");
        $command = "bash -c 'source ~/.bashrc; " . escapeshellcmd($scriptPath . " " . $domain . " " . base_path() . "/.tools") . "'";
        $revoke = shell_exec($command . ' 2>&1');
        if (strpos($revoke, "Successfully") === false) {
            $this->logSys->syslog(
                '[CDN-API | ACME-LETSENCRYPT] Falha na revogação do certificado ACME-LETSENCRYPT do resource ' . $domain,
                ' Domínio' . json_encode($domain) . 'ERROR :' . $revoke,
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        } else {
            $this->logSys->syslog(
                '[CDN-API | ACME-LETSENCRYPT] Certificado ACME-LETSENCRYPT do resource ' . $domain . ' revogado com sucesso!',
                ' Domínio' . json_encode($domain) . 'INFO :' . $revoke,
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }

    }

}
