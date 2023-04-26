<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Contracts\Auditable;

class User extends Authenticatable implements Auditable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use Uuids;
    use \OwenIt\Auditing\Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'display_name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'permissions'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // protected $hidden = [
    //     'permissions'
    // ];

    /**
     * The attributes to append.
     *
     * @var string[]
     */
    protected $appends = [
        'permissions'
    ];

    /**
     * The default attributes.
     *
     * @var string[]
     */
    protected $attributes = [
        'phone' => '',
    ];


    // public function getPermissionsAttribute() {
    //     return $this->permissions()->pluck('permission')->toArray();
    // }


    /**
     * Get the list of files of the user
     *
     * @return HasMany
     */
    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }

    /**
     * Get the permission attribute
     *
     * @return array
     */
    public function getPermissionsAttribute()
    {
        return $this->permissions()->pluck('permission')->toArray();
    }

    /**
     * Test if user has permission
     *
     * @param string $permission Permission to test.
     * @return boolean
     */
    public function hasPermission(string $permission)
    {
        return ($this->permissions()->where('permission', $permission)->first() !== null);
    }

    /**
     * Give permissions to the user
     *
     * @param string|array $permissions The permission(s) to give.
     * @return Collection
     */
    public function givePermission($permissions)
    {
        if (!is_array($permissions)) {
            $permissions = [$permissions];
        }

        $permissions = collect($permissions)->map(function ($permission) {
            return ['permission' => $permission];
        });

        $existingPermissions = $this->permissions()->whereIn('permission', $permissions->pluck('permission'))->get();
        $newPermissions = $permissions->reject(function ($permission) use ($existingPermissions) {
            return $existingPermissions->contains('permission', $permission['permission']);
        });

        return $this->permissions()->createMany($newPermissions->toArray());
    }


    /**
     * Revoke permissions from the user
     *
     * @param string|array $permissions The permission(s) to revoke.
     * @return integer
     */
    public function revokePermission($permissions)
    {
        if (!is_array($permissions)) {
            $permissions = [$permissions];
        }

        return $this->permissions()
            ->whereIn('permission', $permissions)
            ->delete();
    }

    /**
     * Get the list of files of the user
     *
     * @return HasMany
     */
    public function media()
    {
        return $this->hasMany(Media::class);
    }

    /**
     * Get the list of files of the user
     *
     * @return HasMany
     */
    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    /**
     * Get associated user codes
     *
     * @return HasMany
     */
    public function codes()
    {
        return $this->hasMany(UserCode::class);
    }

    /**
     * Get the list of logins of the user
     *
     * @return HasMany
     */
    public function logins()
    {
        return $this->hasMany(UserLogins::class);
    }
}
