<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     required={"id", "name", "email"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Nadia"),
 *     @OA\Property(property="email", type="string", example="user@example.com"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 * )
 */

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'designation_id',
        'department_id',
        'status',
    ];

    protected $hidden = ['password'];
    protected $appends = ['role'];

    protected function role(): Attribute
    {
        return Attribute::make(
            get: fn(mixed $value) => $this->roles->first()->name,
        );
    }

    public function userhasRole()
    {
        return $this->hasMany(ModelHasRole::class, 'model_id');
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function createdNotices()
    {
        return $this->hasMany(Notice::class, 'created_by');
    }

    public function modifiedNotices()
    {
        return $this->hasMany(Notice::class, 'modified_by');
    }

    public function noticePropagations()
    {
        return $this->hasMany(NoticePropagation::class);
    }
}
