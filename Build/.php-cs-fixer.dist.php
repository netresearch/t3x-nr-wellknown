<?php

$createConfig = require __DIR__ . '/../.build/vendor/netresearch/typo3-ci-workflows/config/php-cs-fixer/config.php';

$config = $createConfig(<<<'EOF'
    This file is part of the package netresearch/nr-wellknown.

    For the full copyright and license information, please read the
    LICENSE file that was distributed with this source code.
    EOF, __DIR__ . '/..');

// The shared factory excludes .Build/config/node_modules/var. This extension
// uses a lowercase .build vendor dir, and TYPO3's composer installer generates
// a public/ web root and a packages/ dir — none of which is our source. Exclude
// them so the generated public/index.php cannot fail the check on a fresh install.
$config->getFinder()->exclude(['.build', 'public', 'packages']);

return $config;
