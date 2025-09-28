<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingDetail extends Model
{
    protected $fillable = ['title', 'start_date', 'end_date', 'meeting_id'];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function propagations()
    {
        return $this->hasMany(MeetingDetailspropagation::class, 'meeting_detail_id');
    }
}
