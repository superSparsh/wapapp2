<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Domains\Templates\Enums\TemplateStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TemplateStatusLabelTest extends TestCase
{
    #[Test]
    public function rejected_status_label_is_rejected_not_error(): void
    {
        $this->assertSame('Rejected', TemplateStatus::Rejected->label());
        $this->assertSame('fd-error', TemplateStatus::Rejected->chipVariant());
        $this->assertSame('rejected', TemplateStatus::Rejected->value);
    }

    #[Test]
    public function other_status_labels_remain_stable(): void
    {
        $this->assertSame('Yet to be submitted', TemplateStatus::Draft->label());
        $this->assertSame('Pending review', TemplateStatus::PendingReview->label());
        $this->assertSame('Approved', TemplateStatus::Approved->label());
    }
}
