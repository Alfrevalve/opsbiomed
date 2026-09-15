<?php

namespace Tests\Feature;

use Tests\TestCase;

class RepositoryReadinessTest extends TestCase
{
    public function test_repository_examples_are_safe_and_health_route_is_available(): void
    {
        $productionEnvironment = file_get_contents(base_path('.env.production.example'));
        $gitignore = file_get_contents(base_path('.gitignore'));

        $this->assertIsString($productionEnvironment);
        $this->assertIsString($gitignore);
        $this->assertStringContainsString('APP_DEBUG=false', $productionEnvironment);
        $this->assertStringContainsString('APP_URL=https://opsbiomed.alfreval.com', $productionEnvironment);
        $this->assertStringContainsString('/.env.*', $gitignore);
        $this->assertStringNotContainsString('OpsBiomed2026!', $productionEnvironment);
        $this->assertStringNotContainsString('change-me', $productionEnvironment);
        $this->assertFileExists(public_path('build/manifest.json'));
        $this->assertSame(storage_path('framework/views'), config('view.compiled'));

        $this->get('/up')->assertOk();
    }
}
