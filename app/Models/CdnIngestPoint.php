<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdnIngestPoint extends Model
{
    use HasFactory;

    protected $table =  'cdn_ingest_points';
    protected $primaryKey = 'id';
    protected $fillable = ['name', 'origin_central','pop_prefix'];

}
