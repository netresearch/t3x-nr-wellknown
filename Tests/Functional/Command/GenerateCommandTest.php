<?php

/*
 * This file is part of the package netresearch/nr-wellknown.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrWellknown\Tests\Functional\Command;

use Netresearch\NrWellknown\Command\GenerateCommand;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class GenerateCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['netresearch/nr-wellknown'];

    public function testWritesSecurityTxtAndGpcButNotExcluded(): void
    {
        $sitePath = Environment::getConfigPath() . '/sites/main';
        GeneralUtility::mkdir_deep($sitePath);
        file_put_contents($sitePath . '/config.yaml', <<<'YAML'
            base: 'https://www.netresearch.de/'
            rootPageId: 1
            wellknown:
              security:
                contacts:
                  - 'mailto:security@netresearch.de'
            YAML);

        $command = $this->get(GenerateCommand::class);
        self::assertInstanceOf(GenerateCommand::class, $command);
        (new CommandTester($command))->execute([]);

        $base = Environment::getPublicPath();
        self::assertFileExists($base . '/.well-known/security.txt');
        self::assertFileExists($base . '/.well-known/gpc.json');                    // default on
        self::assertFileDoesNotExist($base . '/.well-known/openid-configuration');  // never fabricated
        self::assertStringContainsString(
            'Contact: mailto:security@netresearch.de',
            (string) file_get_contents($base . '/.well-known/security.txt'),
        );
    }
}
