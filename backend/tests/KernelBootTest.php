<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Smoke test: vérifie uniquement que le Kernel Symfony boote correctement
 * en environnement "test", sans aucune logique métier.
 *
 * Sert de garde-fou pour prouver que la chaîne de test (PHPUnit + bridge
 * Symfony + configuration) est réellement fonctionnelle, la Phase 1
 * n'introduisant aucune entité ni comportement métier à tester.
 */
final class KernelBootTest extends KernelTestCase
{
    public function testKernelBootsInTestEnvironment(): void
    {
        self::bootKernel();

        $kernel = self::$kernel;

        self::assertNotNull($kernel);
        self::assertSame('test', $kernel->getEnvironment());
        self::assertTrue($kernel->getContainer()->has('kernel'));
    }
}
