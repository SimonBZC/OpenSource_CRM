/**
 * Helpers pour manipuler les IRIs API Platform (ex. "/api/companies/8").
 * Les relations sont exposées et attendues en écriture sous forme d'IRI,
 * jamais d'id nu.
 */

/** Extrait l'id numérique final d'une IRI ("/api/companies/8" -> 8). */
export function idFromIri(iri: string | null | undefined): number | null {
  if (!iri) return null;
  const match = iri.match(/\/(\d+)$/);
  if (!match) return null;
  const id = Number(match[1]);
  return Number.isNaN(id) ? null : id;
}

/** Construit une IRI de ressource à partir d'un segment et d'un id. */
export function toIri(resourceSegment: string, id: number | string): string {
  return `/api/${resourceSegment}/${id}`;
}

export const companyIri = (id: number | string) => toIri("companies", id);
export const contactIri = (id: number | string) => toIri("contacts", id);
export const opportunityIri = (id: number | string) => toIri("opportunities", id);
