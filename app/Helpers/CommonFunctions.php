<?php
use App\Services\EventLogService;
use Illuminate\Support\Facades\DB;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPIOException;





function rmCertFolder($path) {
    if (!is_dir($path)) {
        return false; // Retorna falso se não for um diretório válido
    }
    $files = array_diff(scandir($path), ['.', '..']);
    foreach ($files as $file) {
        $item = $path . DIRECTORY_SEPARATOR . $file;
        if (is_dir($item)) {
            rmCertFolder($item); // Chamada recursiva para subdiretórios
        } else {
            unlink($item); // Exclui files
        }
    }
    return rmdir($path); // Remove a pasta vazia
}


function verifyRenew($startDate, $endDate, $interval) {
    // Converte $startDate para objeto DateTime (formato: "YYYY-MM-DD")
    $initialDt = DateTime::createFromFormat('Y-m-d', $startDate);

    // Extrai apenas a parte da data de $endDate e converte para objeto DateTime (formato: "YYYY-MM-DD H:i:s")
    $endDateOnly = substr($endDate, 0, 10);
    $finalDt = DateTime::createFromFormat('Y-m-d', $endDateOnly);

    // Verifica se as datas são válidas
    if (!$initialDt || !$finalDt) {
        return false;
    }

    // Se a data inicial for maior que a data final, retorna verdadeiro
    if ($initialDt > $finalDt) {
        return true;
    }

    // Calcula a diferença em dias entre as duas datas
    $diff = $initialDt->diff($finalDt)->days;

    // Retorna verdadeiro se a diferença for menor ou igual ao intervalo
    return $diff <= $interval;
}


function parseStringToArray($input)
{
    $lines = explode("\n", trim($input));
    $result = [];

    foreach ($lines as $line) {
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(":", $line, 2);
            $key = trim(strtolower(str_replace([' ', '/'], '_', $key)));
            $result[$key] = trim($value);
        } else {
            if (!isset($result['caso'])) {
                $result['caso'] = '';
            }
            $result['caso'] .= ($result['caso'] ? "\n" : "") . trim($line);
        }
    }

    return $result;
}

function formatCsr(string $csr): string
{
    $csr = preg_replace('/-----BEGIN CERTIFICATE REQUEST-----/', '', $csr);
    $csr = preg_replace('/-----END CERTIFICATE REQUEST-----/', '', $csr);
    $csr = preg_replace('/\s+/', '', $csr); // Remove quebras de linha e espaços
    return $csr;

}

function apiConsumer($payload, $api, $endpoint)
{

    $url = $api->url . "/" . $endpoint;
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt(
        $curl,
        CURLOPT_HTTPHEADER,
        array(
            "Authorization: Basic " . $api->token,
            "Content-Type: application/json",
        )
    );
    $request = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($code >= 400) {
        if ($code == 404) {
            $response = [
                'message' => 'api ' . $api->api_name . ' execution failure ',
                'errors' => $url . ' - endpoint not found ',
                'code' => $code
            ];
        } else {
            $response = [
                'message' => 'api ' . $api->api_name . ' execution failure ',
                'errors' => json_decode($request, true),
                'code' => $code
            ];
        }
    } else {
        $response = json_decode($request, true);
    }

    return $response;

}

function externalIdMask($externalID)
{
    // Remove qualquer caractere que não seja numérico
    $externalID = preg_replace('/\D/', '', $externalID);

    if (strlen($externalID) === 11) {
        // É um CPF
        return substr($externalID, 0, 3) . '******' . substr($externalID, -2);
    } elseif (strlen($externalID) === 14) {
        // É um CNPJ
        return substr($externalID, 0, 3) . '********' . substr($externalID, -2);
    } else {
        return 'External ID NOT VALID';
    }
}

function getSummaryValidate($data)
{

    $error = [];

    if (isset($data['start_date'])) {
        if (strtotime($data['start_date']) === false) {
            array_push($error, 'value entered for the start_date field does not correspond to a valid date value');
        }
    } else {
        array_push($error, 'at least the start_date field must be entered');
    }

    if (isset($data['end_date'])) {

        if (strtotime($data['end_date']) === false || substr_count($data['end_date'], '-') != 2) {
            array_push($error, 'value entered for the end_date field does not correspond to a valid date value');
        } else {
            if (strtotime($data['start_date']) > strtotime($data['end_date'])) {
                array_push($error, 'the end date must be later than the start date ');

            }
        }

        if (isset($data['tenant'])) {
            if (!preg_match('/^[a-z0-9]+$/', strtolower($data['tenant']))) {
                array_push($error, 'the tenant field format is invalid');
            }
        } else {
            array_push($error, 'the tenant field is required');

        }
        if (isset($data['page'])) {
            if (is_int($data['page']) === false) {
                array_push($error, 'the page field must receive integers');
            }
        }
    }

    return $error;

}

function getRawDataValidate($data)
{

    $error = [];

    if (isset($data['start_date'])) {
        if (strtotime($data['start_date']) === false) {
            array_push($error, 'value entered for the start_date field does not correspond to a valid date value');
        }
    } else {
        array_push($error, 'at least the start_date field must be entered');
    }

    if (isset($data['end_date'])) {
        if (strtotime($data['end_date']) === false) {
            array_push($error, 'value entered for the end_date field does not correspond to a valid date value');
        }
    }

    if (isset($data['start_hour'])) {
        if (is_int($data['start_hour']) === false) {
            array_push($error, 'the start_hour field must receive integers');
        } else {
            if ($data['start_hour'] < 0 || $data['start_hour'] > 23) {
                array_push($error, 'the start_hour field must be greater than or equal to zero and less than or equal to twenty-three');
            }
        }
    }

    if (isset($data['end_hour'])) {
        if (is_int($data['end_hour']) === false) {
            array_push($error, 'the start_hour field must receive integers');
        } else {
            if ($data['end_hour'] < 0 || $data['end_hour'] > 23) {

                array_push($error, 'the end_hour field must be greater than or equal to zero and less than or equal to twenty-three');
            }
        }
    }

    return $error;

}


function paginate($array, $thisPage, $perPageData)
{
    $initialPage = ($thisPage - 1) * $perPageData;

    $finalIndex = $initialPage + $perPageData;

    $pageItems = array_slice($array, $initialPage, $perPageData);

    $totalItems = count($array);
    $totalPage = ceil($totalItems / $perPageData);

    $response = [
        'thisPage' => $thisPage,
        'nextPage' => $totalPage > 1 ? $thisPage + 1 : $totalPage,
        'perPageData' => $perPageData,
        'totalItems' => $totalItems,
        'totalPage' => $totalPage,
        'pageItems' => $pageItems
    ];

    return $response;
}

function summarizeBytesTransmitted($data)
{
    $summarizedData = [];
    foreach ($data as $item) {
        $tenant = $item['tenant'];
        if (!isset($summarizedData[$tenant])) {
            $summarizedData[$tenant] = [
                'tenant' => $item['tenant'],
                'date' => $item['date'],
                'total_bytes_transmitted' => 0
            ];
        }
        $summarizedData[$tenant]['total_bytes_transmitted'] += (int) $item['bytes_transmitted'];
    }
    $summaries = array_values($summarizedData);
    return concat($summaries, $data);
}


function concat($summaries, $data)
{
    $combinedData = [];
    foreach ($summaries as $summary) {
        $key = $summary['tenant'] . '|' . $summary['date'];
        $combinedData[$key] = [
            'tenant' => $summary['tenant'],
            'date' => $summary['date'],
            'total_bytes_transmitted' => $summary['total_bytes_transmitted'],
            'stream_servers' => []
        ];
    }
    foreach ($data as $item) {
        $key = $item['tenant'] . '|' . $item['date'];
        if (isset($combinedData[$key])) {
            $combinedData[$key]['stream_servers'][] = $item;
        }
    }
    $result = array_values($combinedData);
    return $result;
}


function connectQUeue()
{
    try {
        $connection = new AMQPStreamConnection(env('RABBITMQ_HOST'), env('RABBITMQ_PORT'), env('RABBITMQ_USER'), env('RABBITMQ_PASSWORD'), env('RABBITMQ_VHOST'));
    } catch (\Exception $e) {
        $connection = new AMQPStreamConnection(env('RABBITMQ2_HOST'), env('RABBITMQ_PORT'), env('RABBITMQ_USER'), env('RABBITMQ_PASSWORD'), env('RABBITMQ_VHOST'));
    }
    return $connection->channel(1);
}

function makeRequestCode()
{
    $requestCode = '';
    do {
        $requestCode = uuid_create();
        $cdnSetup = DB::table('cdn_resources')->select('request_code')->where('request_code', $requestCode)->first();
    } while ($cdnSetup !== null);
    return $requestCode;
}


function extractTxtValue($log)
{

    // Tentando extrair o valor do TXT do log sem as aspas simples
    if (preg_match("/TXT value: ([^\s]+)/", $log, $matches)) {
        // Se encontrado, retorna o valor do TXT em um array
        return ['txt_value' => $matches[1], 'code' => 200];
    } else if (strpos($log, 'Cert success.') !== false) {
        return ['code' => 202];
    } else {

        // Caso não encontre, tenta extrair uma mensagem de erro
        $message = extractMessage($log);
        // Se não conseguir extrair a mensagem de erro, define uma mensagem padrão
        $message = is_null($message) ? "unknown error in acme request for TXT input" : $message;
        // Retorna null para o valor TXT e a mensagem de erro
        return ['code' => 400, 'message' => $message];
    }
}


function extractMessage($log)
{
    // Verifica se a string "Domains not changed." existe
    if (strpos($log, 'Domains not changed.') !== false) {
        $array = explode("\n", $log);
        for ($i = 0; $i < count($array); $i++) {
            if (strpos($array[$i], 'Domains not changed')) {
                return trim(preg_replace('/\[[^\]]*\]\s*/', '', $array[$i + 1]));
            }
        }

    }
    if (strpos($log, 'Error creating new order.') !== false) {
        $array = explode("\n", $log);
        for ($i = 0; $i < count($array); $i++) {
            if (strpos($array[$i], 'detail')) {
                $string =  trim(preg_replace('/\[[^\]]*\]\s*/', '', $array[$i]));
                return explode(":",$string)[1];
            }
        }
        if (preg_match("/\"detail\": ([^\s]+)/", $log, $matches)) {
            return $matches[1];
        }
    }

    if (strpos($log, 'Cert success.') !== false) {
        return 'cert_found';
    }
    return null; // Retorna null caso a string não seja encontrada
}
function CAACheck($host)
{
    $isLets = 0;
    $hostMaster = "";
    $check = dns_get_record($host, DNS_CAA);
    if (empty($check)) {
        $hostMaster = explode('.', $host);
        unset($hostMaster[0]);
        $checkMaster = dns_get_record(implode(".", $hostMaster), DNS_CAA);
        if (!empty($checkMaster)) {
            foreach ($checkMaster as $regiter) {
                if ($regiter['value'] == "letsencrypt.org") {
                    $isLets++;
                }
            }
            if ($isLets == 0) {
                return false;
            } else {
                return true;
            }
        } else {
            return true;
        }
    } else {
        foreach ($check as $regiter) {
            if ($regiter['value'] == "letsencrypt.org") {
                $isLets++;
            }
        }
    }
    if ($isLets == 0) {
        return false;
    } else {
        return true;
    }
}

function CNAMEValidate($host, $cname)
{
    $dnsRecords = dns_get_record($host, DNS_CNAME);
    if (empty($dnsRecords)) {
        return false;
    }
    foreach ($dnsRecords as $record) {
        if (isset($record['target']) && $record['target'] === $cname) {
            return true;
        }
    }
    return false;
}

function cname()
{
    return DB::table('cdn_cnames')->whereNull('cdn_resource_id')->first();
}

function cnameById($cnameId)
{
    $cname = DB::table('cdn_cnames')->find($cnameId);
    return $cname->cname;
}


function getTired($tenant)
{
    // Define listas de vogais e inicializa arrays para armazenar consoantes e vogais encontradas
    $vogais = ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U'];
    $consoantes = [];
    $vogais_encontradas = [];

    // Itera sobre cada caractere na tenant
    for ($i = 0; $i < strlen($tenant); $i++) {
        $char = $tenant[$i];

        // Verifica se o caractere é uma letra
        if (ctype_alpha($char)) {
            // Adiciona à lista de consoantes ou de vogais
            if (!in_array($char, $vogais)) {
                $consoantes[] = $char;
            } else {
                $vogais_encontradas[] = $char;
            }
        }
    }

    // Verifica o número de consoantes encontradas e retorna conforme a lógica definida
    $num_consoantes = count($consoantes);

    if ($num_consoantes == 3) {
        return implode('', $consoantes);
    } elseif ($num_consoantes < 3) {
        $resultado = array_merge($consoantes, array_slice($vogais_encontradas, 0, 3 - $num_consoantes));
        return implode('', $resultado);
    } else {
        // Mais que 3 consoantes: retorna as duas primeiras e a última
        return $consoantes[0] . $consoantes[1] . end($consoantes);
    }
}

function makeAccountName($nameBase)
{

    $string = getTired($nameBase);
    $strtoint = [];
    foreach (str_split($string) as $str) {
        $strtoint[] = ord(strtoupper($str)) - ord('A') + 1;
    }

    // Gera uma variável com 4 algarismos de 0 a 9
    $integers = '';
    for ($i = 0; $i < 4; $i++) {
        $integers .= rand(0, 9);
    }

    // Concatena os números das letras e os integers
    $joins = implode('', $strtoint) . $integers;
    // Calcula o resto das somas sucessivas
    $a = array_sum(str_split($joins)) % 7;
    $b = array_sum(str_split(substr($joins, 1))) % 7;
    $c = array_sum(str_split(substr($joins, 2))) % 7;
    $d = array_sum(str_split(substr($joins, 3))) % 7;

    // Concatena a string original com os restos calculados
    $result = $string . $a . $b . $c . $d;

    return $result;
}


function makePassword()
{
    // Define o conjunto de caracteres possíveis
    $base = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789*&%#@!<>()';

    // Inicializa a string vazia
    $randonStr = '';

    // Gera uma string de 12 caracteres aleatórios
    for ($i = 0; $i < 12; $i++) {
        $randonIndex = rand(0, strlen($base) - 1);
        $randonStr .= $base[$randonIndex];
    }

    return $randonStr;
}


function nextTenantOrUser($str, $type)
{
    if (preg_match('/(.*?)([TU])(\d+)$/i', $str, $matches)) {
        $prefix = $matches[1];
        $control = strtolower($matches[2]);
        $number = $matches[3];
        if ($control === strtolower($type)) {
            $incrementedNumber = str_pad((int) $number + 1, strlen($number), '0', STR_PAD_LEFT);
            return $prefix . $control . $incrementedNumber;
        }
    }
    return $str . strtolower($type) . '001';

}


/**
 * Função para validar se todas as chaves necessárias estão presentes em $data.
 *
 * @param string $jsonString A string JSON contendo placeholders.
 * @param array $data O array associativo com os valores reais.
 * @return array Um array com as chaves faltantes ou uma mensagem de sucesso.
 */
function validateJsonPlaceholders(string $jsonString, array $data): array
{
    // Encontra todos os placeholders no JSON
    $placeholders = findPlaceholders($jsonString);
    // Remove duplicatas
    $placeholders = array_unique($placeholders);

    // Verifica se todas as chaves estão presentes no array $data
    $missingKeys = array_diff($placeholders, array_keys($data));

    Log::info("template -> " . json_encode($placeholders) . " Formulário -> " . json_encode($data));


    if (!empty($missingKeys)) {
        return [
            'error' => 'Missing required keys in data array.',
            'missing_keys' => $missingKeys
        ];
    }

    return [
        'status' => 'success',
        'message' => 'All required keys are present in data array.'
    ];
}

/**
 * Função para converter JSON em array e substituir placeholders.
 *
 * @param string $template Json do template.
 * @param array $data O array associativo com os valores reais.
 * @return array O array com os placeholders substituídos ou uma mensagem de erro.
 */
function jsonToArrayWithReplacements($jsonString, $data): array
{

    $data['cdn_resource_hostname'] = str_replace('.', '_', $data['cdn_resource_hostname']);

    // Valida os placeholders no JSON com o array $data
    $validationResult = validateJsonPlaceholders($jsonString, $data);
    if (isset($validationResult['error'])) {
        return $validationResult;
    }

    // Converte a string JSON para um array
    $array = json_decode($jsonString, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \InvalidArgumentException("Invalid JSON string: " . json_last_error_msg());
    }
    // Substitui os placeholders no array
    return replacePlaceholders($array, $data);
}


/**
 * Função para encontrar todos os placeholders em uma string.
 *
 * @param string $jsonString A string JSON contendo placeholders.
 * @return array Um array de placeholders encontrados.
 */
function findPlaceholders(string $jsonString): array
{
    preg_match_all("/\\\$data\['(.*?)'\]/", $jsonString, $matches);
    return $matches[1];
}

/**
 * Função para substituir placeholders no array com valores reais.
 *
 * @param mixed $value O valor a ser processado.
 * @param array $data O array associativo com os valores reais.
 * @return mixed O valor com os placeholders substituídos.
 */

function replacePlaceholders($value, array $data)
{
    if (is_array($value)) {
        foreach ($value as $key => &$subValue) {
            $subValue = replacePlaceholders($subValue, $data);
        }
    } elseif (is_string($value)) {
        $value = preg_replace_callback(
            "/\\\$data\['(.*?)'\]/",
            function ($matches) use ($data) {
                $key = $matches[1];
                // Verifica se o valor existe no array de dados
                if (isset($data[$key])) {
                    return is_array($data[$key])
                        ? json_encode($data[$key]) // Converte arrays para JSON
                        : (is_bool($data[$key])
                            ? ($data[$key] ? 'true' : 'false')
                            : strval($data[$key]));
                }
                return $matches[0]; // Retorna o valor original se não houver correspondência
            },
            $value
        );
    }

    return $value;
}


function serverGroupsDeclare(array $serverGroups)
{

    $result = "";

    foreach ($serverGroups as $serverGroup) {
        $result .= $serverGroup['name'] . ",";
    }
    return explode(",", substr($result, 0, -1));
}

function serverGroups(array $data, string $groupName): array
{
    $result = [];
    $groupIndex = 1; // Para manter o índice do grupo

    foreach ($data as $resourceOriginGroup) {
        if (!isset($resourceOriginGroup['origin_group']['origin_servers']) || !is_array($resourceOriginGroup['origin_group']['origin_servers'])) {
            continue; // Ignora se não existir ou não for um array
        }

        $nodes = [];
        foreach ($resourceOriginGroup['origin_group']['origin_servers'] as $index => $originServer) {
            $nodes[] = [
                "https" => [
                    "caFile" => "",
                    "caPath" => "/etc/ssl/certs",
                    "enable" => $originServer['cdn_origin_protocol'] === 'https',
                    "http2" => false,
                    "verifyPeer" => false
                ],
                "id" => $index,
                "tcpAddresses" => [
                    $originServer['cdn_origin_hostname'] . ':' . $originServer['cdn_origin_server_port']
                ]
            ];
        }

        $result[] = [
            "name" => $groupName . '_' . $groupIndex,
            "nodes" => $nodes,
        ];

        $groupIndex++;
    }

    return $result;
}

function transformDataWithPlaceholders($value, array $data)
{
    // Substitui placeholders no array inicial
    $value = replacePlaceholders($value, $data);

    if (isset($data['custon_headers']) && is_array($data['custon_headers'])) {
        $custon = [];
        foreach ($data['custon_headers'] as $custonHeader) {
            $custon[] = [
                "name" => $custonHeader['name'],
                "value" => $custonHeader['value']
            ];
        }
        $value['services']['webtv']['origin']['backends'][0]['customHeaders'] = $custon;
    }

    // Adiciona lógica adicional para ajustar "serverGroups" no formato esperado
    if (isset($data['server_groups']) && is_array($data['server_groups'])) {
        $value['services']['webtv']['origin']['serverGroups'] = [];

        foreach ($data['server_groups'] as $group) {
            $serverGroup = [
                "capMaxAge" => 0,
                "connectTimeout" => 0,
                "minBitrate" => 0,
                'name' => $group['name'],
                'nodes' => [],
                "numRetry" => 0,
                "peerMode" => false,
                "readTimeout" => 0,
                "spreadFactor" => 0,
                "timeout" => 1250

            ];

            foreach ($group['nodes'] as $node) {
                $serverGroup['nodes'][] = [
                    'https' => $node['https'],
                    'id' => $node['id'],
                    'tcpAddresses' => $node['tcpAddresses']
                ];
            }

            $value['services']['webtv']['origin']['serverGroups'][] = $serverGroup;
        }
    }

    $value['services']['webtv']['origin']['backends'][0]['serverGroups'] = $data['server_groups_declare'];
    $value['services']['webtv']['origin']['backends'][0]['forwardHeaders'] = $data['forward_headers'];

    return $value;

}



function getIdGroupServer($groups)
{
    $response = [];
    foreach ($groups as $group) {
        $response[] = $group['cdn_origin_group_id'];
    }
    return $response;
}

function getHeader($request)
{

    $server = $request->server();
    $data['token'] = $request->bearerToken();
    $data['remote_address'] = $server['REMOTE_ADDR'];

    return $data;
}


function sslEnviroment()
{
    if (env('SSL_ENVIROMENT') == 'prod') {
        return 'MODE_LIVE';
    } else {
        return 'MODE_STAGING';
    }
}


function formatBytes($bytes, $format)
{
    switch (strtoupper($format)) {
        case 'T':
            return round($bytes / 1024000000000, 2) . ' Tbytes';
        case 'G':
            return round($bytes / 1024000000, 2) . ' Gbytes';
        case 'M':
            return round($bytes / 1024000, 2) . ' Mbytes';
        case 'K':
            return round($bytes / 1024, 2) . ' Kbytes';
        default:
            return $bytes . ' bytes';
    }
}



function validateDateInterval($data, $graph)
{
    $start = new DateTime($data['start_date']);
    $end = new DateTime($data['end_date']);

    if ($graph != "monthly") {
        // Calcula a diferença em minutos
        $interval = $start->diff($end);
        $totalMinutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

        if ($graph == "fiveMinutes") {
            // Verifica se o intervalo é menor que 30 minutos ou se start_date é maior que end_date
            if ($start > $end || $totalMinutes < 30) {
                return ['error' => "The start date and time must be at least 30 minutes before the end date and time "];
            }
            // Verifica se o intervalo é menor que 120 minutos ou se start_date é maior que end_date
        } else if ($graph == "daily") {
            if ($start > $end || $totalMinutes < 120) {
                return ['error' => "The start date and time must be at least 2 hours before the end date and time "];
            }
        }
        return [];
    } else {
        $interval = $start->diff($end);
        $days = $interval->days;
        if ($start >= $end || $days < 1) {
            return ['error' => "Invalid period selected, start date must be less than end date"];
        }

        return [];

    }

}


function checkRabbitMQConnection($host)
{
    try {
        $port = env("RABBITMQ_PORT");
        $user = env("RABBITMQ_USER");
        $password = env("RABBITMQ_PASSWORD");
        $vhost = env("RABBITMQ_VHOST");

        $connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
        if ($connection->isConnected()) {
            $connection->close();
            return "Conexão com RabbitMQ em {$host}:{$port} foi bem-sucedida.";
        } else {
            return "Não foi possível estabelecer uma conexão com RabbitMQ.";
        }
    } catch (AMQPConnectionClosedException $e) {
        return "Erro de conexão: " . $e->getMessage();
    } catch (AMQPIOException $e) {
        return "Erro de I/O: " . $e->getMessage();
    } catch (Exception $e) {
        return "Erro geral: " . $e->getMessage();
    }
}


/**
 * Função responsável por verificar o tipo do certificado SSL
 * @param string $certificatePath (patch do arquivo SSL - Padrão storage/certs/<domain>.crt)
 * @param string $domain
 *
 * @return //(se certificado estiver atrubuido ao um windcard retorna "WILDCARD" caso contrario retorna "SIMPLE")
 *           (caso haja uma exceção na função retorna um array com a descrição do erro)
 *
 */

function sslType($certificatePath, $domain)
{
    try {
        // Carregar o certificado
        $certificateData = file_get_contents($certificatePath);

        $certInfo = openssl_x509_parse($certificateData);

        // Extrair os domínios permitidos
        if (isset($certInfo['extensions']['subjectAltName'])) {
            $subjectAltNames = explode(', ', $certInfo['extensions']['subjectAltName']);
            foreach ($subjectAltNames as $name) {
                if (strpos($name, 'DNS:') === 0) {
                    $domains[] = substr($name, 4); // Remover o prefixo 'DNS:'
                }
            }
        }
        // Verificar correspondência com wildcard
        foreach ($domains as $allowedDomain) {
            if (strpos($allowedDomain, '*.') === 0) {
                // Verificar se o domínio combina com o wildcard
                $pattern = '/^' . str_replace('\*', '[^.]+', preg_quote($allowedDomain, '/')) . '$/';
                if (preg_match($pattern, $domain)) {
                    return "WILDCARD";
                }
            } elseif ($allowedDomain === $domain) {
                return "SIMPLE"; // Domínio exato
            } elseif (($allowedDomain !== $domain)) {
                return ['errors' => "This SSL certificate does not belong to the domain."];
            } else {
                return ['errors' => "SSL certificate is not valid, check the contents of the .key and .crt files."];

            }
        }
    } catch (\Exception $e) {
        return ['errors' => $e->getMessage()];
    }

}


/**
 * função responsável por verificar se a diferença entre resource persistido e a chamada de update
 * Caso a camada for apenas para alterar a descrição retorna true
 *
 */

function descriptionOnly($payload, $cdnResource)
{

    // Converte o registro do banco de dados para array
    $resourceData = $cdnResource->toArray();

    $persisted = [
        'cdn_resource_hostname' => $cdnResource->cdn_resource_hostname,
        'cdn_origin_hostname' => $cdnResource->cdn_origin_hostname,
        'cdn_origin_server_port' => $cdnResource->cdn_origin_server_port,
        'cdn_origin_protocol' => $cdnResource->cdn_origin_protocol,
        'cdn_ingest_point_id' => $cdnResource->cdn_ingest_point_id,
        'cdn_target_group_id' => $cdnResource->cdn_target_group_id,
        'cdn_template_id' => $cdnResource->cdn_template_id
    ];

    // Remove o campo 'description' de ambos para comparar o restante
    $payloadWithoutDescription = $payload;
    $resourceDataWithoutDescription = $persisted;

    unset($payloadWithoutDescription['description']);

    // Verifica se os demais campos são iguais
    $isEqual = $payloadWithoutDescription == $resourceDataWithoutDescription;

    // Verifica se a única diferença está no campo 'description'
    return $isEqual && $payload['description'] !== $resourceData['description'];
}


function convertToNull($value)
{
    return $value === "null" ? null : $value;
}


