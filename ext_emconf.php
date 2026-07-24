<?php

/*
 * This file is part of the package netresearch/nr-wellknown.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

$EM_CONF['nr_wellknown'] = [
    'title'          => 'Netresearch: Well-Known Resources',
    'description'    => 'Serve the well-known resources a TYPO3 site should provide, from per-site configuration.',
    'category'       => 'fe',
    'author'         => 'Team der Netresearch DTT GmbH',
    'author_email'   => '',
    'author_company' => 'Netresearch DTT GmbH',
    'state'          => 'beta',
    'version'        => '0.1.0',
    'constraints'    => [
        'depends'   => [
            'php'   => '8.2.0-8.99.99',
            'typo3' => '13.4.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests'  => [],
    ],
];
