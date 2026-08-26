/** Formate une date ISO en date FR courte (ex. "21/08/2026"). */
export function formatDate(iso: string | null | undefined): string {
  if (!iso) return "—";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return "—";
  return date.toLocaleDateString("fr-FR");
}

/** Formate une date ISO en date + heure FR (ex. "21/08/2026 15:36"). */
export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return "—";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return "—";
  return date.toLocaleString("fr-FR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

/** Convertit une date ISO en valeur pour <input type="date">. */
export function toDateInputValue(iso: string | null | undefined): string {
  if (!iso) return "";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return "";
  return date.toISOString().slice(0, 10);
}

/**
 * Convertit une date ISO en valeur pour <input type="datetime-local">
 * ("YYYY-MM-DDTHH:mm"). Pas de conversion de fuseau : on prend directement
 * les composants de la chaîne ISO, cohérent avec la convention déjà utilisée
 * pour `startDate` (OpportunityFormFields) qui ignore aussi le fuseau.
 */
export function toDateTimeInputValue(iso: string | null | undefined): string {
  if (!iso || Number.isNaN(new Date(iso).getTime())) return "";
  return iso.slice(0, 16);
}

/** Convertit une valeur <input type="datetime-local"> en ISO (UTC+00:00). */
export function fromDateTimeInputValue(value: string): string | null {
  if (!value) return null;
  return `${value}:00+00:00`;
}

/** Formate une date ISO en indiquant si elle est passée (relance/tâche en retard). */
export function isOverdue(iso: string | null | undefined): boolean {
  if (!iso) return false;
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return false;
  return date.getTime() < Date.now();
}

/** Formate une fourchette de TJM. */
export function formatDailyRate(min: number | null, max: number | null): string {
  if (min && max) return `${min}–${max} €/j`;
  if (min) return `à partir de ${min} €/j`;
  if (max) return `jusqu'à ${max} €/j`;
  return "—";
}
