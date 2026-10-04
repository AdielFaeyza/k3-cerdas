<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Camera extends Model
{
    protected $fillable = ['camera_code', 'location_name', 'ip_address'];
}
