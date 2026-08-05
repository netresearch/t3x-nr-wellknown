<?php

/*
 * This file is part of the package netresearch/nr-wellknown.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;

$configure = require_once __DIR__ . '/../.build/vendor/netresearch/typo3-ci-workflows/config/rector/rector.php';

return static function (RectorConfig $rectorConfig) use ($configure): void {
    // Shared org base config: paths, code-quality sets, rule skips,
    // and the package's ergebnis-free phpstan-rector.neon.
    $configure($rectorConfig, __DIR__ . '/..');

    // The shared default path list does not include Tests/ — this repo
    // scans it, so override (paths() replaces, so list everything).
    $rectorConfig->paths(array_merge(
        [
            __DIR__ . '/../Classes',
            __DIR__ . '/../Configuration',
            __DIR__ . '/../Tests',
        ],
        glob(__DIR__ . '/../ext_*.php') ?: [],
    ));

    $rectorConfig->disableParallel();

    $rectorConfig->sets([
        Typo3LevelSetList::UP_TO_TYPO3_13,
    ]);
};
