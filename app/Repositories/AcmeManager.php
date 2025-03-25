<?php

namespace App\Repositories;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

class AcmeManager
{
    private $acmedns_url;
    private $acmeProdUrl;
    private $acmeStagingUrl;
    private $logSys;
    private $facilityLog;
    private static $privateKey;
    private static $kid;

    private $api_key;
    private $eab_kid;
    private $eab_hmac_key;




    public static function setPKey($pKey)
    {
        self::$privateKey = $pKey;
    }

    public static function getPKey()
    {
        return self::$privateKey;
    }

    public static function setKid($value)
    {
        self::$kid = $value;
    }

    public static function getKid()
    {
        return self::$kid;
    }


    public function __construct()
    {
        $this->acmedns_url = env('ACME_DNS_URL');
        $this->acmeProdUrl = 'https://acme.zerossl.com/v2/DV90';
        $this->logSys = app('App\Services\EventLogService');
        $this->facilityLog = basename(__FILE__);
        $this->api_key = env('ZEROSSL_API_KEY');
        $this->eab_kid = env('ZEROSSL_EAB_KID');
        $this->eab_hmac_key = env('ZEROSSL_EAB_HMAC_KEY');
    }

    /**
     * Registrar uma nova conta no servidor ACME-DNS.
     */
    public function registerAccount()
    {
        try {
            $ch = curl_init("https://auth-dns.vcdn.net.br/register");
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

    /**
     * Atualizar um registro TXT no servidor ACME-DNS.
     */
    public function updateTxtRecord($account, $txt)
    {
        $update = ["subdomain" => $account['subdomain'], "txt" => $txt];
        $headers = [
            "X-Api-User: " . $account['username'],
            "X-Api-Key: " . $account['password'],
            "Content-Type: application/json"
        ];
        try {

            $ch = curl_init($this->acmedns_url . "/update");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($update));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            if ($httpcode == 200) {
                Log::info("Alteração do TXT : " . json_encode($result));
                $response = ['code' => $httpcode];
            } else {
                Log::info("Falha na Alteração do TXT : " . json_encode($result));

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
                $response = ["code" => 400];
            }
        } catch (Exception $e) {
            Log::info("ERRO FATAL  na Alteração do TXT : " . $e->getMessage());
            $this->logSys->syslog(
                '[CDN-API | ACME-LETSENCRYPT] Falha fatal na inclusão do TXT no registro ACME-LETSENCRYPT!',
                ' Ocorreu um erro ao tentar atualizar o registro TXT no acme-dns DADOS' . json_encode($update) . 'ERROR :' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = ["code" => 400];
        }
        return $response;
    }

    public function requestCertificate($domain)
    {

        $this->setPKey($this->getNewKey());

        $this->setKid($this->newAccount($this->getPKey()));


    }


    public function newOrder($domain)
    {

        $newOrderUrl = "{$this->acmeProdUrl}/newOrder";
        $payload = ['identifiers' => [['type' => 'dns', 'value' => $domain]]];

        // $protected = $this->sendSignedRequest($newOrderUrl, $payload, $this->getPKey(), $accountKid);


    }


    /**
     * Gerar um novo certificado SSL.
     */

    public function requestCertificatea($account, $domain)
    {
        try {
            $this->setPKey($this->getNewKey());
            Log::info("Private key gerada localmente.");

            // 1. Obter o endpoint de criação de ordem no ACME
            $directory = $this->sendCurlRequest($this->acmeProdUrl, 'GET');
            $directoryData = json_decode($directory['body'], true);

            if (empty($directoryData['newOrder'])) {
                throw new Exception("Falha ao obter endpoint de nova ordem.");
            }
            $newOrderUrl = $directoryData['newOrder'];

            // 2. Criar uma nova ordem para o domínio
            $payload = ['identifiers' => [['type' => 'dns', 'value' => $domain]]];

            // Armazenar o Kid da conta em uma variável de instância
            $accountKid = $this->createNewAccount($this->getPKey());

            $response = $this->sendSignedRequest($newOrderUrl, $payload, $this->getPKey(), $accountKid);
            $orderData = json_decode($response['body'], true);
            Log::info("Dados de nova ordem: " . json_encode($orderData));

            if (empty($orderData['authorizations'])) {
                throw new Exception("Falha ao criar ordem para o domínio: {$domain}");
            }
            $authorizationUrl = $orderData['authorizations'][0];

            // 3. Obter desafio para validação do domínio (DNS-01)
            $authResponse = $this->sendCurlRequest($authorizationUrl, 'GET');
            $authData = json_decode($authResponse['body'], true);
            Log::info("Dados de Autorização: " . json_encode($authData));

            if (empty($authData['challenges'])) {
                throw new Exception("Falha ao obter desafios para o domínio: {$domain}");
            }

            $challenge = $this->getDnsChallenge($authData['challenges']);
            Log::info("Dados de getDnsChallenge: " . json_encode($challenge));

            $keyAuthorization = $this->getKeyAuthorization($challenge['token']);
            Log::info("Dados de getKeyAuthorization: " . json_encode($keyAuthorization));

            // 4. Publicar o registro DNS TXT
            $txtValue = $this->calculateDnsTxtValue($challenge, $domain);
            Log::info("Cálculo do TXT: " . json_encode($txtValue));

            $this->updateTxtRecord($account, $txtValue['value']);
            Log::info("Registro TXT atualizado no DNS.");

            // 5. Confirmar o desafio
            $confirmChallenge = $this->sendSignedRequest($challenge['url'], ['status' => 'valid'], $this->getPKey(), $accountKid);
            Log::info("Confirmação do desafio: " . json_encode($confirmChallenge));

            // 6. Aguardar validação do desafio
            $this->waitForChallengeValidation($challenge['url'], $domain);

            // Gerar nova chave para o CSR
            $csrPrivateKey = $this->getNewKey();

            // 7. Finalizar a ordem com o CSR
            $csr = $this->generateCsr([$domain], $csrPrivateKey);
            $csrEncoded = formatCsr($csr);

            // Enviar o CSR para finalizar a ordem
            $finalizeResponse = $this->sendSignedRequest(
                $orderData['finalize'],
                ['csr' => $csrEncoded],
                $csrPrivateKey,
                $accountKid
            );
            Log::info("Dados de finalização da ordem: " . json_encode($finalizeResponse));

            // 8. Baixar o certificado emitido
            $certificate = $this->downloadCertificate($orderData['url']);
            Log::info("Certificado emitido com sucesso.");

            return ['code' => 200, 'certificate' => $certificate];
        } catch (Exception $e) {
            $this->logCriticalError("Erro ao solicitar certificado para o domínio: {$domain}", $e);
            return ['code' => 500, 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()];
        }
    }


    private function waitForChallengeValidation($challengeUrl, $domain, $maxAttempts = 20, $delay = 20)
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            sleep($delay);
            $challengeStatus = $this->sendCurlRequest($challengeUrl, 'GET');
            $challengeData = json_decode($challengeStatus['body'], true);

            if ($challengeData['status'] === 'valid') {
                Log::info("Desafio validado com sucesso para o domínio: {$domain}");
                return;
            }

            if ($challengeData['status'] !== 'pending') {
                throw new Exception("Erro na validação do desafio: " . json_encode($challengeData));
            }
        }

        throw new Exception("Validação do desafio excedeu o tempo limite para o domínio: {$domain}");
    }


    private function getDnsChallenge(array $challenges)
    {
        $challenge = array_filter($challenges, fn($c) => $c['type'] === 'dns-01');
        return array_values($challenge)[0] ?? throw new Exception("Nenhum desafio DNS-01 encontrado.");
    }

    private function downloadCertificate($orderUrl, $maxAttempts = 10, $delay = 5)
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            sleep($delay);
            $orderStatus = $this->sendCurlRequest($orderUrl, 'GET');
            $orderData = json_decode($orderStatus['body'], true);

            if ($orderData['status'] === 'valid' && isset($orderData['certificate'])) {
                $certificateUrl = $orderData['certificate'];
                $certResponse = $this->sendCurlRequest($certificateUrl, 'GET');
                return $certResponse['body'];
            }

            if ($orderData['status'] !== 'processing') {
                throw new Exception("Erro ao processar o certificado: " . json_encode($orderData));
            }
        }

        throw new Exception("Emissão do certificado excedeu o tempo limite.");
    }


    /**
     * Enviar requisição cURL genérica.
     */
    private function sendCurlRequest($url, $method, $body = null, $headers = [], $getHeaders = false)
    {
        Log::info("URL sendCurlRequest: " . json_encode($url));
        Log::info("URL metodo: " . json_encode($method));
        Log::info("URL corpo: " . json_encode($body));

        // Adiciona os cabeçalhos obrigatórios
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($getHeaders == true) {
            curl_setopt($ch, CURLOPT_HEADER, true); // Inclui os cabeçalhos na resposta
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Tempo de espera
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); // Tempo de conexão

        // Configura o corpo da requisição
        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        // Adiciona os cabeçalhos obrigatórios
        $defaultHeaders = [
            'Content-Type: application/jose+json', // Cabeçalho exigido pela API ACME
        ];

        if ($headers) {
            $defaultHeaders = array_merge($defaultHeaders, $headers);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $defaultHeaders);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        Log::info("retorno sendCurlRequest: " . json_encode($result));
        Log::info("retorno sendCurlRequest httpcode: " . json_encode($httpCode));

        if ($error) {
            throw new Exception("Erro de cURL: $error");
        }

        return ['body' => $result, 'http_code' => $httpCode];
    }

    /**
     * Registrar logs de erro.
     */
    private function logError($message, $response)
    {
        $this->logSys->syslog(
            "[ACME-LETSENCRYPT] {$message}",
            json_encode($response, JSON_PRETTY_PRINT),
            'ERROR',
            $this->facilityLog
        );
    }

    /**
     * Registrar logs de erro crítico.
     */
    private function logCriticalError($message, $exception)
    {
        $this->logSys->syslog(
            "[ACME-LETSENCRYPT] {$message}",
            $exception->getMessage(),
            'ERROR',
            $this->facilityLog
        );
    }

    public static function generateCsr(array $domains, $key): string
    {
        $primaryDomain = current($domains);
        $config = [
            '[req]',
            'distinguished_name=req_distinguished_name',
            '[req_distinguished_name]',
            '[v3_req]',
            '[v3_ca]',
            '[SAN]',
            'subjectAltName=' . implode(',', array_map(function ($domain) {
                return 'DNS:' . $domain;
            }, $domains)),
        ];

        $fn = tempnam(sys_get_temp_dir(), md5(microtime(true)));
        file_put_contents($fn, implode("\n", $config));
        $csr = openssl_csr_new([
            'countryName' => 'NL',
            'commonName' => $primaryDomain,
        ], $key, [
            'config' => $fn,
            'req_extensions' => 'SAN',
            'digest_alg' => 'sha512',
        ]);
        unlink($fn);

        if ($csr === false) {
            throw new \Exception('Could not create a CSR');
        }

        if (openssl_csr_export($csr, $result) == false) {
            throw new \Exception('CSR export failed');
        }

        return trim($result);
    }


    public static function getNewKey(int $keyLength = 4096): string
    {
        $key = openssl_pkey_new([
            'private_key_bits' => $keyLength,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if (!openssl_pkey_export($key, $pem)) {
            value:
            throw new Exception('Erro ao exportar a chave privada');
        }

        return $pem;
    }

    private function sendSignedRequest($url, $payload, $pKey, $kid = null, $getHeader = false)
    {
        try {
            // 1. Obter o nonce
            $nonce = $this->getNonce();
            if (!$nonce) {
                throw new Exception("Falha ao obter nonce do servidor ACME.");
            }

            // 2. Criar cabeçalho protegido (Header)
            $protectedHeader = [
                "alg" => "RS256",  // Algoritmo de assinatura
                "nonce" => $nonce, // Nonce obtido
                "url" => $url,     // URL para a requisição
            ];

            if ($kid) {
                $protectedHeader["kid"] = $kid;  // Caso exista, adicionar o Kid da conta
            } else {
                $protectedHeader["jwk"] = $this->getJwk($pKey); // Caso contrário, incluir a chave pública JWK
            }

            // 3. Codificar o protectedHeader
            $protectedHeaderEncoded = $this->base64UrlEncode(json_encode($protectedHeader, JSON_UNESCAPED_SLASHES));

            // 4. Codificar o payload
            $payloadEncoded = $payload === [] ? "" : $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

            // 5. Assinar os dados (protected header + payload)
            $dataToSign = $protectedHeaderEncoded . "." . $payloadEncoded;
            openssl_sign($dataToSign, $signature, $pKey, OPENSSL_ALGO_SHA256);
            $signatureEncoded = $this->base64UrlEncode($signature);

            // 6. Criar o corpo da requisição
            $requestBody = json_encode([
                "protected" => $protectedHeaderEncoded,
                "payload" => $payloadEncoded,
                "signature" => $signatureEncoded,
            ], JSON_UNESCAPED_SLASHES);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Erro ao gerar o JSON: " . json_last_error_msg());
            }

            // 7. Enviar a requisição para o servidor ACME
            $response = $this->sendCurlRequest($url, 'POST', $requestBody, $getHeader);
            return ['code' => $response['http_code'], 'body' => $response['body']];
        } catch (Exception $e) {
            throw new Exception("Erro no método sendSignedRequest: " . $e->getMessage());
        }
    }



    private function calculateDnsTxtValue($dnsChallenge, $domain)
    {

        $token = $dnsChallenge['token'];
        $hashInput = $token . '.' . $this->getAccountKeyThumbprint();
        $txtValue = $this->base64UrlEncode(hash('sha256', $hashInput, true));
        $txtRecord = [
            'name' => '_acme-challenge.' . $domain,
            'value' => $txtValue,
        ];

        return $txtRecord;

    }


    private function getNonce()
    {
        try {
            // Obter o diretório ACME
            $directory = $this->sendCurlRequest("{$this->acmeProdUrl}", 'GET');
            $directoryData = json_decode($directory['body'], true);


            // Configurar requisição HEAD para obter o nonce
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "{$this->acmeProdUrl}/newNonce",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true, // Incluir cabeçalhos na resposta
                CURLOPT_NOBODY => true, // Ignorar corpo da resposta
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "HEAD",
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/jose+json",
                    "X-API-Key: {$this->api_key}",
                    "EAB:{$this->eab_hmac_key}"
                ],
            ]);

            // Executar a requisição
            $nonceResponse = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $err = curl_error($curl);
            curl_close($curl);

            // Verificar erros de execução do cURL
            if ($err) {
                throw new Exception("Erro cURL ao tentar obter o nonce: {$err}");
            }

            // Verificar código HTTP da resposta
            if ($httpCode !== 200) {
                throw new Exception("Falha ao obter o nonce. Código HT  TP: {$httpCode}");
            }

            // Capturar o cabeçalho Replay-Nonce
            $nonce = $this->extractNonceFromHeaders($nonceResponse);

            if (!$nonce) {
                throw new Exception("Nonce não encontrado no cabeçalho 'Replay-Nonce'.");
            }

            return $nonce;
        } catch (Exception $e) {
            // Logar o erro e lançar exceção para o chamador
            Log::error("Erro ao obter o nonce: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extrai o valor do cabeçalho 'Replay-Nonce' da resposta.
     *
     * @param string $response A resposta completa (cabeçalhos incluídos).
     * @return string|null O nonce extraído ou null se não encontrado.
     */
    private function extractNonceFromHeaders($response)
    {
        $headers = explode("\r\n", trim($response));

        foreach ($headers as $header) {
            if (stripos($header, 'Replay-Nonce:') === 0) {
                return trim(substr($header, strlen('Replay-Nonce:')));
            }
        }

        return null; // Retorna null se o cabeçalho não for encontrado
    }


    private function getJwk($key)
    {
        $pKey = openssl_pkey_get_private($key);
        $keyDetails = openssl_pkey_get_details($pKey);

        if (!isset($keyDetails['rsa'])) {
            throw new Exception("Falha ao obter detalhes da chave pública.");
        }

        return [
            'kty' => 'RSA',
            'n' => $this->base64UrlEncode($keyDetails['rsa']['n']),
            'e' => $this->base64UrlEncode($keyDetails['rsa']['e']),
        ];
    }

    private function getKeyAuthorization($token)
    {
        $accountKeyThumbprint = $this->getAccountKeyThumbprint();
        return $token . '.' . $accountKeyThumbprint;
    }

    private function base64UrlEncode($data)
    {
        $base64 = base64_encode($data);
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function getAccountKeyThumbprint()
    {
        $privateKeyResource = openssl_pkey_get_private(self::$privateKey);
        // Carregar as informações da chave pública no formato PEM
        $keyDetails = openssl_pkey_get_details($privateKeyResource);

        $n = $keyDetails['rsa']['n']; // Módulo (n)
        $e = $keyDetails['rsa']['e']; // Expoente (e)

        // Base64 URL-Safe encode
        $base64UrlEncode = function ($data): string {
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        };

        // Montar o JWK no formato esperado
        $jwk = [
            'e' => $base64UrlEncode($e),
            'kty' => 'RSA',
            'n' => $base64UrlEncode($n),
        ];

        // Serializar o JWK em JSON ordenado
        $jwkJson = json_encode($jwk, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Gerar o hash SHA-256 do JSON do JWK
        $hash = hash('sha256', $jwkJson, true);

        // Retornar o resultado em Base64 URL-Safe
        return $base64UrlEncode($hash);
    }

    private function newAccount($pKey)
    {
        try {
            // Criar o payload para o novo registro da conta
            $payload = [
                "termsOfServiceAgreed" => true, // Aceitar os termos de serviço
            ];

            // Gerar cabeçalho protegido
            $protectedHeader = [
                "alg" => "HS256",
                "nonce" => $this->getNonce(),
                "url" => "{$this->acmeProdUrl}/newAccount",
                "jwk" => $this->getJwk($pKey),
            ];

            // Codificar os componentes
            $protectedHeaderEncoded = $this->base64UrlEncode(json_encode($protectedHeader));
            $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

            // Assinar os dados
            $dataToSign = $protectedHeaderEncoded . "." . $payloadEncoded;
            openssl_sign($dataToSign, $signature, $pKey, OPENSSL_ALGO_SHA256);
            $signatureEncoded = $this->base64UrlEncode($signature);

            // Criar corpo da requisição
            $requestBody = json_encode([
                "protected" => $protectedHeaderEncoded,
                "payload" => $payloadEncoded,
                "signature" => $signatureEncoded,
            ]);

            // Calcular o tamanho do corpo da requisição
            $contentLength = strlen($requestBody);


            $response = Http::withHeaders([
                'Content-Type' => 'application/jose+json',
                'Content-Length' => $contentLength,
                'X-API-Key' => $this->api_key,
                'EAB' => $this->eab_hmac_key

            ])->post("{$this->acmeProdUrl}/newAccount", [
                        "protected" => $protectedHeaderEncoded,
                        "payload" => $payloadEncoded,
                        "signature" => $signatureEncoded,
                    ]);

            return $response->json();



        } catch (Exception $e) {
            // Tratar qualquer erro
            Log::error("Erro ao criar a conta: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => "Erro ao criar a conta: " . $e->getMessage(),
            ];
        }
    }

    /**
     *
     *
     */

    private function sendSignedCSR($url, $payload, $pKey, $kid = null, $getHeader = false)
    {
        try {
            $nonce = $this->getNonce();
            if (!$nonce) {
                throw new Exception("Falha ao obter nonce do servidor ACME.");
            }

            // Gerar cabeçalho protegido
            $protectedHeader = [
                "alg" => "RS256",
                "nonce" => $nonce,
                "url" => $url,
            ];

            if ($kid) {
                $protectedHeader["kid"] = $kid;
            } else {
                $protectedHeader["jwk"] = $this->getJwk($pKey);
            }

            Log::info("ProtectedGHeader : " . json_encode($protectedHeader));
            Log::info("Payload : " . json_encode($payload));


            $protectedHeaderEncoded = $this->base64UrlEncode(json_encode($protectedHeader, JSON_UNESCAPED_SLASHES));

            // Verificar se o payload é vazio
            $payloadEncoded = $payload === [] ? "" : $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

            // Assinar os dados
            $dataToSign = $protectedHeaderEncoded . "." . $payloadEncoded;
            openssl_sign($dataToSign, $signature, $pKey, OPENSSL_ALGO_SHA256);
            $signatureEncoded = $this->base64UrlEncode($signature);

            // Criar corpo da requisição
            $requestBody = json_encode([
                "protected" => $protectedHeaderEncoded,
                "payload" => $payloadEncoded,
                "signature" => $payloadEncoded,
            ], JSON_UNESCAPED_SLASHES);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Erro ao gerar o JSON: " . json_last_error_msg());
            }

            $jws = $protectedHeaderEncoded . '.' . $payloadEncoded . '.' . $payloadEncoded;
            // Enviar a requisição
            $response = $this->sendCurlRequestCSR($jws, $url, 'POST', $requestBody, $getHeader);

            return ['code' => $response['http_code'], 'body' => $response['body']];
        } catch (Exception $e) {
            throw new Exception("Erro no método sendSignedRequest: " . $e->getMessage());
        }
    }

    private function sendCurlRequestCSR($jws, $url, $method, $body = null, $headers = [], $getHeaders = false)
    {
        Log::info("URL sendCurlRequest: " . json_encode($url));
        Log::info("URL metodo: " . json_encode($method));
        Log::info("URL corpo: " . json_encode($body));

        // Adiciona os cabeçalhos obrigatórios
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($getHeaders == true) {
            curl_setopt($ch, CURLOPT_HEADER, true); // Inclui os cabeçalhos na resposta
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Tempo de espera
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); // Tempo de conexão

        // Configura o corpo da requisição
        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        // Adiciona os cabeçalhos obrigatórios
        $defaultHeaders = [
            'Content-Type: application/jose+json', // Cabeçalho exigido pela API ACME
            'Authorization: Bearer ' . $jws
        ];

        if ($headers) {
            $defaultHeaders = array_merge($defaultHeaders, $headers);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $defaultHeaders);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        Log::info("retorno sendCurlRequest: " . json_encode($result));
        Log::info("retorno sendCurlRequest httpcode: " . json_encode($httpCode));

        if ($error) {
            throw new Exception("Erro de cURL: $error");
        }

        return ['body' => $result, 'http_code' => $httpCode];
    }
}
