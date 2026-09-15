<?php

namespace Tests\Unit;

use App\Services\Inventory\StockEligibilityService;
use PHPUnit\Framework\TestCase;

class StockEligibilityServiceTest extends TestCase
{
    public function test_service_class_exists(): void
    {
        $this->assertTrue(class_exists(StockEligibilityService::class));
    }
}
