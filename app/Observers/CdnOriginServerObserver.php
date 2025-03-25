<?php

namespace App\Observers;

use App\Models\CdnOriginServer;
use App\Models\CdnResource;
use App\Models\CdnResourceOriginGroup;
use App\Repositories\DeprovisioningRepository;
use Illuminate\Support\Facades\Log;

class CdnOriginServerObserver
{
    /**
     * Handle the CdnOriginServer "created" event.
     *
     * @param  \App\Models\CdnOriginServer  $cdnOriginServer
     * @return void
     */
    public function created(CdnOriginServer $cdnOriginServer)
    {
        $cdnResourceOriginGroups  = CdnResourceOriginGroup::where('cdn_origin_group_id', $cdnOriginServer->cdn_origin_group_id)->get();

        if($cdnResourceOriginGroups) {
            foreach($cdnResourceOriginGroups as $cdnResourceOriginGroup){
                $cdnResource = CdnResource::find($cdnResourceOriginGroup->cdn_resource_id);
                $data = ['request_code' => $cdnResource->request_code];
                $updateGroup = isset($cdnOriginServer->type) == 'main' ? null : "x";
                Log::info("CDNORIGINSERVEROBSERVER  CREATE : ". json_encode( $cdnOriginServer));

                $deprovisioningRepository =  new DeprovisioningRepository();
                $deprovisioningRepository->deleteCdnTemplate($data, $updateGroup);
            }
        }
    }

    /**
     * Handle the CdnOriginServer "updated" event.
     *
     * @param  \App\Models\CdnOriginServer  $cdnOriginServer
     * @return void
     */
    public function updated(CdnOriginServer $cdnOriginServer)
    {
        $cdnResourceOriginGroups  = CdnResourceOriginGroup::where('cdn_origin_group_id', $cdnOriginServer->cdn_origin_group_id)->get();
        if($cdnResourceOriginGroups) {
            foreach($cdnResourceOriginGroups as $cdnResourceOriginGroup){
                $cdnResource = CdnResource::find($cdnResourceOriginGroup->cdn_resource_id);
                    $updated = $cdnOriginServer->getChanges();
                Log::info("CDNORIGINSERVEROBSERVER UPDATE : ". json_encode($updated));
                $updateGroup = isset($updated['type']) ? null : "x";
                $data = ['request_code' => $cdnResource->request_code];
                $deprovisioningRepository =  new DeprovisioningRepository();
                $deprovisioningRepository->deleteCdnTemplate($data, $updateGroup);
            }
        }
    }

    /**
     * Handle the CdnOriginServer "deleted" event.
     *
     * @param  \App\Models\CdnOriginServer  $cdnOriginServer
     * @return void
     */
    public function deleted(CdnOriginServer $cdnOriginServer)
    {
        $cdnResources  = CdnResource::where('cdn_origin_group_id', $cdnOriginServer->cdn_origin_group_id)->get();
        if($cdnResources) {
            foreach($cdnResources as $cdnResource){
                $data = ['request_code' => $cdnResource->request_code];
                $deprovisioningRepository =  new DeprovisioningRepository();
                $deprovisioningRepository->deleteCdnTemplate($data, "X");
            }
        }
    }

}
