<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingDetail extends Model
{
    protected $fillable = ['title', 'date', 'start_time', 'end_time', 'meeting_id'];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function propagations()
    {
        return $this->hasMany(MeetingDetailspropagation::class, 'meeting_detail_id');
    }
}
