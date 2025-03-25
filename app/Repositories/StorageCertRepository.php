<?php

namespace App\Repositories;

use App\Models\CdnLetsencryptAcmeRegister;
use App\Services\SecurityService;
use Exception;
use Spatie\SslCertificate\SslCertificate;
use App\Services\EventLogService;


class StorageCertRepository
{

    private $logSys;
    protected $facilityLog;
    private $security;
    private $provisioningStep;


    public function __construct()
    {
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
        $this->security = new SecurityService();
        $this->provisioningStep = new StepProvisioningRepository();

    }

    public function saveCustonCertificate($data, $domain, $cdnResourceId)
    {

        try {
            $validate = $this->validateCustonCertificate($data, $domain, $cdnResourceId);
            if (empty($validate)) {
                $cdnLetsencryptAcmeRegisterRepository = new CdnLetsencryptAcmeRegisterRepository();
                try {
                    $this->provisioningStep->updateStep($cdnResourceId, 7, 'finished', "Custon SSL certificate is valid.");
                    $certData = $this->getCertificateData($domain, $cdnResourceId);
                    $newCertificate = $cdnLetsencryptAcmeRegisterRepository->create($certData, $cdnResourceId);
                    $this->provisioningStep->updateStep($cdnResourceId, 8, 'waiting', "Installing a customized SSL certificate ");
                    $response = ['id' => $newCertificate->id, 'code' =>200];
                } catch (Exception $e) {
                    $this->provisioningStep->updateStep($cdnResourceId, 8, 'failed', "Fatal failure in the persistence of the customized SSL certificate, contact support.");
                    $this->provisioningStep->deleteSSLSteps($cdnResourceId);
                    $response = ['errors' => ['Fatal failure in the persistence of the customized ssl certificate, contact support.', $e->getMessage()], 'code'=>400];
                }
            } else {
                $response = ["errors" => $validate, 'code'=>400];
            }
        } catch (Exception $e) {
            $response = ['errors' => ['Fatal ssl certificate upload failure, contact support.', $e->getTraceAsString()], 'code'=>400];
        }
        // unlink(storage_path("/certs/$domain.crt"));
        // unlink(storage_path("/certs/$domain.key"));
        return $response;
    }


    public function updateCustonCertificate($data, $domain, $cdnResourceId)
    {
        $validate = $this->validateCustonCertificate($data, $domain, $cdnResourceId);
        if (empty($validate)) {
            $response  =  $this->getCertificateData($domain, $cdnResourceId);
        } else {
           $response = ["errors" => $validate];
        }
         // unlink(storage_path("/certs/$domain.crt"));
        // unlink(storage_path("/certs/$domain.key"));

        return $response;
    }


    public function getCertificateData($domain, $cdnResourceId)
    {
            $key = file_get_contents(storage_path("app/certs/$domain.key"));
            try {
                $cert = file_get_contents(storage_path("app/certs/$domain.crt"));
                $key = file_get_contents(storage_path("app/certs/$domain.key"));
                $certificate = SslCertificate::createFromString($cert);
                $response  =  [
                    "code" => 200,
                    "cdn_resource_id" => $cdnResourceId,
                    "company" => $certificate->getOrganization(),
                    "certificate" => $this->security->dataEncrypt($cert),
                    "private_key" => $this->security->dataEncrypt($key),
                    "certificate_created" =>$certificate->validFromDate(),
                    "certificate_expires" =>$certificate->expirationDate()
                ];

            } catch (Exception $e) {
                $response = ['errors' => [$e->getMessage()]];
            }
            return $response;
    }

    public function validateCustonCertificate($data, $domain, $cdnResourceId)
    {
        $error = [];
        $this->provisioningStep->updateStep($cdnResourceId, 7, 'waiting', "Validating the certificate ");

        try {
            $savefile = $this->saveFile($data, $domain);
            if ($savefile['code'] == 400) {
                $this->provisioningStep->updateStep($cdnResourceId, 7, 'failed', "Error uploading SSL certificate files, try again.");

                return ['errors' => [$savefile['message']]];
            } else {
                $certificatePath = storage_path("app/certs/$domain.crt");
                $validateType = $this->validateType($certificatePath, $domain);
                if ($validateType["code"] >= 400) {
                    $this->provisioningStep->updateStep($cdnResourceId, 7, 'failed', $validateType['message']);
                    array_push($error, $validateType['message']);
                }
                $validateCertificate = $this->validateCertificate($certificatePath, $domain);
                if ($validateCertificate["code"] >= 400) {
                    $this->provisioningStep->updateStep($cdnResourceId, 7, 'failed', $validateCertificate['message']);
                    array_push($error, $validateCertificate['message']);
                }
                $validateExpirationDate = $this->validateExpirationDate($certificatePath);
                if ($validateExpirationDate["code"] >= 400) {
                    $this->provisioningStep->updateStep($cdnResourceId, 7, 'failed', $validateExpirationDate['message']);

                    array_push($error, $validateExpirationDate['message']);
                }
                $validateCompany = $this->validateCompany($certificatePath);
                if ($validateCompany["code"] >= 400) {
                    $this->provisioningStep->updateStep($cdnResourceId, 7, 'failed', $validateCompany['message']);
                    array_push($error, $validateCompany['message']);
                }
            }
        } catch (Exception $e) {
            array_push($error, 'Fatal failure ssl certificate validate, contact support.');
            $this->logSys->syslog(
                '[CDN-API | Provisioning CDN] Ocorreu uma exceção na validação do certificado SSL CUSTOMIZADO',
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data). "Resource : $domain, ID $cdnResourceId",
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
        unlink(storage_path("/certs/$domain.crt"));
        unlink(storage_path("/certs/$domain.key"));
        return $error;

    }
    public function saveFile($data, $domain)
    {
        try {
            $error = 0;

            // Define o caminho de destino relativo ao diretório storage/app
            $certPath = 'certs/';
            // Armazena o certificado
            $certificateFile = $data['certificate'];
            $certificateFile->storeAs($certPath, "$domain.crt");

            // Armazena a chave privada
            $privateKeyFile = $data['private_key'];
            $privateKeyFile->storeAs($certPath, "$domain.key");

            // Verifica se os arquivos foram realmente salvos
            if (!file_exists(storage_path("app/$certPath/$domain.crt"))) {
                $error++;
            }

            if (!file_exists(storage_path("app/$certPath/$domain.key"))) {
                $error++;
            }

            // Define a resposta com base no resultado do upload
            $response = $error > 0
                ? ['message' => 'The SSL certificate files failed to upload, please try again', 'code' => 400]
                : ['code' => 200];

        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Provisioning CDN] Ocorreu uma exceção no upload do certificado SSL',
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

            $response = ['message' => 'Fatal failure in uploading SSL certificate files, contact support.', "code" => 400];
        }

        return $response;
    }


    public function validateType($certificatePath, $domain)
    {
        $sslType = sslType($certificatePath, $domain);

        if (isset($sslType['errors'])) {
            return ["message" => $sslType['errors'], "code" => 400];
        }

        if ($sslType == 'SIMPLE') {
            $certificate = SslCertificate::createFromFile($certificatePath);
            $certificate = json_decode($certificate, true);

            if (strpos($certificate['extensions']['subjectAltName'], $domain) === false) {
                return [
                    "message" => "The SSL certificate sent is of the simple type and does not belong to the domain $domain, upload the certificate registered to the domain $domain, or upload a wildcard certificate. ",
                    "code" => 401
                ];
            } else {
                return ["code" => 200];
            }
        } elseif ($sslType == 'WILDCARD') {
            return ["code" => 200];
        }
    }

    public function validateCertificate($certificatePath)
    {
        $certificate = SslCertificate::createFromFile($certificatePath);
        if ($certificate->isValid()) {
            return ["code" => 200];
        } else {
            return [
                "message" => "Certificate not valid",
                "code" => 401
            ];
        }
    }

    public function validateExpirationDate($certificatePath)
    {
        $certificate = SslCertificate::createFromFile($certificatePath);
        if ($certificate->daysUntilExpirationDate() < env("DAYS_UNTIL_EXPIRATION_DATE")) {
            return [
                "message" => "Certificate expires in less than " . env("DAYS_UNTIL_EXPIRATION_DATE") . " days, renew the certificate and send it again. ",
                "code" => 401
            ];

        } else {
            return ["code" => 200];

        }
    }


    public function validateCompany($certificatePath)
    {
        $certificate = SslCertificate::createFromFile($certificatePath);
        $company = strtolower(str_replace("'", "", (str_replace(" ", "_", $certificate->getOrganization()))));
        if ($company == "lets_encrypt") {
            return [
                "message" => "The certificate sent was generated by the Let's Encrypt organization. For Let's Encrypt certificates, use our portal to generate them. . ",
                "code" => 401
            ];

        } else {
            return ["code" => 200];

        }

    }


}
