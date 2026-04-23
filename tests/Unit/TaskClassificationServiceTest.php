<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TaskClassificationService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TaskClassificationServiceTest extends TestCase
{
    public function testInScopeTaskBecomesInformative(): void
    {
        $service = new TaskClassificationService();
        $normalized = $service->normalize('in_scope', 999);

        self::assertSame('informative', $normalized['billing_type']);
        self::assertSame(0.0, $normalized['billable_amount']);
    }

    public function testBillableTaskRequiresPositiveAmount(): void
    {
        $this->expectException(RuntimeException::class);
        (new TaskClassificationService())->normalize('out_of_scope', 0);
    }
}
