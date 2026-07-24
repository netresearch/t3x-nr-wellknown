<?php

/*
 * This file is part of the package netresearch/nr-wellknown.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrWellknown\Tests\Unit\Resource;

use DateTimeImmutable;
use Netresearch\NrWellknown\Configuration\WellKnownConfig;
use Netresearch\NrWellknown\Resource\StaticResources;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\Site;

final class StaticResourcesTest extends TestCase
{
    /**
     * @param array<mixed> $wk
     */
    private function config(array $wk): WellKnownConfig
    {
        return WellKnownConfig::fromSite(new Site('s', 1, ['base' => 'https://x.test/', 'wellknown' => $wk]));
    }

    public function testGpcOnByDefault(): void
    {
        $json = StaticResources::gpcJson($this->config([]), new DateTimeImmutable('2026-07-24T00:00:00Z'));
        self::assertNotNull($json);
        self::assertSame(['gpc' => true, 'lastUpdate' => '2026-07-24'], json_decode($json, true));
    }

    public function testGpcDisabledYieldsNull(): void
    {
        self::assertNull(StaticResources::gpcJson($this->config(['gpc' => false]), new DateTimeImmutable('2026-07-24T00:00:00Z')));
    }

    public function testAgentSkillsOnlyWhenNonEmpty(): void
    {
        self::assertNull(StaticResources::agentSkillsJson($this->config([])));
        $json = StaticResources::agentSkillsJson($this->config(['agentSkills' => ['skills' => [['name' => 'x']]]]));
        self::assertNotNull($json);
        self::assertStringContainsString('"name": "x"', $json);
    }

    public function testInlineLlmsTextPassedThrough(): void
    {
        self::assertNull(StaticResources::llmsTxt($this->config([])));
        $txt = StaticResources::llmsTxt($this->config(['llms' => ['source' => '# Netresearch']]));
        self::assertSame("# Netresearch\n", $txt);
    }
}
