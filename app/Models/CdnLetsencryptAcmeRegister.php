<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdnLetsencryptAcmeRegister extends Model
{
    use HasFactory;

    protected $table = "cdn_letsencrypt_acme_registries";
    protected $primaryKey = "id";
    protected $fillable = [
        'cdn_resource_id',
        'username',
        'password',
        'fulldomain',
        'subdomain',
        'company',
        'active',
        'certificate',
        'private_key',
        'intermediate_certificate',
        'csr',
        'fullchain',
        'publication_attempts',
        'published',
        'attempt_install',
        'last_attempt',
        'certificate_created',
        'certificate_expires'
    ];

}
