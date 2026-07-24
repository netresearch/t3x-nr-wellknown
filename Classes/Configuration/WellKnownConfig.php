<?php

/*
 * This file is part of the package netresearch/nr-wellknown.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrWellknown\Configuration;

use function array_filter;
use function array_key_exists;
use function array_values;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function max;

use TYPO3\CMS\Core\Site\Entity\Site;

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
        $wk       = self::section($site->getConfiguration(), 'wellknown');
        $security = self::section($wk, 'security');
        $change   = self::section($wk, 'changePassword');
        $llms     = self::section($wk, 'llms');
        $agent    = self::section($wk, 'agentSkills');

        return new self(
            securityContacts: array_values(array_filter(self::listOf($security, 'contacts'), is_string(...))),
            securityPolicy: self::stringOrNull($security, 'policy'),
            preferredLanguages: array_values(array_filter(self::listOf($security, 'preferredLanguages'), is_string(...))),
            expiresMonths: self::intOr($security, 'expiresMonths', 6),
            changePasswordTarget: self::stringOrNull($change, 'target'),
            gpcEnabled: self::boolOr($wk, 'gpc', true),
            llmsSource: self::stringOrNull($llms, 'source'),
            agentSkills: self::listOf($agent, 'skills'),
        );
    }

    /**
     * @param array<mixed> $a
     *
     * @return array<mixed>
     */
    private static function section(array $a, string $key): array
    {
        $value = $a[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<mixed> $a
     *
     * @return list<mixed>
     */
    private static function listOf(array $a, string $key): array
    {
        $value = $a[$key] ?? null;

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @param array<mixed> $a
     */
    private static function stringOrNull(array $a, string $key): ?string
    {
        $value = $a[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param array<mixed> $a
     */
    private static function intOr(array $a, string $key, int $default): int
    {
        $value = $a[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param array<mixed> $a
     */
    private static function boolOr(array $a, string $key, bool $default): bool
    {
        return array_key_exists($key, $a) ? (bool) $a[$key] : $default;
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
