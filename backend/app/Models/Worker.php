<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Worker extends Model
{
    use SoftDeletes;

    protected $fillable = ['worker_code', 'name', 'department', 'email', 'employment_type', 'status', 'current_score'];

    public function violationLogs(): HasMany
    {
        return $this->hasMany(ViolationLog::class);
    }
}
