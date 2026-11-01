<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityHeadersTest extends WebTestCase
{
    public function testEveryPageCarriesAStrictContentSecurityPolicy(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/connexion');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');

        $csp = (string) $client->getResponse()->headers->get('Content-Security-Policy');
        self::assertStringContainsString("frame-ancestors 'none'", $csp);
        self::assertStringNotContainsString('unsafe-inline', $csp);
        self::assertStringNotContainsString('unsafe-eval', $csp);

        // Les scripts en ligne de l'importmap portent le nonce annoncé par l'en-tête
        if (1 !== preg_match("/'nonce-([^']+)'/", $csp, $match)) {
            self::fail('La CSP ne déclare aucun nonce.');
        }
        self::assertGreaterThan(0, $crawler->filter('script[nonce="'.$match[1].'"]')->count());
    }

    public function testTheNonceChangesOnEveryRequest(): void
    {
        $client = self::createClient();

        $client->request('GET', '/connexion');
        $first = $client->getResponse()->headers->get('Content-Security-Policy');
        $client->request('GET', '/connexion');

        self::assertNotSame($first, $client->getResponse()->headers->get('Content-Security-Policy'));
    }
}
