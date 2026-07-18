<?php

namespace Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use KuboKolibri\Client\KolibriClient;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * KolibriClient::openSession() logs a learner in server-side and returns the
 * resulting session cookies, so the SSO bridge never has to hand the learner's
 * password to the browser. The client's own (admin) session must be untouched.
 */
class KolibriOpenSessionTest extends TestCase
{
    use RefreshDatabase;

    /** A KolibriClient whose HTTP layer is a mock returning $queued responses. */
    private function clientReturning(Response ...$queued): KolibriClient
    {
        $client = (new \ReflectionClass(KolibriClient::class))->newInstanceWithoutConstructor();

        $http = new Client([
            'handler' => HandlerStack::create(new MockHandler($queued)),
            'base_uri' => 'http://localhost:8080', // gives Set-Cookie a domain to bind to
            'cookies' => true,
        ]);
        $prop = (new \ReflectionClass(KolibriClient::class))->getProperty('http');
        $prop->setAccessible(true);
        $prop->setValue($client, $http);

        return $client;
    }

    #[Test]
    public function it_returns_the_session_cookies_on_a_successful_login(): void
    {
        $client = $this->clientReturning(new Response(200, [
            'Set-Cookie' => ['kolibri=SESSION123; Path=/', 'kolibri_csrftoken=CSRF456; Path=/'],
        ]));

        $result = $client->openSession('kubo_1', 'secret', 'facility-uuid');

        $this->assertSame('SESSION123', $result['kolibri']);
        $this->assertSame('CSRF456', $result['kolibri_csrftoken']);
    }

    #[Test]
    public function it_returns_null_when_kolibri_rejects_the_credentials(): void
    {
        // Kolibri answers 403 on a bad password; Guzzle throws, openSession swallows to null.
        $client = $this->clientReturning(new Response(403, [], '{"detail":"Invalid"}'));

        $this->assertNull($client->openSession('kubo_1', 'wrong', 'facility-uuid'));
    }

    #[Test]
    public function it_returns_null_when_no_session_cookie_comes_back(): void
    {
        // 200 but no kolibri cookie — treat as failure rather than a half-session.
        $client = $this->clientReturning(new Response(200, [
            'Set-Cookie' => ['visitor_id=abc; Path=/'],
        ]));

        $this->assertNull($client->openSession('kubo_1', 'secret', 'facility-uuid'));
    }
}
