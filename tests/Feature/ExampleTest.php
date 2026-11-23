<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_redesigned_public_pages_share_the_editorial_shell(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Undangan yang')
            ->assertSee('assets/site/css/site.css', false)
            ->assertSee('assets/beranda/hero.webp', false)
            ->assertSee('assets/beranda/pngegg%20%287%29.png', false)
            ->assertSee('assets/beranda/undangan-website.webp', false)
            ->assertSee('assets/beranda/banner-our-stories.webp', false)
            ->assertSee('assets/beranda/banner-our-little-promise.webp', false);

        $this->get(route('templates.index'))
            ->assertOk()
            ->assertSee('Pilih Template Undangan')
            ->assertSee('data-template-search', false);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Masuk dengan Google')
            ->assertSee('google-btn', false);
    }
}
