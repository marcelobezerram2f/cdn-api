<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdnCname extends Model
{
    use HasFactory;


    protected $table = 'cdn_cnames';
    protected $primaryKey = 'id';
    protected $fillable= ['cname', 'cdn_letsencrypt_acme_register_id', 'cdn_resource_id'];




}


