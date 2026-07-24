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
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function array_values;
use function file_get_contents;
use function is_file;
use function json_encode;
use function rtrim;
use function str_contains;
use function str_starts_with;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

/**
 * Render the static well-known resources. Each method returns null when the
 * resource must not be emitted, so a site never ships a fabricated file.
 */
final class StaticResources
{
    public static function gpcJson(WellKnownConfig $c, \DateTimeImmutable $now): ?string
    {
        if (!$c->gpcEnabled()) {
            return null;
        }

        return json_encode(
            ['gpc' => true, 'lastUpdate' => $now->format('Y-m-d')],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . "\n";
    }

    public static function llmsTxt(WellKnownConfig $c): ?string
    {
        $source = $c->llmsSource();
        if ($source === null) {
            return null;
        }

        // EXT:/absolute file reference, else treat the value as inline content.
        $resolved = str_contains($source, ':') || str_starts_with($source, '/')
            ? GeneralUtility::getFileAbsFileName($source)
            : '';

        if ($resolved !== '' && is_file($resolved)) {
            return (string) file_get_contents($resolved);
        }

        return rtrim($source) . "\n";
    }

    public static function agentSkillsJson(WellKnownConfig $c): ?string
    {
        $skills = $c->agentSkills();
        if ($skills === []) {
            return null;
        }

        return json_encode(['skills' => array_values($skills)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
