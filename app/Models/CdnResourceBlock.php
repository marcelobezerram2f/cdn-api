<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdnResourceBlock extends Model
{
    use HasFactory;
    protected $table = 'cdn_resource_blocks';
    protected $primaryKey = 'id';

    protected $fillable = [
        'cdn_resource_id',
        'reason',
        'type',
    ];


    public function cdnResource()
    {
        return $this->hasOne(CdnResource::class, 'id', 'cdn_resource_id');
    }

}
