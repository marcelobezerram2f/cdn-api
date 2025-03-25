<?php

namespace App\Console\Commands;

use App\Repositories\BillingRepository;
use Illuminate\Console\Command;
use App\Services\EventLogService;
use Exception;

class SummaryDayRequest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'summary:request {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private $billingRepository;
    private $logSys;
    protected $facilityLog;
    /**
     * Execute the console command.
     *
     * @return int
     */

    public function __construct()
    {
        parent::__construct();
        $this->billingRepository = new BillingRepository();
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
    }
    public function handle()
    {
        $this->logSys->syslog(
            "[CDN-API | Cron Request Summary] Iniciando a requisição de sumarização de telemetria do dia ".date('Y-m-d'),
            null,
            "INFO",
            $this->facilityLog . ':' . basename(__FUNCTION__)
        );

        $date = $this->argument('date');
        if(is_null($date)) {
            $request = ['start_date' => date('Y-m-d', strtotime('-1 days '.date('Y-m-d')))];
        } else {
            $request = ['start_date' => $date];
        }


        $this->billingRepository->saveSummarized($request);

        $this->logSys->syslog(
            "[CDN-API | Cron Request Summary] Fim do processo de coleta da sumarização de telemetria do dia ".date('Y-m-d'),
            null,
            "INFO",
            $this->facilityLog . ':' . basename(__FUNCTION__)
        );
    }
}
