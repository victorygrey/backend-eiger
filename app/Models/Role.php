<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'permissions', 'is_active'];

    protected $casts = ['permissions' => 'array', 'is_active' => 'boolean', 'is_system' => 'boolean'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'slug');
    }
}
