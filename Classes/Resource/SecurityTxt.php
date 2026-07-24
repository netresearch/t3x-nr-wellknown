<?php

/*
 * This file is part of the package netresearch/nr-wellknown.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrWellknown\Resource;

use Netresearch\NrWellknown\Configuration\WellKnownConfig;

use function implode;
use function sprintf;

/**
 * Render an RFC 9116 security.txt. Expires is always recomputed as
 * now + expiresMonths, normalised to 00:00:00Z, so a re-generated file never lapses.
 */
final class SecurityTxt
{
    public static function render(WellKnownConfig $c, \DateTimeImmutable $now): ?string
    {
        if ($c->securityContacts() === []) {
            return null;
        }

        $expires = $now->modify(sprintf('+%d months', $c->expiresMonths()))
            ->setTime(0, 0, 0)
            ->format('Y-m-d\TH:i:s\Z');

        $lines = [];
        foreach ($c->securityContacts() as $contact) {
            $lines[] = 'Contact: ' . $contact;
        }

        $lines[] = 'Expires: ' . $expires;

        if ($c->preferredLanguages() !== []) {
            $lines[] = 'Preferred-Languages: ' . implode(', ', $c->preferredLanguages());
        }

        if ($c->securityPolicy() !== null) {
            $lines[] = 'Policy: ' . $c->securityPolicy();
        }

        return implode("\n", $lines) . "\n";
    }
}
