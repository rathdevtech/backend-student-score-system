<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'name_kh', 'email', 'password', 'role', 'avatar', 'is_active', 'role_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    // NO $appends here — avoids circular recursion with Student model

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    public function roleRelation()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function student()
    {
        return $this->hasOne(Student::class, 'user_id');
    }

    /**
     * Safe accessor — only reads from already-loaded relation to avoid recursion.
     */
    public function getStudentIdAttribute(): ?int
    {
        if ($this->relationLoaded('student') && $this->student) {
            return $this->student->getKey();
        }
        // Fallback: direct DB query without triggering Student appends
        return Student::where('user_id', $this->getKey())->value('id');
    }

    /**
     * Override toArray to inject student_id without $appends recursion.
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        $array['student_id'] = $this->getStudentIdAttribute();
        return $array;
    }

    public function getRoleAttribute()
    {
        return $this->roleRelation ? $this->roleRelation->name : ($this->attributes['role'] ?? 'teacher');
    }

    public function setRoleAttribute($value)
    {
        $role = Role::where('name', $value)->first();
        if ($role) {
            $this->attributes['role_id'] = $role->id;
        }
        $this->attributes['role'] = $value;
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->role === 'admin') {
            return true;
        }
        if (!$this->roleRelation) {
            return false;
        }
        return $this->roleRelation->permissions()->where('slug', $permissionSlug)->exists();
    }
}
