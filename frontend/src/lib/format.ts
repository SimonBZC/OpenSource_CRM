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
 * ("YYYY-MM-DDTHH:mm"), exprimée dans le fuseau du navigateur : l'heure
 * affichée dans le champ est la même que dans les listes (formatDateTime).
 */
export function toDateTimeInputValue(iso: string | null | undefined): string {
  if (!iso) return "";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return "";
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/**
 * Convertit une valeur <input type="datetime-local"> (heure locale du
 * navigateur) en ISO UTC. Toujours envoyer de l'UTC : les colonnes Doctrine
 * sont sans fuseau et le backend relit les dates en UTC.
 */
export function fromDateTimeInputValue(value: string): string | null {
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return null;
  return date.toISOString();
}

/** Formate une date ISO en indiquant si elle est passée (relance/alerte en retard). */
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
