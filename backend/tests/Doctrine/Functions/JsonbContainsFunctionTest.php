<?php

declare(strict_types=1);

namespace App\Tests\Doctrine\Functions;

use App\Doctrine\Functions\JsonbContainsFunction;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\SqlWalker;
use PHPUnit\Framework\TestCase;

/**
 * JsonbContainsFunction::parse() a besoin d'un vrai Doctrine\ORM\Query\Parser
 * (lexer + tokens DQL), ce qui nécessite une infrastructure Doctrine complète
 * (EntityManager, métadonnées mappées...) pour être exercé de façon réaliste.
 * Plutôt que de monter cette infrastructure lourde pour un test unitaire,
 * on vérifie ici :
 *  - que la classe est bien une fonction DQL valide (extends FunctionNode) ;
 *  - que getSql() produit le SQL PostgreSQL attendu (CAST ... AS jsonb / @>)
 *    une fois les nœuds AST field/value renseignés (via Reflection, en
 *    s'affranchissant du parsing DQL lui-même) ;
 *  - l'enregistrement de la fonction est couvert séparément par
 *    config/packages/doctrine.yaml (revue manuelle / QA), la lecture de la
 *    configuration YAML n'apportant pas de valeur en test unitaire pur.
 */
final class JsonbContainsFunctionTest extends TestCase
{
    public function testExtendsDoctrineFunctionNode(): void
    {
        $function = new JsonbContainsFunction('JSONB_CONTAINS');

        self::assertInstanceOf(FunctionNode::class, $function);
    }

    public function testGetSqlGeneratesJsonbContainmentExpression(): void
    {
        $function = new JsonbContainsFunction('JSONB_CONTAINS');

        $this->setPrivateNode($function, 'field', 'o0_.technologies');
        $this->setPrivateNode($function, 'value', "'[\"PHP\"]'");

        $walker = $this->createMock(SqlWalker::class);

        $sql = $function->getSql($walker);

        self::assertSame(
            '(CAST(o0_.technologies AS jsonb) @> CAST(\'["PHP"]\' AS jsonb))',
            $sql,
        );
        self::assertStringContainsString('@>', $sql);
        self::assertStringContainsString('CAST(', $sql);
        self::assertStringContainsString('AS jsonb', $sql);
    }

    public function testGetSqlDelegatesFieldAndValueDispatchToTheSqlWalker(): void
    {
        $function = new JsonbContainsFunction('JSONB_CONTAINS');

        $fieldNode = $this->makeStubNode('field_sql');
        $valueNode = $this->makeStubNode(':param1');

        $reflection = new \ReflectionClass($function);
        $reflection->getProperty('field')->setValue($function, $fieldNode);
        $reflection->getProperty('value')->setValue($function, $valueNode);

        $walker = $this->createMock(SqlWalker::class);

        $sql = $function->getSql($walker);

        self::assertSame('(CAST(field_sql AS jsonb) @> CAST(:param1 AS jsonb))', $sql);
    }

    private function setPrivateNode(JsonbContainsFunction $function, string $property, string $literalSql): void
    {
        $reflection = new \ReflectionClass($function);
        $reflection->getProperty($property)->setValue($function, $this->makeStubNode($literalSql));
    }

    private function makeStubNode(string $dispatchResult): Node
    {
        return new class($dispatchResult) extends Node {
            public function __construct(private readonly string $dispatchResult)
            {
            }

            public function dispatch(SqlWalker $walker): string
            {
                return $this->dispatchResult;
            }
        };
    }
}
