<?php

declare(strict_types=1);

namespace App\Doctrine\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Fonction DQL personnalisée JSONB_CONTAINS(field, value).
 *
 * Utilisée pour tester si une colonne JSON (stockée comme tableau, ex.
 * `["PHP","Symfony"]`) contient un élément donné, en s'appuyant sur
 * l'opérateur natif PostgreSQL `@>` (containment) après cast en `jsonb`.
 *
 * Cette approche évite les faux positifs d'un simple LIKE sur le texte JSON
 * (ex. rechercher "PHP" ne doit pas matcher un futur tag "PHP7").
 *
 * Limite : la comparaison est sensible à la casse (comportement natif de
 * l'opérateur `@>` sur des chaînes JSON) ; le filtre applicatif (cf.
 * App\Filter\TechnologyFilter) est responsable de normaliser/valider les
 * valeurs en amont si une recherche insensible à la casse est nécessaire.
 */
final class JsonbContainsFunction extends FunctionNode
{
    private Node $field;
    private Node $value;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->field = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->value = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return sprintf(
            '(CAST(%s AS jsonb) @> CAST(%s AS jsonb))',
            $this->field->dispatch($sqlWalker),
            $this->value->dispatch($sqlWalker),
        );
    }
}
