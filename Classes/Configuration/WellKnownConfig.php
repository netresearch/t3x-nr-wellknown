<?php

/*
 * This file is part of the package netresearch/nr-wellknown.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrWellknown\Configuration;

use TYPO3\CMS\Core\Site\Entity\Site;

use function array_filter;
use function array_values;
use function max;

/**
 * Immutable view of one site's `wellknown` configuration, with safe defaults.
 */
final readonly class WellKnownConfig
{
    /**
     * @param list<string> $securityContacts
     * @param list<string> $preferredLanguages
     * @param array<mixed> $agentSkills
     */
    public function __construct(
        private array $securityContacts,
        private ?string $securityPolicy,
        private array $preferredLanguages,
        private int $expiresMonths,
        private ?string $changePasswordTarget,
        private bool $gpcEnabled,
        private ?string $llmsSource,
        private array $agentSkills,
    ) {}

    public static function fromSite(Site $site): self
    {
        $wk = $site->getConfiguration()['wellknown'] ?? [];

        return new self(
            securityContacts: array_values(array_filter((array) ($wk['security']['contacts'] ?? []), 'is_string')),
            securityPolicy: isset($wk['security']['policy']) ? (string) $wk['security']['policy'] : null,
            preferredLanguages: array_values(array_filter((array) ($wk['security']['preferredLanguages'] ?? []), 'is_string')),
            expiresMonths: (int) ($wk['security']['expiresMonths'] ?? 6),
            changePasswordTarget: isset($wk['changePassword']['target']) ? (string) $wk['changePassword']['target'] : null,
            gpcEnabled: (bool) ($wk['gpc'] ?? true),
            llmsSource: isset($wk['llms']['source']) ? (string) $wk['llms']['source'] : null,
            agentSkills: (array) ($wk['agentSkills']['skills'] ?? []),
        );
    }

    /**
     * @return list<string>
     */
    public function securityContacts(): array
    {
        return $this->securityContacts;
    }

    public function securityPolicy(): ?string
    {
        return $this->securityPolicy;
    }

    /**
     * @return list<string>
     */
    public function preferredLanguages(): array
    {
        return $this->preferredLanguages;
    }

    public function expiresMonths(): int
    {
        return max(1, $this->expiresMonths);
    }

    public function changePasswordTarget(): ?string
    {
        return $this->changePasswordTarget;
    }

    public function gpcEnabled(): bool
    {
        return $this->gpcEnabled;
    }

    public function llmsSource(): ?string
    {
        return $this->llmsSource;
    }

    /**
     * @return array<mixed>
     */
    public function agentSkills(): array
    {
        return $this->agentSkills;
    }
}
