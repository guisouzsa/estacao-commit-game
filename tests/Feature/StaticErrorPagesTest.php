<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Páginas estáticas usadas pelo Apache quando o Laravel nem chega a responder. */
class StaticErrorPagesTest extends TestCase
{
    public function test_apache_error_pages_exist_are_self_contained_and_friendly(): void
    {
        foreach ([500, 502, 503, 504] as $status) {
            $html = file_get_contents(public_path("errors/{$status}.html"));

            $this->assertStringContainsString("Erro {$status}", $html);
            $this->assertStringContainsString('Estação', $html);
            $this->assertStringNotContainsString('http://', $html);
            $this->assertStringNotContainsString('https://', $html);
            $this->assertStringNotContainsString('<script src', $html);
            foreach (['Laravel', 'Apache', 'Render', 'Exception', 'Stack', 'SQL'] as $technical) {
                $this->assertStringNotContainsString($technical, $html);
            }
        }
    }

    public function test_apache_is_configured_for_every_page_and_the_image_copies_it(): void
    {
        $conf = file_get_contents(base_path('docker/apache/error-pages.conf'));
        foreach ([500, 502, 503, 504] as $status) {
            $this->assertStringContainsString("ErrorDocument {$status} /errors/{$status}.html", $conf);
        }
        $dockerfile = file_get_contents(base_path('Dockerfile'));
        $this->assertStringContainsString('error-pages.conf', $dockerfile);
        $this->assertStringContainsString('a2enconf reverb-proxy mpm-tuning error-pages', $dockerfile);
    }

    public function test_every_page_ships_a_friendly_opening_screen_instead_of_a_black_one(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="boot-splash"', $html);
        $this->assertStringContainsString('Aguarde um instante', $html);
        $this->assertStringContainsString('preparando o ambiente para você jogar', $html);
    }
}
