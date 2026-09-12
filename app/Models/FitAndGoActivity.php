<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FitAndGoActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'care_mc_level_2',
        'image',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];
}
