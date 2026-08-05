<?php

/*
 * This file is part of the package netresearch/nr-wellknown.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrWellknown\Command;

use DateTimeImmutable;
use DateTimeZone;

use function dirname;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;

use Netresearch\NrWellknown\Configuration\WellKnownConfig;
use Netresearch\NrWellknown\Resource\SecurityTxt;
use Netresearch\NrWellknown\Resource\StaticResources;

use function sprintf;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Site\SiteFinder;

use function unlink;

/**
 * Write the configured well-known resources into the docroot. Run at deploy
 * time; re-running refreshes security.txt's Expires. A resource is only written
 * when its renderer returns content, so excluded resources are never created.
 */
#[AsCommand(
    name: 'nr:wellknown:generate',
    description: 'Write the configured well-known resources into the docroot.',
)]
final class GenerateCommand extends Command
{
    public function __construct(private readonly SiteFinder $siteFinder)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io        = new SymfonyStyle($input, $output);
        $now       = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $wellKnown = Environment::getPublicPath() . '/.well-known';
        $written   = 0;

        foreach ($this->siteFinder->getAllSites() as $site) {
            $config = WellKnownConfig::fromSite($site);

            // .well-known is filesystem-single per docroot; the first site wins.
            $written += $this->put($wellKnown . '/security.txt', SecurityTxt::render($config, $now));
            $written += $this->put($wellKnown . '/gpc.json', StaticResources::gpcJson($config, $now));
            $written += $this->put($wellKnown . '/agent-skills.json', StaticResources::agentSkillsJson($config));
            $written += $this->put(Environment::getPublicPath() . '/llms.txt', StaticResources::llmsTxt($config));

            break;
        }

        $io->success(sprintf('Wrote %d well-known resource(s).', $written));

        return Command::SUCCESS;
    }

    /**
     * Write when content is non-null; delete a now-unemitted file.
     *
     * @return int 1 when a file was written, else 0
     */
    private function put(string $path, ?string $content): int
    {
        if ($content === null) {
            if (is_file($path)) {
                // No user input reaches $path: every call site appends a
                // literal file name to Environment::getPublicPath(), so the
                // rule's premise — a path an actor can influence — does not
                // hold. The suppression has to sit on the line directly above
                // the call; a comment block with the token on its first line
                // is not recognised.
                // nosemgrep: php.lang.security.unlink-use.unlink-use
                unlink($path);
            }

            return 0;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }

        file_put_contents($path, $content);

        return 1;
    }
}
