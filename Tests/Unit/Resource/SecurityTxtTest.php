<?php

declare(strict_types=1);

namespace Netresearch\NrWellknown\Tests\Unit\Resource;

use Netresearch\NrWellknown\Configuration\WellKnownConfig;
use Netresearch\NrWellknown\Resource\SecurityTxt;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;

final class SecurityTxtTest extends TestCase
{
    /**
     * @param array<mixed> $wk
     */
    private function config(array $wk): WellKnownConfig
    {
        return WellKnownConfig::fromSite(new Site('s', 1, ['base' => 'https://x.test/', 'wellknown' => $wk]));
    }

    public function testRendersRfc9116WithFutureExpires(): void
    {
        $now  = new \DateTimeImmutable('2026-07-24T09:00:00Z');
        $body = SecurityTxt::render($this->config([
            'security' => ['contacts' => ['mailto:security@x.test'], 'policy' => 'https://x.test/p', 'preferredLanguages' => ['de', 'en']],
        ]), $now);

        self::assertNotNull($body);
        self::assertStringContainsString('Contact: mailto:security@x.test', $body);
        self::assertStringContainsString('Policy: https://x.test/p', $body);
        self::assertStringContainsString('Preferred-Languages: de, en', $body);
        self::assertStringContainsString('Expires: 2027-01-24T00:00:00Z', $body); // now + 6 months, normalised
    }

    public function testNoContactYieldsNull(): void
    {
        self::assertNull(SecurityTxt::render($this->config([]), new \DateTimeImmutable('2026-07-24T09:00:00Z')));
    }
}
