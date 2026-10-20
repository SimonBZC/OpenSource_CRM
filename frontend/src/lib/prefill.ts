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
