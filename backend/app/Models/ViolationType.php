<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViolationType extends Model
{
    protected $fillable = ['code', 'name', 'deduction_points', 'severity'];
}
