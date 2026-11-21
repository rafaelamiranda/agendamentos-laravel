<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Os testes cobrem o backend; não dependem do build do front
        // (public/build/manifest.json), então o CI não precisa de Node.
        $this->withoutVite();

        // Nenhum teste fala com a internet: toda chamada HTTP precisa de um
        // Http::fake correspondente, ou o teste falha.
        Http::preventStrayRequests();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
