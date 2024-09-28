<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class UserBackupCode extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'code'
    ];

    /**
     * Set the code attribute and automatically hash the code.
     *
     * @param  string  $value
     * @return void
     */
    public function setCodeAttribute($value)
    {
        $this->attributes['code'] = Hash::make($value);
    }

    /**
     * Verify the given code against the stored hashed code.
     *
     * @param  string  $value
     * @return bool
     */
    public function verify($value)
    {
        return Hash::check($value, $this->code);
    }
}
