<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppUpdate extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'platform',
        'latest_version',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function setPlatformAttribute($value): void
    {
        $this->attributes['platform'] = strtolower(trim((string) $value));
    }

    public function setLatestVersionAttribute($value): void
    {
        $this->attributes['latest_version'] = trim((string) $value);
    }
}