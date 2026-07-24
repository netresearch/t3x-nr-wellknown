<?php

declare(strict_types=1);

namespace Netresearch\NrWellknown\Tests\Unit\Configuration;

use Netresearch\NrWellknown\Configuration\WellKnownConfig;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;

final class WellKnownConfigTest extends TestCase
{
    public function testReadsValuesFromSiteWithDefaults(): void
    {
        $site = new Site('main', 1, [
            'base'      => 'https://www.netresearch.de/',
            'wellknown' => [
                'security'       => ['contacts' => ['mailto:security@netresearch.de']],
                'changePassword' => ['target' => 'https://www.netresearch.de/passwort'],
            ],
        ]);
        $config = WellKnownConfig::fromSite($site);

        self::assertSame(['mailto:security@netresearch.de'], $config->securityContacts());
        self::assertSame(6, $config->expiresMonths());          // default
        self::assertTrue($config->gpcEnabled());                // default true
        self::assertSame('https://www.netresearch.de/passwort', $config->changePasswordTarget());
    }

    public function testAbsentSectionsYieldSafeDefaults(): void
    {
        $config = WellKnownConfig::fromSite(new Site('empty', 2, ['base' => 'https://x.test/']));
        self::assertSame([], $config->securityContacts());
        self::assertNull($config->changePasswordTarget());
        self::assertTrue($config->gpcEnabled());
    }
}
