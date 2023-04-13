<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ModuleConfig extends DeviceRelatedModel
{
    use HasFactory;
    protected $casts = ['config' => 'array'];
    protected $fillable = ['device_id', 'module', 'config'];
}
