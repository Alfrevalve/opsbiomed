<?php

namespace Tests\Feature;

use Tests\TestCase;

class PhaseZeroSecurityTest extends TestCase
{
    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard/ops')->assertRedirect();
    }
}
