<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NoticePropagation extends Model
{
    use HasFactory;

    protected $fillable = [
        'notice_id',
        'user_id',
        'user_email',
        'name',         // new
        'is_read',
        'sent_at',      // new
    ];

    public function notice()
    {
        return $this->belongsTo(Notice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scope for drafts (not sent)
    public function scopeDraft($query)
    {
        return $query->whereNull('sent_at');
    }

    // Scope for sent
    public function scopeSent($query)
    {
        return $query->whereNotNull('sent_at');
    }
}
