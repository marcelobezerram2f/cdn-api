<?php

namespace Database\Seeders;

use App\Models\CdnTargetGroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TargetGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $datas = [
            ["name"=>"Global","plan" => "global"],
            ["name"=>"América do Sul","plan" => "south-america"],
            ["name"=>"América do Norte","plan" => "north-america"],
            ["name"=>"Brasil", "plan" => "brazil"],
        ];

        foreach($datas as $data) {

            CdnTargetGroup::create($data);
        }
    }
}
