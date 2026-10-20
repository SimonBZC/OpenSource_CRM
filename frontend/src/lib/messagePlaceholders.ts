/**
 * Indicateurs utilisables dans le corps d'un message pré-construit.
 * Miroir de `MessageTemplateRenderer::PLACEHOLDERS` (backend) : la
 * substitution elle-même est faite côté backend.
 */
export interface MessagePlaceholder {
  /** Clé renvoyée par l'API dans `missing` (ex. "prenom"). */
  key: string;
  /** Libellé affiché dans l'éditeur. */
  label: string;
  /** Libellé utilisé dans l'avertissement des données manquantes. */
  missingLabel: string;
}

export const MESSAGE_PLACEHOLDERS: MessagePlaceholder[] = [
  { key: "prenom", label: "Prénom", missingLabel: "prénom" },
  { key: "nom", label: "Nom", missingLabel: "nom" },
  { key: "entreprise", label: "Entreprise", missingLabel: "entreprise" },
];

/** Indicateur à insérer dans le corps (syntaxe exacte, sensible à la casse). */
export function placeholderToken(key: string): string {
  return `{%${key}%}`;
}

/** Libellé lisible d'une donnée manquante (clé inconnue : clé brute). */
export function missingPlaceholderLabel(key: string): string {
  return MESSAGE_PLACEHOLDERS.find((p) => p.key === key)?.missingLabel ?? key;
}
