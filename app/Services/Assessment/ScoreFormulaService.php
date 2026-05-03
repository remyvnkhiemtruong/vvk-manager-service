<?php

namespace App\Services\Assessment;

use App\Models\ScoreEntry;
use App\Models\Subject;
use Illuminate\Support\Collection;

class ScoreFormulaService
{
    public function averageForEntries(Collection $entries, ?Subject $subject = null): ?float
    {
        if (($subject?->assessment_mode ?? 'numeric') === config('school.assessment.average.comment_subject_mode', 'comment')) {
            return null;
        }

        $sum = 0.0;
        $weightTotal = 0.0;

        foreach ($entries as $entry) {
            if (! $entry instanceof ScoreEntry || $entry->score === null) {
                continue;
            }

            $type = $entry->category ?? $entry->scoreColumn?->scoreType;

            if (! $type || ! (bool) $type->counts_toward_average || ($type->input_type ?? 'numeric') !== 'numeric') {
                continue;
            }

            $weight = (float) $type->weight;

            if ($weight <= 0) {
                continue;
            }

            $sum += (float) $entry->score * $weight;
            $weightTotal += $weight;
        }

        if ($weightTotal <= 0) {
            return null;
        }

        return round($sum / $weightTotal, (int) config('school.assessment.average.precision', 2));
    }

    public function weightedAverageSql(): string
    {
        return 'ROUND(SUM(student_scores.score * score_types.weight) / NULLIF(SUM(score_types.weight), 0), '.(int) config('school.assessment.average.precision', 2).')';
    }
}
