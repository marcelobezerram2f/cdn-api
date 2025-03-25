<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdnAuditLog extends Model
{
    use HasFactory;



    protected $table= 'cdn_audit_logs';
    protected $primaryKey = 'id';
    protected $fillable = [
        'user_type',
        'ip_address',
        'account',
        'action',
        'type',
        'message',
        'payload',
        'response',
    ];
}
