<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CdnIngestPoint;

class IngestPointSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $datas = [
            ['name' => "São Paulo", 'origin_central'=>'BR/SP4','pop_prefix'=>'SPO'],
            ['name' => "Rio de Janeiro", 'origin_central'=>'BR/RJ0','pop_prefix'=>'RJO'],
            ['name' => "Fortaleza", 'origin_central'=>'BR/FLZ','pop_prefix'=>'FLZ'],
            ['name' => "Miami", 'origin_central'=>'US/MIA','pop_prefix'=>'MIA']
        ];

        foreach($datas as $data) {

            CdnIngestPoint::create($data);
        }
    }
}
