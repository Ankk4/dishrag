<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie as BrowserKitCookie;

trait ApiBrowserTestTrait
{
    private function applySetCookieHeaders(KernelBrowser $client): void
    {
        foreach ($client->getResponse()->headers->all('set-cookie') as $raw) {
            $client->getCookieJar()->set(BrowserKitCookie::fromString($raw, 'http://localhost'));
        }
    }

    private function bearer(KernelBrowser $client, string $accessToken): void
    {
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$accessToken);
    }
}
