<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactEnquiry extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'email', 'subject', 'message'];

    protected $casts = ['completed_at' => 'datetime'];
}
