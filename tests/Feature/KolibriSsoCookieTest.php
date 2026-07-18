<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use KuboKolibri\Services\KolibriSessionBridge;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The SSO bridge hands the browser Kolibri's session as raw Set-Cookie headers
 * scoped to the proxy path. These pin that scoping — it's what keeps the session
 * cookie away from the rest of the origin and lets the proxy forward it verbatim.
 */
class KolibriSsoCookieTest extends TestCase
{
    use RefreshDatabase;

    private function cookieHeaders(array $session): array
    {
        // Pure string formatting — no client/provisioner needed.
        $bridge = (new \ReflectionClass(KolibriSessionBridge::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(KolibriSessionBridge::class, 'sessionCookieHeaders');
        $method->setAccessible(true);

        return $method->invoke($bridge, $session);
    }

    #[Test]
    public function the_session_cookie_is_scoped_to_the_proxy_path_and_http_only(): void
    {
        $headers = $this->cookieHeaders(['kolibri' => 'SID123', 'kolibri_csrftoken' => 'CSRF456']);

        $this->assertStringContainsString('kolibri=SID123', $headers[0]);
        $this->assertStringContainsString('Path=/kolibri-proxy/', $headers[0]);
        $this->assertStringContainsString('HttpOnly', $headers[0]);
    }

    #[Test]
    public function the_csrf_token_is_forwarded_scoped_but_readable(): void
    {
        $headers = $this->cookieHeaders(['kolibri' => 'SID123', 'kolibri_csrftoken' => 'CSRF456']);

        $this->assertStringContainsString('kolibri_csrftoken=CSRF456', $headers[1]);
        $this->assertStringContainsString('Path=/kolibri-proxy/', $headers[1]);
        $this->assertStringNotContainsString('HttpOnly', $headers[1]); // Kolibri's JS reads it
    }

    #[Test]
    public function no_csrf_cookie_is_emitted_when_kolibri_returned_none(): void
    {
        $headers = $this->cookieHeaders(['kolibri' => 'SID123', 'kolibri_csrftoken' => null]);

        $this->assertCount(1, $headers);
    }
}
