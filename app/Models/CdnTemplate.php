<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdnTemplate extends Model
{
    use HasFactory;

    protected $table = 'cdn_templates';
    protected $primaryKey = 'id';
    protected $fillable = [
        'template_name',
        'label',
        'active',
        'template_json'
    ];

}
