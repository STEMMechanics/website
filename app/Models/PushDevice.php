<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushDevice extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['subscription', 'endpoint_hash'];

    protected $casts = ['enabled' => 'boolean', 'subscription' => 'encrypted:array'];
}
