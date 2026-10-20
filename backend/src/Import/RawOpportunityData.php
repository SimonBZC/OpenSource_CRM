<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Représentation brute, standardisée et indépendante de la source, d'une
 * annonce d'opportunité à importer.
 *
 * Volontairement pauvre en validation : ce DTO ne fait AUCUNE vérification
 * métier (y compris sur `title`, qui peut être vide ici). C'est le rôle de
 * l'importeur concret (ex. App\Import\ManualImporter), qui valide l'entité
 * Doctrine résultante avec le Validator Symfony AVANT toute persistance.
 */
final readonly class RawOpportunityData
{
    /**
     * @param array<int, string> $technologies
     */
    public function __construct(
        public string $title = '',
        public ?string $description = null,
        public ?string $companyName = null,
        public ?int $dailyRate = null,
        public bool $remote = false,
        public ?string $location = null,
        public array $technologies = [],
        public ?\DateTimeImmutable $postedAt = null,
    ) {
    }

    /**
     * Fabrique tolérante : les clés absentes du tableau (ex. décodé depuis
     * un JSON de commande console) prennent une valeur par défaut sûre au
     * lieu de lever une erreur. Les clés reconnues sont volontairement en
     * snake_case (format naturel d'un JSON externe/CLI), mappées vers les
     * propriétés camelCase du DTO.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: self::toStringOrDefault($data['title'] ?? null, ''),
            description: self::toStringOrNull($data['description'] ?? null),
            companyName: self::toStringOrNull($data['company_name'] ?? $data['companyName'] ?? null),
            dailyRate: self::toIntOrNull($data['daily_rate'] ?? $data['dailyRate'] ?? null),
            remote: self::toBool($data['remote'] ?? false),
            location: self::toStringOrNull($data['location'] ?? null),
            technologies: self::toStringList($data['technologies'] ?? []),
            postedAt: self::toDateOrNull($data['posted_at'] ?? $data['postedAt'] ?? null),
        );
    }

    private static function toStringOrDefault(mixed $value, string $default): string
    {
        return \is_string($value) ? $value : $default;
    }

    private static function toStringOrNull(mixed $value): ?string
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        return $value;
    }

    private static function toIntOrNull(mixed $value): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (\is_int($value)) {
            return $value;
        }

        if (\is_float($value) || (\is_string($value) && is_numeric($value))) {
            return (int) $value;
        }

        return null;
    }

    private static function toBool(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_string($value)) {
            return \in_array(strtolower($value), ['1', 'true', 'yes', 'oui'], true);
        }

        return (bool) $value;
    }

    /**
     * @return array<int, string>
     */
    private static function toStringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => \is_string($item) && '' !== $item));
    }

    private static function toDateOrNull(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
