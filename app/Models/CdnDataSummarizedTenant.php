<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdnDataSummarizedTenant extends Model
{
    use HasFactory;

    protected $table = 'cdn_data_summarized_tenant';

    protected $primary_key = "id";

    protected $fillable = [
      'client_id',
      'tenant',
      'total_bytes_transmitted',
       'summary_date'
    ];


    public function streamServer()
    {
        return $this->hasMany(CdnDataSummarizedStreamServer::class, 'cdn_data_summarized_tenant_id', 'id');
    }
}
