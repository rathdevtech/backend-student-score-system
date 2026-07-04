<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(ClassModel::class, 'class_subject', 'subject_id', 'class_id')
                    ->withPivot('teacher_id')
                    ->withTimestamps();
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }
}
