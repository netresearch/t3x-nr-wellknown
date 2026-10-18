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

$createConfig = require __DIR__ . '/../.build/vendor/netresearch/typo3-ci-workflows/config/php-cs-fixer/config.php';

// The shared factory already excludes .build and public; TYPO3's composer
// installer also generates a packages/ dir, which is not our source.
return $createConfig(<<<'EOF'
    This file is part of the package netresearch/nr-wellknown.

    SPDX-License-Identifier: GPL-2.0-or-later
    SPDX-FileCopyrightText: Netresearch DTT GmbH

    For the full copyright and license information, please read the
    LICENSE file that was distributed with this source code.
    EOF, __DIR__ . '/..', [], ['packages']);
