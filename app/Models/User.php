<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\CmsPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
        'job_title',
        'invited_at',
        'invitation_accepted_at',
        'password_setup_token_hash',
        'password_setup_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'password_setup_token_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'invited_at' => 'datetime',
            'invitation_accepted_at' => 'datetime',
            'password_setup_expires_at' => 'datetime',
        ];
    }

    /**
     * Check if user has SuperAdmin / SuperUser privileges.
     */
    public function isSuperAdmin(): bool
    {
        return in_array(strtolower((string) $this->role), ['superadmin', 'superuser'], true);
    }

    /**
     * Check if user is standard Admin.
     */
    public function isAdmin(): bool
    {
        return strtolower((string) $this->role) === 'admin';
    }

    /**
     * Check if account is active.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active && (bool) $this->accessRole?->is_active;
    }

    public function accessRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role', 'slug');
    }

    public function hasPermission(string $permission): bool
    {
        if (! in_array($permission, CmsPermissions::keys(), true)) {
            return false;
        }
        if (! $this->isActive() || $this->awaitingSetup()) {
            return false;
        }
        if ($this->isSuperAdmin()) {
            return true;
        }
        $grants = $this->accessRole?->permissions ?? [];

        return in_array($permission, $grants, true)
            && in_array(explode('.', $permission)[0].'.view', $grants, true);
    }

    public function awaitingSetup(): bool
    {
        return $this->invited_at !== null && $this->invitation_accepted_at === null;
    }

    public function roleName(): string
    {
        return $this->accessRole?->name ?? $this->role;
    }

    public function homeRoute(): string
    {
        foreach (CmsPermissions::modules() as $module => $definition) {
            if ($this->hasPermission($module.'.view')) {
                return $definition['route'];
            }
        }

        return 'admin.profile.index';
    }
}
