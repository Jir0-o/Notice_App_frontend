<?php

// app/Models/NoticeTemplateRegard.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoticeTemplateRegard extends Model
{
    protected $fillable = ['name','designation','note','notice_template_id','is_active','status'];
    protected $casts = ['is_active' => 'boolean'];

    public function noticeTemplate() { return $this->belongsTo(NoticeTemplate::class); }
}
