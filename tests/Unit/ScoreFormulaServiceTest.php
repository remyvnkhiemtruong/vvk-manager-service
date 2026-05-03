<?php

namespace Tests\Unit;

use App\Models\ScoreCategory;
use App\Models\ScoreEntry;
use App\Models\Subject;
use App\Services\Assessment\ScoreFormulaService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ScoreFormulaServiceTest extends TestCase
{
    public function test_weighted_average_uses_score_type_weights(): void
    {
        $tx = new ScoreCategory(['weight' => 1, 'input_type' => 'numeric', 'counts_toward_average' => true]);
        $ck = new ScoreCategory(['weight' => 3, 'input_type' => 'numeric', 'counts_toward_average' => true]);

        $first = new ScoreEntry(['score' => 8]);
        $first->setRelation('category', $tx);

        $second = new ScoreEntry(['score' => 6]);
        $second->setRelation('category', $ck);

        $average = app(ScoreFormulaService::class)->averageForEntries(new Collection([$first, $second]), new Subject(['assessment_mode' => 'numeric']));

        $this->assertSame(6.5, $average);
    }

    public function test_comment_subjects_and_comment_score_types_are_ignored(): void
    {
        $commentType = new ScoreCategory(['weight' => 0, 'input_type' => 'comment', 'counts_toward_average' => false]);
        $entry = new ScoreEntry(['score' => null, 'comment' => 'Dat']);
        $entry->setRelation('category', $commentType);

        $service = app(ScoreFormulaService::class);

        $this->assertNull($service->averageForEntries(new Collection([$entry]), new Subject(['assessment_mode' => 'numeric'])));
        $this->assertNull($service->averageForEntries(new Collection([$entry]), new Subject(['assessment_mode' => 'comment'])));
    }
}
