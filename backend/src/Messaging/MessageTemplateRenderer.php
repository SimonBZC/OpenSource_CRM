<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Entity\Contact;

/**
 * Remplace les indicateurs `{%x%}` d'un corps de message par les données d'un
 * contact. Service pur, sans dépendance à la relance ni à la base : il suffit
 * d'un corps et d'un contact (éventuellement absent).
 *
 * Règles :
 * - syntaxe exacte et sensible à la casse ; seuls les indicateurs de
 *   PLACEHOLDERS sont reconnus, tout autre `{%…%}` reste tel quel sans être
 *   signalé ;
 * - une donnée manquante (pas de contact, valeur vide ou faite d'espaces,
 *   contact sans entreprise) laisse l'indicateur visible et sa clé est listée
 *   dans `missing`, une seule fois, uniquement s'il figure dans le corps ;
 * - `{%entreprise%}` est l'entreprise du contact.
 */
final class MessageTemplateRenderer
{
    /**
     * Indicateurs reconnus : clé → libellé (miroir côté frontend :
     * frontend/src/lib/messagePlaceholders.ts).
     */
    public const PLACEHOLDERS = [
        'prenom' => 'Prénom',
        'nom' => 'Nom',
        'entreprise' => 'Entreprise',
    ];

    public function render(string $body, ?Contact $contact): RenderedMessage
    {
        $replacements = [];
        $missing = [];

        foreach (array_keys(self::PLACEHOLDERS) as $key) {
            $token = '{%'.$key.'%}';
            if (!str_contains($body, $token)) {
                continue;
            }

            $value = $this->valueFor($key, $contact);
            if (null === $value) {
                $missing[] = $key;
                continue;
            }

            $replacements[$token] = $value;
        }

        // strtr remplace tout en une passe : une valeur contenant elle-même un
        // indicateur (ex. un nom « {%prenom%} ») n'est pas réinterprétée.
        return new RenderedMessage(strtr($body, $replacements), $missing);
    }

    private function valueFor(string $key, ?Contact $contact): ?string
    {
        if (null === $contact) {
            return null;
        }

        $value = match ($key) {
            'prenom' => $contact->getFirstName(),
            'nom' => $contact->getLastName(),
            'entreprise' => $contact->getCompany()?->getName(),
            default => null,
        };

        if (null === $value || '' === trim($value)) {
            return null;
        }

        return $value;
    }
}
