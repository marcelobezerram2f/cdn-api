<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdnApi extends Model
{
    use HasFactory;

    protected $table = 'cdn_apis';
    protected $primaryKey = 'id';
    protected $fillable = [
        'api_name',
        'url',
        'token'
    ];
}
