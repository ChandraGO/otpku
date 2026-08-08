<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteMedia extends Model
{
    protected $table = 'site_media';

    protected $hidden = ['data_base64'];

    protected $fillable = [
        'key',
        'mime_type',
        'data_base64',
    ];
}
