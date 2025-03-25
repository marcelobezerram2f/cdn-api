<?php

namespace Database\Seeders;

use App\Models\CdnTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CdnTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $datas = [
            ['template_name' => "live", 'label' => "Live Content", 'active' => 1, 'template_json' => '{"services":{"webtv":{"origin":{"backends":[{"defaultAccount":"$data[\'tenant\']","name":"$data[\'cdn_resource_hostname\']","prefix":"/$data[\'storage_id\']","rewrite":{"this":"/$data[\'storage_id\']/"},"serverGroups":["$data[\'cdn_resource_hostname\']"],"sessionHandling":{"limits":{"maxLocationChanges":0.0}}}],"serverGroups":[{"name":"$data[\'cdn_resource_hostname\']","nodes":[{"https":{"enable":"$data[\'protocol\']"},"tcpAddresses":["$data[\'cdn_origin_hostname\']:$data[\'cdn_origin_server_port\']"]}]}]}}}}'],
            ['template_name' => "vod", 'label' => "Video On Demand", 'active' => 1, 'template_json' => '{"services":{"webtv":{"origin":{"backends":[{"defaultAccount":"$data[\'tenant\']","name":"$data[\'cdn_resource_hostname\']","prefix":"/$data[\'storage_id\']","rewrite":{"this":"/$data[\'storage_id\']/"},"serverGroups":["$data[\'cdn_resource_hostname\']"],"sessionHandling":{"limits":{"maxLocationChanges":0.0}}}],"serverGroups":[{"name":"$data[\'cdn_resource_hostname\']","nodes":[{"https":{"enable":"$data[\'protocol\']"},"tcpAddresses":["$data[\'cdn_origin_hostname\']:$data[\'cdn_origin_server_port\']"]}]}]}}}}'],
        ];

        foreach ($datas as $data) {

            CdnTemplate::create($data);
        }
    }

}
