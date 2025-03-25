<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log as FacadesLog;
use Exception;

class EventLogService
{

    /**
     * Convert error types (strings to integers)
     *
     * @param string $type
     *
     * @return integer $key
     *
     */
    public function typeError($type)
    {
        $psrLevels = array(
            'EMERGENCY',    // 0
            'ALERT',        // 1
            'CRITICAL',     // 2
            'ERROR',        // 3
            'WARNING',      // 4
            'NOTICE',       // 5
            'INFO',         // 6
            'DEBUG'         // 7
        );

        $key = array_search($type, $psrLevels);
        if (!isset($key)) {
            $key = 6;
        }
        return $key;
    }


    /**
     * Metodo resposavel por salvar log Syslog no servidor
     *
     * @param $message
     * @param null $fullMessage
     * @param null $type
     * @param null $facility
     */

    public function syslog($message, $fullMessage = null, $type = 'info', $facility = null)
    {
        $hostname = env('APP_HOSTNAME');
        $level = $this->typeError(strtoupper($type));
        $appName = env('APP_NAME');
        //$ip_address = $_SERVER['REMOTE_ADDR'];
        $ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "";

        $data = [
            'json' => [
                "short_message" => $message,
                "full_message" => $fullMessage,
                "host" => $hostname,
                "level" => $level,
                "facility" => $facility,
                "application_name" => $appName,
                "ip"=> $ip_address,
            ]
        ];

        try {
            switch ($type) {
                case 'INFO':
                    openlog(ENV("LOG_NAME_SYSLOG"), LOG_PID | LOG_ODELAY, 160);
                    syslog(LOG_INFO, json_encode($data));
                    closelog();
                    break;
                case  'WARNING':
                    openlog(ENV("LOG_NAME_SYSLOG"), LOG_PID | LOG_ODELAY, 160);
                    syslog(LOG_WARNING, json_encode($data));
                    closelog();
                    break;
                case 'ERROR':
                    openlog(ENV("LOG_NAME_SYSLOG"), LOG_PID | LOG_ODELAY, 160);
                    syslog(LOG_ERR, json_encode($data));
                    closelog();
                    break;
                case 'EMERGENCY':
                    openlog(ENV("LOG_NAME_SYSLOG"), LOG_PID | LOG_ODELAY, 160);
                    syslog(LOG_EMERG, json_encode($data));
                    closelog();
                    break;
                case 'ALERT':
                    openlog(ENV("LOG_NAME_SYSLOG"), LOG_PID | LOG_ODELAY, 160);
                    syslog(LOG_ALERT, json_encode($data));
                    closelog();
                    break;
                case 'CRITICAL':
                    openlog(ENV("LOG_NAME_SYSLOG"), LOG_PID | LOG_ODELAY, 160);
                    syslog(LOG_CRIT, json_encode($data));
                    closelog();
                    break;
                case 'NOTICE':
                    openlog(ENV("LOG_NAME_SYSLOG"), LOG_PID | LOG_ODELAY, 160);
                    syslog(LOG_NOTICE, json_encode($data));
                    closelog();
                    break;
                case 'DEBUG':
                    openlog(ENV("LOG_NAME_SYSLOG"), LOG_PID | LOG_ODELAY, 160);
                    syslog(LOG_DEBUG, json_encode($data));
                    closelog();
                    break;
            }
        } catch (\Exception $e) {
            FacadesLog::alert('[Exception Syslog] : Erro ao executar gravação de log no syslog.'  . ' Mensagem : ' . $message);
        }

    }

    public function auditLog($header, $action,  $type, $message, $payload, $response) {


    }

}
