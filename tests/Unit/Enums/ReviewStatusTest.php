<?php

namespace Tests\Unit\Enums;

use App\Enums\ReviewStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReviewStatusTest extends TestCase
{
    /**
     * @return array<string, array{ReviewStatus, bool}>
     */
    public static function statuses(): array
    {
        return [
            'draft' => [ReviewStatus::Draft, true],
            'changes requested' => [ReviewStatus::ChangesRequested, true],
            'submitted' => [ReviewStatus::Submitted, false],
            'approved' => [ReviewStatus::Approved, false],
            'rejected' => [ReviewStatus::Rejected, false],
        ];
    }

    #[DataProvider('statuses')]
    public function test_encoders_can_edit_only_drafts_and_records_sent_back_for_changes(ReviewStatus $status, bool $editable): void
    {
        $this->assertSame($editable, $status->isEditableByEncoder());
    }
}
