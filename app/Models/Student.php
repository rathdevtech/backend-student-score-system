<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = ['class_id', 'gender', 'user_id'];

    // NO $appends here — avoids circular recursion with User model

    /**
     * Access student's name from linked user (safe: checks relationLoaded).
     */
    public function getNameAttribute(): string
    {
        if ($this->relationLoaded('user') && $this->user) {
            return $this->user->attributes['name'] ?? '';
        }
        return '';
    }

    /**
     * Access student's Khmer name from linked user.
     */
    public function getNameKhAttribute(): ?string
    {
        if ($this->relationLoaded('user') && $this->user) {
            return $this->user->attributes['name_kh'] ?? null;
        }
        return null;
    }

    /**
     * Access student's photo (stored as avatar on user).
     */
    public function getPhotoAttribute(): ?string
    {
        if ($this->relationLoaded('user') && $this->user) {
            return $this->user->attributes['avatar'] ?? null;
        }
        return null;
    }

    /**
     * Access student's active status from linked user.
     */
    public function getIsActiveAttribute(): bool
    {
        if ($this->relationLoaded('user') && $this->user) {
            return (bool) ($this->user->attributes['is_active'] ?? true);
        }
        return true;
    }

    /**
     * When converting to array/JSON, include virtual fields if user is loaded.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        if ($this->relationLoaded('user') && $this->user) {
            $array['name']      = $this->getNameAttribute();
            $array['name_kh']   = $this->getNameKhAttribute();
            $array['photo']     = $this->getPhotoAttribute();
            $array['is_active'] = $this->getIsActiveAttribute();
        }

        return $array;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }
}
