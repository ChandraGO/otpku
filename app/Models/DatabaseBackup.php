<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class DatabaseBackup extends Model
{
    protected $fillable = ['created_by', 'filename', 'disk', 'size', 'checksum', 'source', 'status'];
}
