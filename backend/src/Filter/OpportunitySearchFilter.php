<?php

declare(strict_types=1);

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Filtre personnalisé de recherche textuelle sur Opportunity.
 *
 * Syntaxe : `?search=terme`.
 *
 * Comportement : renvoie les opportunités dont le `title` OU la `description`
 * contient le terme recherché, en correspondance partielle et insensible à la
 * casse (comparaison sur `LOWER(...)` des deux côtés). `description` étant
 * nullable, on protège explicitement le côté description avec un `IS NOT NULL`
 * implicite via la comparaison LIKE (une colonne NULL ne matche jamais un LIKE,
 * ce qui est le comportement voulu : seule la condition sur `title` doit alors
 * suffire à faire remonter la ligne, ce que garantit le OR).
 *
 * Ce filtre est indépendant du SearchFilter natif déclaré sur `title` et
 * `description` (comparaison AND, sensible à la casse) : il s'agit d'un
 * paramètre de requête distinct (`search`), pensé pour une barre de recherche
 * unique côté UI. Les deux filtres coexistent sans interférence.
 *
 * Les caractères spéciaux LIKE (`%` et `_`) saisis par l'utilisateur sont
 * échappés pour ne pas se comporter comme des jokers. La valeur est toujours
 * liée via un paramètre préparé (aucune concaténation SQL).
 */
final class OpportunitySearchFilter implements FilterInterface
{
    public const PARAMETER_NAME = 'search';

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $value = $context['filters'][self::PARAMETER_NAME] ?? null;

        if (!\is_string($value)) {
            return;
        }

        $search = trim($value);

        if ('' === $search) {
            return;
        }

        // Échappement des caractères spéciaux LIKE pour éviter qu'ils agissent comme des jokers.
        // On utilise `!` comme caractère d'échappement (plutôt que le backslash standard) :
        // le driver PDO pgsql interprète le backslash de façon incorrecte dans un littéral
        // ESCAPE '\', ce qui casse le comptage des paramètres positionnels de la requête.
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
        $pattern = '%'.mb_strtolower($escaped).'%';

        $alias = $queryBuilder->getRootAliases()[0];
        // Deux paramètres distincts (plutôt qu'un seul réutilisé deux fois) : la
        // réutilisation d'un même paramètre nommé à plusieurs endroits d'une
        // requête DQL peut entraîner une erreur de liaison ("parameter was not
        // defined") selon la façon dont le DQL est traduit en SQL positionnel.
        $titleParameter = $queryNameGenerator->generateParameterName(self::PARAMETER_NAME);
        $descriptionParameter = $queryNameGenerator->generateParameterName(self::PARAMETER_NAME);

        $queryBuilder
            ->andWhere(sprintf(
                "LOWER(%1\$s.title) LIKE :%2\$s ESCAPE '!' OR LOWER(%1\$s.description) LIKE :%3\$s ESCAPE '!'",
                $alias,
                $titleParameter,
                $descriptionParameter,
            ))
            ->setParameter($titleParameter, $pattern)
            ->setParameter($descriptionParameter, $pattern);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::PARAMETER_NAME => [
                'property' => null,
                'type' => 'string',
                'required' => false,
                'description' => "Recherche textuelle globale : renvoie les opportunités dont le titre OU la description contient le terme donné (correspondance partielle, insensible à la casse). Paramètre distinct des filtres `title`/`description` (SearchFilter natif, sensibles à la casse, combinés en AND).",
                'schema' => ['type' => 'string'],
            ],
        ];
    }
}
