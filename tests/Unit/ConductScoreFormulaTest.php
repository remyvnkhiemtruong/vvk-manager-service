<?php

namespace Tests\Unit;

use App\Models\ConductRecord;
use App\Models\ConductRule;
use App\Models\ConductScore;
use App\Services\Conduct\ConductScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConductScoreFormulaTest extends TestCase
{
    use RefreshDatabase;

    public function test_formula_counts_only_approved_records_and_applies_rating(): void
    {
        $this->seed();

        $summary = ConductScore::firstOrFail();
        ConductRecord::where('student_id', $summary->student_id)->where('semester_id', $summary->semester_id)->delete();

        $bonus = ConductRule::where('code', 'PEER_SUPPORT')->firstOrFail();
        $minus = ConductRule::where('code', 'LATE')->firstOrFail();
        $pending = ConductRule::where('code', 'FIGHTING')->firstOrFail();

        ConductRecord::create([
            'school_year_id' => $summary->school_year_id,
            'semester_id' => $summary->semester_id,
            'class_id' => $summary->class_id,
            'student_id' => $summary->student_id,
            'conduct_rule_id' => $bonus->id,
            'points' => $bonus->points,
            'recorded_date' => '2026-03-01',
            'status' => 'approved',
        ]);
        ConductRecord::create([
            'school_year_id' => $summary->school_year_id,
            'semester_id' => $summary->semester_id,
            'class_id' => $summary->class_id,
            'student_id' => $summary->student_id,
            'conduct_rule_id' => $minus->id,
            'points' => $minus->points,
            'recorded_date' => '2026-03-02',
            'status' => 'approved',
        ]);
        ConductRecord::create([
            'school_year_id' => $summary->school_year_id,
            'semester_id' => $summary->semester_id,
            'class_id' => $summary->class_id,
            'student_id' => $summary->student_id,
            'conduct_rule_id' => $pending->id,
            'points' => $pending->points,
            'recorded_date' => '2026-03-03',
            'status' => 'pending',
        ]);

        $summary->forceFill(['base_score' => 80, 'adjustment_points' => 2])->save();

        $result = app(ConductScoreService::class)->recalculate($summary);

        $this->assertSame(5, $result->bonus_points);
        $this->assertSame(3, $result->minus_points);
        $this->assertSame(84, $result->score);
        $this->assertSame('Khá', $result->rating);
    }

    public function test_formula_clamps_to_configured_min_and_max(): void
    {
        $this->seed();

        $summary = ConductScore::firstOrFail();
        ConductRecord::where('student_id', $summary->student_id)->where('semester_id', $summary->semester_id)->delete();
        $summary->forceFill(['base_score' => 100, 'adjustment_points' => 50])->save();

        $result = app(ConductScoreService::class)->recalculate($summary);

        $this->assertSame(100, $result->score);
        $this->assertSame('Tốt', $result->rating);
    }
}
