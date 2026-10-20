"use client";

import { useState } from "react";
import { ApiValidationError, toDisplayMessage } from "@/lib/api/errors";
import { companyIri } from "@/lib/api/iri";
import { companiesApi } from "@/lib/api/resources";
import type { Company, ContactWritePayload } from "@/lib/api/types";

export const DUPLICATE_COMPANY_MESSAGE = "Cette entreprise existe déjà";

/** Normalisation de la règle de doublon : espaces de bord ignorés, casse ignorée. */
export function normalizeCompanyName(name: string): string {
  return name.trim().toLowerCase();
}

/**
 * Cherche une entreprise existante portant ce nom (casse et espaces de bord
 * ignorés). Le filtre `name` de l'API est une recherche partielle sensible à
 * la casse : il ne trouverait pas « ACME » pour « acme ». On parcourt donc
 * toutes les pages de `companiesApi.list()` et on compare côté client avec la
 * même normalisation que le backend (CompanyRepository::findOneByNameIgnoreCase).
 */
async function findCompanyByName(name: string): Promise<Company | null> {
  const target = normalizeCompanyName(name);
  const itemsPerPage = 100;
  let page = 1;
  for (;;) {
    const result = await companiesApi.list({ page, itemsPerPage });
    const match = result.items.find((company) => normalizeCompanyName(company.name) === target);
    if (match) return match;
    if (page * itemsPerPage >= result.totalItems || result.items.length === 0) return null;
    page += 1;
  }
}

export type InlineCompanyStatus = "idle" | "checking" | "available" | "duplicate";

export type InlineCompanyResult =
  | { ok: true; company: Company | null }
  | { ok: false };

/**
 * État et actions du bloc « Ou créer une entreprise » du formulaire Contact
 * (FR-1, FR-2, FR-3). Les erreurs non liées à la validation (réseau, 5xx)
 * sont propagées à l'appelant, qui les affiche dans son bandeau d'erreur.
 */
export function useInlineCompany() {
  const [name, setNameState] = useState("");
  const [sector, setSector] = useState("");
  const [status, setStatus] = useState<InlineCompanyStatus>("idle");
  const [error, setError] = useState<string | null>(null);

  function setName(value: string) {
    setNameState(value);
    setStatus("idle");
    setError(null);
  }

  function reset() {
    setNameState("");
    setSector("");
    setStatus("idle");
    setError(null);
  }

  /** Vérifie si le nom saisi est libre. Retourne true si disponible. */
  async function check(): Promise<boolean> {
    if (name.trim() === "") {
      setStatus("idle");
      setError(null);
      return false;
    }
    setStatus("checking");
    setError(null);
    try {
      const existing = await findCompanyByName(name);
      if (existing) {
        setStatus("duplicate");
        setError(DUPLICATE_COMPANY_MESSAGE);
        return false;
      }
      setStatus("available");
      return true;
    } catch (err) {
      setStatus("idle");
      throw err;
    }
  }

  /**
   * À l'enregistrement du contact : si un nom est saisi, re-vérifie le
   * doublon puis crée l'entreprise. `company: null` si aucun nom n'est saisi.
   */
  async function create(): Promise<InlineCompanyResult> {
    if (name.trim() === "") return { ok: true, company: null };

    const available = await check();
    if (!available) return { ok: false };

    try {
      const company = await companiesApi.create({
        name: name.trim(),
        sector: sector.trim() === "" ? null : sector.trim(),
      });
      return { ok: true, company };
    } catch (err) {
      if (err instanceof ApiValidationError) {
        const nameMessage = err.messageFor("name");
        setStatus(nameMessage ? "duplicate" : "idle");
        // Même message rouge que la vérification par « Rechercher » : une
        // violation sur `name` ne peut être qu'un doublon ici (nom non vide).
        setError(nameMessage ? DUPLICATE_COMPANY_MESSAGE : (err.messageFor("sector") ?? err.message));
        return { ok: false };
      }
      throw err;
    }
  }

  /**
   * Enregistre le contact (création ou mise à jour via `save`) en créant au
   * préalable l'entreprise saisie, si aucune entreprise existante n'est
   * choisie. Si l'enregistrement du contact échoue, l'entreprise créée est
   * supprimée (compensation, FR-2) et l'erreur du contact est propagée.
   *
   * Retourne null si l'entreprise n'a pas pu être créée (doublon, nom
   * invalide) : le message est alors affiché sous le champ.
   */
  async function saveContact<T>(
    values: ContactWritePayload,
    save: (payload: ContactWritePayload) => Promise<T>,
  ): Promise<{ saved: T; createdCompany: Company | null } | null> {
    let createdCompany: Company | null = null;
    if (!values.company) {
      const result = await create();
      if (!result.ok) return null;
      createdCompany = result.company;
    }

    try {
      const payload = createdCompany ? { ...values, company: companyIri(createdCompany.id) } : values;
      const saved = await save(payload);
      return { saved, createdCompany };
    } catch (err) {
      if (createdCompany) {
        try {
          await companiesApi.remove(createdCompany.id);
        } catch (rollbackError) {
          throw new Error(
            `Le contact n'a pas été enregistré, et l'entreprise « ${createdCompany.name} » créée pour lui n'a pas pu être supprimée (${toDisplayMessage(rollbackError)}). Supprimez-la depuis la page Entreprises.`,
          );
        }
      }
      throw err;
    }
  }

  return { name, sector, status, error, setName, setSector, check, saveContact, reset };
}

export type InlineCompanyController = ReturnType<typeof useInlineCompany>;
