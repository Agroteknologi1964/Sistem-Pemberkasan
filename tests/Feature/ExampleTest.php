<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Root URL memang sengaja redirect ke halaman login (lihat routes/web.php).
     * Test bawaan mengharapkan 200 sehingga gagal setelah perilaku ini ditetapkan.
     */
    public function test_root_url_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_login_page_is_reachable(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_login_page_sends_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_protected_page_redirects_guest_to_login(): void
    {
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_login_is_rate_limited(): void
    {
        // 5 percobaan per menit; percobaan ke-6 harus ditolak rate limiter.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email'    => 'rate-limit@example.test',
                'password' => 'salah-' . $i,
            ]);
        }

        $response = $this->post('/login', [
            'email'    => 'rate-limit@example.test',
            'password' => 'salah-lagi',
        ]);

        $response->assertHeader('Retry-After');
    }
}
