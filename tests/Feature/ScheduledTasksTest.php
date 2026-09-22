<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduledTasksTest extends TestCase
{
    public function test_operational_alert_task_uses_lima_timezone_and_prevents_overlap(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'ops:alerts:evaluate'));

        $this->assertNotNull($event);
        $this->assertSame('America/Lima', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(55, $event->expiresAt);
    }
}
