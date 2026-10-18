<?php

/*
 * This file is part of the package netresearch/nr-wellknown.
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

// Included by Build/phpstan.neon. CI removes netresearch/typo3-ci-workflows
// on the TYPO3 ^12.4 cells (remove-dev-deps in .github/workflows/ci.yml),
// because its saschaegerer/phpstan-typo3 requires typo3/cms-core ^13.4.3.
// A plain NEON include of the shared config would fail there on a missing
// file, so the include is made only when the package is installed. Without
// it, the parameters the shared phpstan-base.neon would set keep the ^12.4
// cells at the same level as before.
$shared = __DIR__ . '/../.build/vendor/netresearch/typo3-ci-workflows/config/phpstan/phpstan.neon';

if (is_file($shared)) {
    return ['includes' => [$shared]];
}

return [
    'parameters' => [
        'level'                        => 10,
        'treatPhpDocTypesAsCertain'    => false,
        'reportUnmatchedIgnoredErrors' => true,
        // The explicit analyseAndScan key is needed here: a plain list that
        // works in NEON arrives empty from a PHP config file.
        'excludePaths' => ['analyseAndScan' => [
            '%currentWorkingDirectory%/.build/*',
            '%currentWorkingDirectory%/ext_emconf.php',
        ]],
    ],
];
