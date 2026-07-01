<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = [
        'contact_no',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'priority',
        'status',
        'user_id',
        'ip_address',
        'user_agent',
        'admin_note',
        'read_at',
        'replied_at',
        'closed_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'replied_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $q) use ($search) {
            $q->where(function (Builder $sub) use ($search) {
                $sub->where('contact_no', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        });
    }
}