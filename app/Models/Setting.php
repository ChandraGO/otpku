<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'encrypted'];
    protected function casts(): array { return ['encrypted' => 'boolean']; }
}
