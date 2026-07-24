<?php

/*
 * This file is part of the package netresearch/nr-wellknown.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrWellknown\Middleware;

use Netresearch\NrWellknown\Configuration\WellKnownConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Answer GET /.well-known/change-password with a 302 to the site-configured
 * password-change page. Reached only because the nginx .well-known location
 * falls through to TYPO3 for absent files. Any other path passes through, and
 * an unconfigured target passes through too (→ 404, correct).
 */
final class ChangePasswordMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getUri()->getPath() !== '/.well-known/change-password') {
            return $handler->handle($request);
        }

        $site = $request->getAttribute('site');
        if ($site instanceof Site) {
            $target = WellKnownConfig::fromSite($site)->changePasswordTarget();
            if ($target !== null) {
                return new RedirectResponse($target, 302);
            }
        }

        return $handler->handle($request);
    }
}
