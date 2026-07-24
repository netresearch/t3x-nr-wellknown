<?php

declare(strict_types=1);

namespace Netresearch\NrWellknown\Tests\Unit\Middleware;

use Netresearch\NrWellknown\Middleware\ChangePasswordMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;

final class ChangePasswordMiddlewareTest extends TestCase
{
    private function handler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response('php://temp', 404);
            }
        };
    }

    public function testRedirectsWhenConfigured(): void
    {
        $site = new Site('main', 1, [
            'base'      => 'https://www.netresearch.de/',
            'wellknown' => ['changePassword' => ['target' => 'https://www.netresearch.de/passwort']],
        ]);
        $request = (new ServerRequest('https://www.netresearch.de/.well-known/change-password', 'GET'))
            ->withAttribute('site', $site);

        $response = (new ChangePasswordMiddleware())->process($request, $this->handler());

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://www.netresearch.de/passwort', $response->getHeaderLine('Location'));
    }

    public function testPassesThroughWhenUnconfigured(): void
    {
        $site    = new Site('main', 1, ['base' => 'https://www.netresearch.de/']);
        $request = (new ServerRequest('https://www.netresearch.de/.well-known/change-password', 'GET'))
            ->withAttribute('site', $site);

        self::assertSame(404, (new ChangePasswordMiddleware())->process($request, $this->handler())->getStatusCode());
    }

    public function testIgnoresOtherPaths(): void
    {
        $request = new ServerRequest('https://www.netresearch.de/kontakt', 'GET');

        self::assertSame(404, (new ChangePasswordMiddleware())->process($request, $this->handler())->getStatusCode());
    }
}
