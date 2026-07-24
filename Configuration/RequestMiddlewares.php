<?php

/*
 * This file is part of the package netresearch/nr-wellknown.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\NrWellknown\Middleware\ChangePasswordMiddleware;

return [
    'frontend' => [
        'netresearch/nr-wellknown/change-password' => [
            'target' => ChangePasswordMiddleware::class,
            'after'  => ['typo3/cms-frontend/site'],
            'before' => ['typo3/cms-frontend/base-redirect-resolver'],
        ],
    ],
];
