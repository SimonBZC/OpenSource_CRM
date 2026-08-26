<?php

declare(strict_types=1);

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Filtre personnalisé pour Opportunity::$technologies (colonne JSON, array<int,string>).
 *
 * Syntaxe : `?technology=PHP` ou `?technology[]=PHP&technology[]=Symfony` (correspondance OR :
 * l'opportunité doit contenir au moins une des technologies demandées).
 *
 * Implémentation : s'appuie sur la fonction DQL personnalisée JSONB_CONTAINS
 * (voir App\Doctrine\Functions\JsonbContainsFunction) qui utilise l'opérateur
 * PostgreSQL `@>` sur un cast jsonb, garantissant une correspondance exacte
 * d'élément de tableau (pas de faux positif du type "PHP" matchant "PHP7").
 *
 * Limite connue : la comparaison est sensible à la casse. Un client recherchant
 * "php" ne trouvera pas les opportunités taguées "PHP". Ce choix a été fait pour
 * rester simple et prévisible ; à revoir avec l'orchestrateur si une recherche
 * insensible à la casse est requise (nécessiterait de normaliser les technologies
 * à l'écriture ou de comparer sur une projection lower() côté SQL).
 */
final class TechnologyFilter implements FilterInterface
{
    public const PARAMETER_NAME = 'technology';

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $value = $context['filters'][self::PARAMETER_NAME] ?? null;

        if (null === $value || '' === $value) {
            return;
        }

        $technologies = array_values(array_filter(
            array_map('trim', (array) $value),
            static fn (string $technology): bool => '' !== $technology,
        ));

        if ([] === $technologies) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $orExpression = $queryBuilder->expr()->orX();

        foreach ($technologies as $technology) {
            $parameterName = $queryNameGenerator->generateParameterName('technology');
            $orExpression->add(sprintf('JSONB_CONTAINS(%s.technologies, :%s) = TRUE', $alias, $parameterName));
            $queryBuilder->setParameter($parameterName, json_encode([$technology], \JSON_THROW_ON_ERROR));
        }

        $queryBuilder->andWhere($orExpression);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::PARAMETER_NAME => [
                'property' => 'technologies',
                'type' => 'string',
                'required' => false,
                'is_collection' => true,
                'description' => 'Filtre les opportunités contenant la technologie donnée (correspondance exacte, sensible à la casse). Répétable : technology[]=PHP&technology[]=Symfony (OR).',
                'schema' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
        ];
    }
}
