<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    public function hasRole(string $role): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains(fn (Role $item): bool => $item->slug === $role);
    }

    public function permissionKeys(): Collection
    {
        $this->loadMissing('roles.permissions');

        return $this->roles
            ->flatMap(fn (Role $role): Collection => $role->permissions->pluck('key'))
            ->unique()
            ->values();
    }

    public function hasPermission(string $permission): bool
    {
        $this->loadMissing('roles.permissions');

        if ($this->roles->contains(fn (Role $role): bool => $role->slug === 'admin')) {
            return true;
        }

        $keys = $this->permissionKeys();

        if ($keys->contains('*') || $keys->contains($permission)) {
            return true;
        }

        $segments = explode('.', $permission);

        while (count($segments) > 1) {
            array_pop($segments);

            if ($keys->contains(implode('.', $segments).'.*')) {
                return true;
            }
        }

        return false;
    }
}

