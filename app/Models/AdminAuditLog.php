<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class AdminAuditLog extends Model
{
    protected $fillable = ['admin_id', 'action', 'subject_type', 'subject_id', 'ip_address', 'user_agent', 'before', 'after'];
    protected function casts(): array { return ['before' => 'array', 'after' => 'array']; }
}
