/**
 * Pré-remplissage des formulaires de création depuis l'URL (FR-4, FR-5) :
 * `/opportunities/new?contact=12&company=3`, `/follow-ups/new?contact=12`.
 * Les valeurs restent modifiables dans le formulaire.
 */

/** Lit un id numérique dans les paramètres d'URL, ou null s'il est absent ou invalide. */
export function readIdParam(searchParams: URLSearchParams | { get(name: string): string | null }, name: string): number | null {
  const raw = searchParams.get(name);
  if (!raw || !/^\d+$/.test(raw)) return null;
  return Number(raw);
}

/**
 * Garantit que l'élément pré-rempli figure dans les options d'un select :
 * les listes sont limitées à 100 éléments, l'élément manquant est alors
 * chargé individuellement et ajouté.
 */
export async function ensureOption<T extends { id: number }>(
  items: T[],
  id: number | null,
  fetchOne: (id: number) => Promise<T>,
): Promise<T[]> {
  if (id === null || items.some((item) => item.id === id)) return items;
  return [...items, await fetchOne(id)];
}

/** Titre par défaut d'une relance créée depuis la liste des contacts. */
export const DEFAULT_FOLLOW_UP_TITLE = "Relance commercial";

/**
 * Date prévue par défaut d'une relance créée depuis la liste des contacts :
 * aujourd'hui à 14h (heure locale), ou demain à 14h s'il est déjà 14h passées.
 * Renvoyée en ISO UTC, comme `fromDateTimeInputValue` (lib/format.ts).
 */
export function defaultFollowUpScheduledAt(now: Date = new Date()): string {
  const day = new Date(now);
  if (now.getHours() >= 14) day.setDate(day.getDate() + 1);
  day.setHours(14, 0, 0, 0);
  return day.toISOString();
}
