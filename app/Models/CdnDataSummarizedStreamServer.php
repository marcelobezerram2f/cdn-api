<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdnDataSummarizedStreamServer extends Model
{
    use HasFactory;

    protected $table = 'cdn_data_summarized_stream_server';
    protected $privateKey = 'id';
    protected $fillable = [
        'cdn_data_summarized_tenant_id',
        'summary_date',
        'stream_server',
        'bytes_transmitted'
    ];

}
