<?php

require __DIR__ . '/vendor/autoload.php';

$red = "\033[31m";
$green = "\033[32m";
$reset = "\033[0m";
$yellow = "\033[0;33m";

echo "Script de SELF-TEST ... \n";
echo "Projeto : $yellow CDN-API $reset \n";
echo "Versão  : $yellow 1.0 $reset \n";
echo "Objeto  : $yellow Instalação $reset \n\n";


function getParams()
{

    $envFilePath = '.env';
    $lines = file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $envVariables = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $envVariables[$key] = $value;
        }
    }
    $desiredKeys = ['APP_HOST', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
    foreach ($desiredKeys as $key) {
        if (isset($envVariables[$key])) {
            $$key = $envVariables[$key];
        } else {
            $$key = null;
        }
    }

    return ['url' => $APP_HOST, 'dbHost' => $DB_HOST, 'dbPORT' => $DB_PORT, 'dbDataBase' => $DB_DATABASE, 'dbuUsername' => $DB_USERNAME, 'dbPasswd' => $DB_PASSWORD];
}


function loadEnv($filePath)
{
    if (!file_exists($filePath)) {
        throw new Exception("Arquivo .env não encontrado: $filePath");
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Ignora comentários
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        putenv("$key=$value"); // Define a variável de ambiente
    }
}

function dbConn()
{
    // Carregar variáveis de ambiente do arquivo .env
    loadEnv(__DIR__ . '/.env');

    // Usar getenv para acessar as variáveis
    $hostname = getenv('DB_HOST');
    $database = getenv('DB_DATABASE');
    $username = getenv('DB_USERNAME');
    $password = getenv('DB_PASSWORD');

    echo "$hostname|$database|$username|$password";
    /*$params = getParams();
    $database = $params['dbDataBase'];
    $host = $params['dbHost'];
    $username = $params['dbuUsername'];
    $passwd = $params['dbPasswd'];
    var_dump($database, $host, $username, $passwd);*/


    try {
        $pdo = new \PDO("mysql:dbname=$database;host=$hostname", $username, $password);
        return $pdo;
    } catch (\PDOException $e) {
        print_r($e->errorInfo);
    }

}

function getCredentials()
{
    global $red, $yellow, $green, $reset;

    $pdo = dbConn();

    if ($pdo instanceof \PDO) {
        echo "Conexão com o banco de dados estabelecida com sucesso!\n";
        $text = "CDN - API - Conexão com o banco de dados estabelecida com sucesso";

    } else {
        echo "Falha na recuperação do token. $red Fail $reset...\n\n";
        $text = "CDN - API - Falha na recuperação do token  :  PDO ERROR($pdo)";
    }

    eventLog($text, json_encode($pdo));


    $sql = $pdo->prepare("SELECT id,secret FROM oauth_clients LIMIT 1");
    $sql->execute();
    return $sql->fetch();

}


function getToken()
{

    global $red, $yellow, $green, $reset;

    loadEnv(__DIR__ . '/.env');

    $url = getenv('APP_URL'). "/oauth/token";

    $credentials = getCredentials();

    $data = [
        "grant_type" => "client_credentials",
        "client_id" => $credentials['id'],
        "client_secret" => $credentials['secret'],
    ];


    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt(
        $curl,
        CURLOPT_HTTPHEADER,
        array(
            "Content-Type: application/json"
        )
    );

    $request = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    $result = json_decode($request, true);
    if ($httpcode == 200) {
        echo "Autenticação efetuada com sucesso. $green ok $reset... \n\n";
        $text = "CDN - API - Autenticação efetuada com sucesso.";
    } else if ($httpcode >= 400) {
        echo "Falha na recuperação do token. $red Fail $reset...\n\n";
        $text = "CDN - API - Falha na recuperação do token  :  httpcode $httpcode " . json_encode($request);
    }
    if ($error) {
        echo "Falha no teste do endpoint. $red Fail $reset...\n\n";
        $text = "CDN - API - Teste de consumo do  endpoint $url (ocorreu falha no cURL)  :  $error";
        $httpcode = 500;
    }

    eventLog($text, $httpcode);

    return $result['access_token'];

}



function apiConsumers($method, $token, $endpoint, $payload = null): void
{

    global $red, $yellow, $green, $reset;

    loadEnv(__DIR__ . '/.env');
    $url = getenv('APP_URL'). $endpoint;
    echo "$yellow CDN -API $reset- testando o endpoint $url \n";

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    if ($method == "POST") {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    curl_setopt(
        $curl,
        CURLOPT_HTTPHEADER,
        array(
            "Content-Type: application/json",
            "Authorization: Bearer $token"
        )
    );
    $request = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($httpcode == 200 || $httpcode == 202) {
        echo "Teste Efetuado com sucesso. $green ok $reset... \n\n";
        $text = "CDN - API - Teste de consumo do  endpoint $url (efetuado com sucesso)";

    } else if ($httpcode == 400) {
        echo "Falha no teste do endpoint. $red Fail $reset...\n\n";
        $text = "CDN - API - Teste de consumo do  endpoint $url (ocorreu falha)  : " . json_encode($request);

    } else {
        echo "Falha no teste do endpoint. $red Fail $reset...\n\n";
        $text = "CDN - API - Teste de consumo do  endpoint $url (SERVER ERROR) " . $httpcode;
    }

    if ($error) {
        echo "Falha no teste do endpoint. $red Fail $reset...\n\n";
        $text = "CDN - API - Teste de consumo do  endpoint $url (ocorreu falha no cURL)  :  $error";
        $httpcode = 500;

    }

    eventLog($text, $httpcode);

}


function eventLog($text, $httpCode)
{
    $folderPath = __DIR__ . '/storage/logs/self-test/';
    $logFile = $folderPath . 'selftest-' . date('Y-m-d') . '.log';
    if (!file_exists($folderPath)) {
        if (mkdir($folderPath, 0777, true)) {
        } else {
            echo "Não foi possível criar a pasta.\n";
        }
    }
    $append = '[' . date('Y-m-d H:i:s') . '] ' . $text . 'http code :' . $httpCode . "\n";
    file_put_contents($logFile, $append, FILE_APPEND);
}


function testEndpoint()
{
    echo " Testando endpoints ... \n\n";

    $payload = [
        "start_date" => date('Y-m-d'),
        "tenant" => "lab",
    ];

    $token = getToken();
    apiConsumers('POST', $token, '/api/v1/billing/summarized', $payload);
    apiConsumers("POST", $token, '/api/v1/billing/statement', $payload);
    apiConsumers('POST', $token, '/api/v1/billing/summaryHour', $payload);

}

testEndpoint();

echo ">>>>>>> Fim dos testes de endpoints------------------------------------------------------------------------------------------ \n\n";
