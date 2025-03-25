<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LetsencryptCertificateReprocessing extends Model
{
    use HasFactory;

    protected $table = "letsencrypt_certificates_reprocessing";
    protected $privateKey = "id";
    protected $fillable = [
        'cdn_resource_id',
        'status',
        'domain',
        'csr',
        'private_key',
        'url',
        'payload',
    ];

}
