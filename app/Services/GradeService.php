<?php

namespace App\Services;

use App\Models\GradeRule;

class GradeService
{
    /**
     * Calculate total score based on dynamic or default weights.
     */
    public function calculateTotal(array $scores, array $components): float
    {
        $total = 0;
        foreach ($components as $comp) {
            $key = $comp['key'];
            $weight = ((float) $comp['weight']) / 100.0;
            $val = (float) ($scores[$key] ?? 0);
            $total += $val * $weight;
        }
        return round($total, 2);
    }

    /**
     * Determine letter grade based on rules from the database or default rules.
     */
    public function determineGrade(float $total): string
    {
        // Try fetching rules from database, ordered descending by min_score
        $rules = GradeRule::orderBy('min_score', 'desc')->get();

        if ($rules->isNotEmpty()) {
            foreach ($rules as $rule) {
                if ($total >= $rule->min_score && $total <= $rule->max_score) {
                    return $rule->grade;
                }
            }
        }

        // Fallback default rules
        if ($total >= 85) return 'A';
        if ($total >= 70) return 'B';
        if ($total >= 55) return 'C';
        if ($total >= 50) return 'D';
        return 'F';
    }

    /**
     * Calculate and return score object containing total and grade
     */
    public function processScore(array $scores, array $components): array
    {
        $total = 0;
        $componentScores = [];

        foreach ($components as $comp) {
            $key = $comp['key'];
            $weight = ((float) $comp['weight']) / 100.0;
            $val = (float) ($scores[$key] ?? 0);
            $total += $val * $weight;
            $componentScores[$key] = $val;
        }

        $total = round($total, 2);
        $grade = $this->determineGrade($total);

        return [
            'components_scores' => $componentScores,
            'total' => $total,
            'grade' => $grade,
            // Fallback for legacy columns in scores table:
            'quiz' => (float) ($scores['quiz'] ?? 0),
            'assignment' => (float) ($scores['assignment'] ?? 0),
            'midterm' => (float) ($scores['midterm'] ?? 0),
            'final' => (float) ($scores['final'] ?? 0),
        ];
    }
}
