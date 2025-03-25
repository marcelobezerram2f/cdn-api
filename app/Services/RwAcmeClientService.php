<?php

namespace App\Services;

use RW\Acme\Client;
use RW\Acme\Protocol\Certificate\CertificateRequest;
use RW\Acme\Protocol\Authorization\AuthorizationChallenge;

class RwAcmeClientService
{
    private $client;

    public function __construct()
    {
        // Inicializa o cliente ACME
        $this->client = new Client([
            'directoryUrl' => 'https://acme-v02.api.letsencrypt.org/directory', // URL do Let's Encrypt
        ]);
    }

    public function registerAccount($email)
    {
        // Registrar uma conta no ACME
        $this->client->registerAccount(['email' => $email]);
    }

    public function requestCertificate($domain)
    {
        // Crie um novo pedido para o domínio
        $order = $this->client->createOrder([$domain]);

        // Pegue a autorização e o desafio HTTP-01
        $authorization = $order->getAuthorizations()[0];
        $challenge = $authorization->getHttpChallenge();

        // Servir o desafio HTTP
        $token = $challenge->getToken();
        $payload = $challenge->getPayload();

        // Você precisará expor isso no servidor HTTP (por exemplo, usando Storage ou outro mecanismo para servir o desafio)
        $this->storeChallenge($token, $payload);

        // Informar que o desafio está pronto
        $this->client->completeChallenge($challenge);

        // Finalizar a ordem e solicitar o certificado
        $csr = $this->generateCsr($domain);
        $this->client->finalizeOrder($order, $csr);

        // Baixar o certificado
        $certificate = $this->client->getCertificate($order);

        // Salvar o certificado em um arquivo
        file_put_contents(storage_path('certificates/' . $domain . '.pem'), $certificate->getCertificate());

        return $certificate;
    }

    private function generateCsr($domain)
    {
        // Gera o CSR (Certificate Signing Request) para o domínio
        $csr = new CertificateRequest();
        $csr->setSubject(['CN' => $domain]);

        return $csr;
    }

    private function storeChallenge($token, $payload)
    {
        // Salvar o desafio HTTP-01 para servir através do servidor web
        Storage::disk('local')->put('acme-challenges/' . $token, $payload);
    }
}
