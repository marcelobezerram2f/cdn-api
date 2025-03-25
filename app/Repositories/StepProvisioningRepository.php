<?php

namespace App\Repositories;

use App\Models\CdnProvisioningStep;
use App\Services\EventLogService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StepProvisioningRepository
{

    private $cdnProvisioningStep;
    private $logSys;
    protected $facilityLog;




    public function __construct()
    {
        $this->cdnProvisioningStep = new CdnProvisioningStep();
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
    }

    /**
     * step 1 - Validation CNAME -> com os possíveis status : waiting, failed, finish ;
     * step 2 - Creating the Tenant  -> com os possíveis status : pending, waiting, failed, finish ;
     * step 3 - Creating CDN Resource -> com os possíveis status : pending, waiting, failed, finish ;
     * step 4 - Copy New CDN Template JSON -> com os possíveis status : pending, waiting, failed, finish ;
     * step 5 - Create CDN Resource Route ->  com os possíveis status : pending, waiting, failed, finish ;
     * step 6 - Finalizing the Provisioning Process -> com os possíveis status : pending, waiting, failed, finish.
     */

    public function createSteps($referenceId, $status, $observation, $sslCuston = null, $letsencryptSSL = null)
    {
        try {

            /**
             * Definição des passos quando provisionado do Cdn resource
             */
            $sslSteps = [];
            if (!is_null($letsencryptSSL)) {
                $caa = [
                    'cdn_resource_id' => $referenceId,
                    'step' => 6,
                    'step_description' => "Let's Encrypt SSL CAA entry validation",
                    'status' => 'pending',
                    'observation' => null
                ];
                $cname = [
                    'cdn_resource_id' => $referenceId,
                    'step' => 7,
                    'step_description' => "Let's Encrypt SSL CNAME entry validation",
                    'status' => 'pending',
                    'observation' => null
                ];
                $install = [
                    'cdn_resource_id' => $referenceId,
                    'step' => 8,
                    'step_description' => "Installation of the Let's Encrypt SSL certificate",
                    'status' => 'pending',
                    'observation' => null
                ];

                array_push($sslSteps, $cname);
                array_push($sslSteps, $caa);
                array_push($sslSteps, $install);
            }

            if (!is_null($sslCuston)) {
                $validaton = [
                    'cdn_resource_id' => $referenceId,
                    'step' => 7,
                    'step_description' => "Customized SSL certificate validation",
                    'status' => 'pending',
                    'observation' => $observation
                ];
                $install = [
                    'cdn_resource_id' => $referenceId,
                    'step' => 8,
                    'step_description' => "Installation of the custon SSL certificate",
                    'status' => 'pending',
                    'observation' => null
                ];
                array_push($sslSteps, $validaton);
                array_push($sslSteps, $install);
            }

            $steps = [
                [
                    'cdn_resource_id' => $referenceId,
                    'step' => 1,
                    'step_description' => 'Validation CNAME',
                    'status' => $status,
                    'observation' => $observation
                ],
                [
                    'cdn_resource_id' => $referenceId,
                    'step' => 3,
                    'step_description' => 'Creating CDN Resource',
                    'status' => 'pending',
                    'observation' => null
                ],
                [
                    'cdn_resource_id' => $referenceId,
                    'step' => 4,
                    'step_description' => 'Copy New CDN Template JSON',
                    'status' => 'pending',
                    'observation' => null
                ],
                [
                    'cdn_resource_id' => $referenceId,
                    'step' => 5,
                    'step_description' => 'Create CDN Resource Route',
                    'status' => 'pending',
                    'observation' => null
                ],
            ];
            if (!empty($sslSteps)) {
                foreach ($sslSteps as $sslStep) {
                    array_push($steps, $sslStep);
                }
            }

            foreach ($steps as $step) {
                if (!is_null($step) || !empty($step)) {
                    $this->cdnProvisioningStep->create($step);
                }
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Step Provisioning] Ocorreu uma falha na criação de status de fase de provisinamento de CDN',
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: config ID :' . $referenceId . ' status :' . $status . ' Observação: ' . $observation,
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }

    public function createStep($referenceId, $stepNumber, $description, $status, $observation): void
    {
        $step = [
            'cdn_resource_id' => $referenceId,
            'step' => $stepNumber,
            'step_description' => $description,
            'status' => $status,
            'observation' => $observation
        ];
        $this->cdnProvisioningStep->updateOrCreate(['cdn_resource_id' => $referenceId, 'step' => $stepNumber], $step);

    }

    public function updateStep($referenceId, $stepNumber, $status, $observation)
    {

        try {
            Log::info($referenceId . " - " . $stepNumber . " - " . $status. " - ".$observation);
            $step = $this->cdnProvisioningStep->where('cdn_resource_id', $referenceId)
                ->where('step', $stepNumber)
                ->first();
            if (!is_null(value: $step)) {
                $step->status = $status;
                $step->observation = $observation;
                $step->save();
            }
            return $step;
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Step Provisioning] Ocorreu uma excecão na alteração de status de fase de provisinamento de CDN',
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: config ID :' . $referenceId . ' numero da fase :' . $stepNumber . ' status :' . $status . ' Observação: ' . $observation,
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return $e->getMessage();
        }
    }


    public function getStepsByResource($cdnResourceId, $cdnResource, $tenant)
    {

        try {
            $steps = $this->cdnProvisioningStep->where('cdn_resource_id', $cdnResourceId)->get();
            $response = [];
            foreach ($steps as $step) {
                $data = [
                    'step_description' => $step->step_description,
                    'status' => $step->status,
                    'observation' => $step->observation
                ];
                array_push($response, $data);
                unset($data);
            }
            $response['code'] = 200;
            $this->logSys->syslog(
                "[CDN-API | Steps Provisioning] Coleta dos status de provisionamento do cdn resource $cdnResource do tenant $tenant efetuada com sucesso.",
                null,
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        } catch (Exception $e) {

            $this->logSys->syslog(
                '[CDN-API | Step Provisioning] Ocorreu uma excecão na coleta de status de fase de provisinamento de CDN',
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: cdn resource :' . $cdnResource . ' tenant :' . $tenant,
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

            $response = ['code' => 400];
        }
        return $response;
    }


    public function deleteStep($cdnResourceId, $stepNumber)
    {
        try {
            $step = $this->cdnProvisioningStep->where('cdn_resource_id', $cdnResourceId)->where('step', $stepNumber)->first();
            if ($step) {
                $step->delete();
                $this->logSys->syslog(
                    "[CDN-API | Step Delete] Exclusão de status de update do cdn resource efetuada com sucesso!",
                    null,
                    'ERROR',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | Step Delete] Ocorreu uma exceção na exclusão de status de update do cdn resource",
                'ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }

    }

    public function deleteSteps($cdnResourceId, $cdnResource, $tenant)
    {
        try {
            $steps = $this->cdnProvisioningStep->where('cdn_resource_id', $cdnResourceId)->get();
            $stepsArray = $steps->toArray();
            foreach ($steps as $step) {
                $deleteStep = $this->cdnProvisioningStep->find($step->id);
                $deleteStep->delete();
            }
            $this->logSys->syslog(
                "[CDN-API | Steps Provisioning] Exclusão dos status de provisionamento do cdn resource $cdnResource do tenant $tenant, efetuado com sucesso.",
                'Dados Excluidos : ' . json_encode($stepsArray),
                'INFO',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = ['code' => 200];

        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | Steps Provisioning] Ocorreu uma exceção na exclusão dos status de provisionamento do cdn resource $cdnResource do tenant $tenant.",
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: Resource :' . $cdnResource . ' tenant :' . $tenant,
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = ['code' => 400];

        }
        return $response;
    }


    public function blockUnblock($data)
    {

        $this->cdnProvisioningStep->create($data);

    }


    public function deletionSteps($resourceId, $status, $observation, $ssl = null)
    {

        try {
            $sslStep = [];
            if (!is_null($ssl)) {
                $sslStep = [
                    'cdn_resource_id' => $resourceId,
                    'step' => 1,
                    'step_description' => 'Deleting SSL Certificate',
                    'status' => $status,
                    'observation' => 'waiting start deletion process'
                ];
            }
            $steps = [
                $sslStep,
                [
                    'cdn_resource_id' => $resourceId,
                    'step' => is_null($ssl) ? 1 : 2,
                    'step_description' => 'Removing route register',
                    'status' => "pending",
                    'observation' => null
                ],
                [
                    'cdn_resource_id' => $resourceId,
                    'step' => is_null($ssl) ? 2 : 3,
                    'step_description' => 'Removing template config file',
                    'status' => "pending",
                    'observation' => null
                ],
                [
                    'cdn_resource_id' => $resourceId,
                    'step' => is_null($ssl) ? 3 : 4,
                    'step_description' => 'Deleting cdn resource',
                    'status' => "pending",
                    'observation' => null
                ],
                [
                    'cdn_resource_id' => $resourceId,
                    'step' => 5,
                    'step_description' => 'Checking the CDN Resource on our servers ',
                    'status' => "waiting",
                    'observation' => 'If provisioning has not been completed, this record will be deleted without deleting the route and template  '
                ]
            ];

            foreach ($steps as $step) {
                if (!empty($step)) {
                    $this->cdnProvisioningStep->create($step);
                }
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Step Provisioning] Ocorreu uma falha na criação de status de fase de exclusão de CDN',
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: config ID :' . $resourceId . ' status :' . $status . ' Observação: ' . $observation,
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }

    public function createSLLSteps($resourceId, $certType)
    {
        $this->deleteSSLSteps($resourceId);

        $sslSteps = [];
        if ($certType == 'letsencrypt') {
            $sslSteps = [
                [
                    'cdn_resource_id' => $resourceId,
                    'step' => 6,
                    'step_description' => "Let's Encrypt SSL CAA entry validation",
                    'status' => 'pending',
                    'observation' => null
                ],
                [
                    'cdn_resource_id' => $resourceId,
                    'step' => 7,
                    'step_description' => "Let's Encrypt SSL CNAME entry validation",
                    'status' => 'pending',
                    'observation' => null
                ],
                [
                    'cdn_resource_id' => $resourceId,
                    'step' => 8,
                    'step_description' => "Installation of the Let's Encrypt SSL certificate",
                    'status' => 'pending',
                    'observation' => null
                ],
            ];

        }

        if ($certType == 'custon') {
            $sslSteps = [
                [
                    'cdn_resource_id' => $resourceId,
                    'step' => 7,
                    'step_description' => "Customized SSL certificate validation",
                    'status' => 'pending',
                    'observation' => null
                ],
                [
                    'cdn_resource_id' => $resourceId,
                    'step' => 8,
                    'step_description' => "Installation of the custon SSL certificate",
                    'status' => 'pending',
                    'observation' => null
                ]
            ];
        }

        if ($certType == 'uninstall') {
            $sslSteps = [
                [
                    'cdn_resource_id' => $resourceId,
                    'step' => 7,
                    'step_description' => "Uninstalling the SSL certificate on CDN servers",
                    'status' => 'pending',
                    'observation' => null
                ],
                [
                    'cdn_resource_id' => $resourceId,
                    'step' => 8,
                    'step_description' => "Deleting the SSL certificate record",
                    'status' => 'pending',
                    'observation' => null
                ]
            ];
        }
        foreach ($sslSteps as $sslStep) {
            $this->cdnProvisioningStep->create($sslStep);
        }
    }


    public function deleteSSLSteps($resourceId)
    {
        $steps = $this->cdnProvisioningStep->where('cdn_resource_id', $resourceId)
            ->whereIn('step', [6, 7, 8])->get();
        if ($steps) {
            foreach($steps as $step) {
                $deleteStep  =  $this->cdnProvisioningStep->find($step->id);
                $deleteStep->delete();
            }
        }

    }


}
