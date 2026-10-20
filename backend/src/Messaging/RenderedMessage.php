<?php

declare(strict_types=1);

namespace App\Messaging;

/**
 * Résultat du rendu d'un modèle de message.
 */
final class RenderedMessage
{
    /**
     * @param string       $body    corps rendu ; les indicateurs dont la donnée
     *                              manque y restent tels quels
     * @param list<string> $missing clés des indicateurs présents dans le corps
     *                              mais sans donnée (ex. `prenom`), sans doublon,
     *                              dans l'ordre de App\Messaging\MessageTemplateRenderer::PLACEHOLDERS
     */
    public function __construct(
        public readonly string $body,
        public readonly array $missing,
    ) {
    }
}
