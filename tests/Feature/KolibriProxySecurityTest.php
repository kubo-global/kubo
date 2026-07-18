<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use KuboKolibri\Http\Controllers\ProxyController;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * The Kolibri reverse proxy forwards an arbitrary sub-path. resolveTargetUrl()
 * must keep every request pinned to the configured Kolibri host — a path like
 * //evil.com is a network-path reference that would otherwise be proxied off-host
 * (SSRF). These tests exercise the URL builder directly; no network needed.
 */
class KolibriProxySecurityTest extends TestCase
{
    use RefreshDatabase;

    private function controller(string $kolibriUrl = 'http://localhost:8080'): ProxyController
    {
        config(['kubo-kolibri.kolibri_url' => $kolibriUrl]);

        return new ProxyController();
    }

    private function host(string $url): ?string
    {
        return parse_url($url, PHP_URL_HOST);
    }

    #[Test]
    public function a_normal_path_is_preserved_and_stays_on_the_kolibri_host(): void
    {
        $url = $this->controller()->resolveTargetUrl('/kolibri-proxy/en/learn/', 'foo=bar');

        $this->assertSame('http://localhost:8080/en/learn/?foo=bar', $url);
        $this->assertSame('localhost', $this->host($url));
    }

    #[Test]
    public function the_root_path_resolves_to_the_kolibri_root(): void
    {
        $this->assertSame(
            'http://localhost:8080/',
            $this->controller()->resolveTargetUrl('/kolibri-proxy', null)
        );
    }

    #[Test]
    public function a_network_path_reference_cannot_escape_to_another_host(): void
    {
        // //evil.com/x — the classic SSRF vector. Must land on Kolibri, not evil.com.
        $url = $this->controller()->resolveTargetUrl('/kolibri-proxy//evil.com/steal', null);

        $this->assertSame('localhost', $this->host($url));
        $this->assertStringNotContainsString('evil.com/steal', parse_url($url, PHP_URL_HOST) ?? '');
    }

    #[Test]
    public function triple_slash_and_stacked_slashes_also_stay_on_host(): void
    {
        foreach (['/kolibri-proxy///evil.com', '/kolibri-proxy/////evil.com/x'] as $uri) {
            $this->assertSame('localhost', $this->host($this->controller()->resolveTargetUrl($uri, null)), $uri);
        }
    }

    #[Test]
    public function an_embedded_scheme_is_rejected(): void
    {
        $this->expectException(HttpException::class);
        $this->controller()->resolveTargetUrl('/kolibri-proxy/http://evil.com', null);
    }

    #[Test]
    public function a_backslash_path_is_rejected(): void
    {
        $this->expectException(HttpException::class);
        $this->controller()->resolveTargetUrl('/kolibri-proxy/\\evil.com', null);
    }

    #[Test]
    public function the_host_pin_follows_the_configured_kolibri_url(): void
    {
        // Even with a non-default host, an escape attempt stays on that host.
        $url = $this->controller('http://kolibri.local:9090')->resolveTargetUrl('/kolibri-proxy//evil.com', null);

        $this->assertSame('kolibri.local', $this->host($url));
    }
}
