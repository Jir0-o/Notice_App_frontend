<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'notice_id',
        'file_name',
        'file_type',
        'file_path',
        'uploaded_at',
    ];

    public function notice()
    {
        return $this->belongsTo(Notice::class);
    }
}
