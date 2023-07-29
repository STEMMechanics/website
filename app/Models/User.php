<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
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


    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();

        $clearCache = function ($user) {
            $cacheKeys = [
                "user:{$user->id}",
                "user:{$user->id}:permissions",
            ];
            Cache::forget($cacheKeys);
        };

        static::saving($clearCache);
        static::deleting($clearCache);
    }

    /**
     * Get the list of permissions of the user
     *
     * @return Illuminate\Database\Eloquent\Collection
     */
    public function permissions(): array
    {
        $cacheKey = "user:{$this->id}:permissions";
        return Cache::remember($cacheKey, now()->addDays(28), function () {
            return $this->hasMany(Permission::class)->pluck('permission')->toArray();
        });
    }

    /**
     * Get the permission attribute
     *
     * @return array
     */
    public function getPermissionsAttribute(): array
    {
        return $this->permissions();
    }

    /**
     * Test if user has permission
     *
     * @param string $permission Permission to test.
     * @return boolean
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions());
    }

    /**
     * Give permissions to the user
     *
     * @param string|array $permissions The permission(s) to give.
     * @return Illuminate\Database\Eloquent\Collection
     */
    public function givePermission($permissions): Collection
    {
        if (is_array($permissions) === false) {
            $permissions = [$permissions];
        }

        $permissions = collect($permissions)->map(function ($permission) {
            return ['permission' => $permission];
        });

        $existingPermissions = $this->permissions()->whereIn('permission', $permissions->pluck('permission'))->get();
        $newPermissions = $permissions->reject(function ($permission) use ($existingPermissions) {
            return $existingPermissions->contains('permission', $permission['permission']);
        });

        $cacheKey = "user:{$this->id}:permissions";
        Cache::forget($cacheKey);

        return $this->permissions()->createMany($newPermissions->toArray());
    }


    /**
     * Revoke permissions from the user
     *
     * @param string|array $permissions The permission(s) to revoke.
     * @return integer
     */
    public function revokePermission($permissions): int
    {
        if (is_array($permissions) === false) {
            $permissions = [$permissions];
        }

        $cacheKey = "user:{$this->id}:permissions";
        Cache::forget($cacheKey);

        return $this->permissions()
            ->whereIn('permission', $permissions)
            ->delete();
    }

    /**
     * Get the list of files of the user
     *
     * @return HasMany
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * Get the list of files of the user
     *
     * @return HasMany
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /**
     * Get associated user codes
     *
     * @return HasMany
     */
    public function codes(): HasMany
    {
        return $this->hasMany(UserCode::class);
    }

    /**
     * Get the list of logins of the user
     *
     * @return HasMany
     */
    public function logins(): HasMany
    {
        return $this->hasMany(UserLogins::class);
    }

    /**
     * Get the events associated with the user.
     *
     * @return BelongsToMany
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_user', 'user_id', 'event_id');
    }
}
