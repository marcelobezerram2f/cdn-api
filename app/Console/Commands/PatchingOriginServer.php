<?php

namespace App\Console\Commands;

use App\Models\CdnOriginGroup;
use App\Models\CdnOriginServer;
use App\Models\CdnResource;
use App\Models\CdnResourceOriginGroup;
use Illuminate\Console\Command;

class PatchingOriginServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'resource:patch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $resources = CdnResource::whereNotNull('cdn_origin_hostname')->get();

        foreach ($resources as $resource) {
            $group = CdnOriginGroup::create([
                'group_name' => $resource->cdn_resource_hostname,
                'cdn_tenant_id' => $resource->cdn_tenant_id]
            );
            CdnOriginServer::create([
                'cdn_origin_hostname' => $resource->cdn_origin_hostname,
                'cdn_origin_protocol' => $resource->cdn_origin_protocol,
                'cdn_origin_server_port' => $resource->cdn_origin_server_port,
                'cdn_origin_group_id' => $group->id,
                'type'=>"main"
            ]);
            CdnResourceOriginGroup::create([
                'cdn_resource_id' => $resource->id,
                'cdn_origin_group_id' => $group->id,
            ]);
            $resource->cdn_origin_hostname = null;
            $resource->cdn_origin_protocol = null;
            $resource->cdn_origin_server_port = null;
            $resource->save();
        }
    }
}
