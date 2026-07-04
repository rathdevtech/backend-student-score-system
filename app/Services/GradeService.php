<?php

namespace App\Services;

use App\Models\GradeRule;

class GradeService
{
    /**
     * Calculate total score based on the formula:
     * Final Score = (Quiz × 20%) + (Assignment × 10%) + (Midterm × 30%) + (Final × 40%)
     */
    public function calculateTotal(float $quiz, float $assignment, float $midterm, float $final): float
    {
        $total = ($quiz * 0.20) + ($assignment * 0.10) + ($midterm * 0.30) + ($final * 0.40);
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
    public function processScore(array $data): array
    {
        $quiz = (float) ($data['quiz'] ?? 0);
        $assignment = (float) ($data['assignment'] ?? 0);
        $midterm = (float) ($data['midterm'] ?? 0);
        $final = (float) ($data['final'] ?? 0);

        $total = $this->calculateTotal($quiz, $assignment, $midterm, $final);
        $grade = $this->determineGrade($total);

        return [
            'quiz' => $quiz,
            'assignment' => $assignment,
            'midterm' => $midterm,
            'final' => $final,
            'total' => $total,
            'grade' => $grade,
        ];
    }
}
