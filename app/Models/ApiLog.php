<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class ApiLog extends Model
{
    protected $fillable = ['user_id', 'provider', 'method', 'endpoint', 'status_code', 'duration_ms', 'successful', 'error_code', 'error_message', 'request_meta'];
    protected function casts(): array { return ['successful' => 'boolean', 'request_meta' => 'array']; }
}
