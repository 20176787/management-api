<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserImages extends Model
{
    protected $fillable = [
        'name',
        'image_path',
        'user_id',
        'is_avatar'
    ];
}
