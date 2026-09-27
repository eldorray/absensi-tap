<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tolak berjalan kalau config sedang di-cache.
     *
     * Config cache mengabaikan env di phpunit.xml, termasuk DB_DATABASE=:memory:.
     * RefreshDatabase lalu menjalankan migrate:fresh pada database.sqlite milik
     * development dan mengosongkannya -- ini pernah terjadi. Dicek sebelum
     * parent::setUp() karena di sanalah RefreshDatabase mulai bekerja.
     */
    protected function setUp(): void
    {
        if (is_file(dirname(__DIR__).'/bootstrap/cache/config.php')) {
            throw new \RuntimeException('Config sedang di-cache: jalankan `php artisan config:clear` sebelum test, atau database development akan dikosongkan.');
        }

        parent::setUp();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
