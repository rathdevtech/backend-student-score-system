<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GradeRule extends Model
{
    use HasFactory;

    protected $fillable = ['min_score', 'max_score', 'grade'];
}
