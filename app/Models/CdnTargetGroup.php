<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdnTargetGroup extends Model
{
    use HasFactory;

    protected $table = "cdn_target_groups";
    protected $primaryKey = "id";
    protected $fillable = ['name', 'plan'];
}
