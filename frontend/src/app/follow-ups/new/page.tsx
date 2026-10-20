"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { FollowUpFormFields } from "@/components/follow-ups/FollowUpFormFields";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { ApiValidationError, toDisplayMessage } from "@/lib/api/errors";
import { companyIri, contactIri } from "@/lib/api/iri";
import { companiesApi, contactsApi, followUpsApi, opportunitiesApi } from "@/lib/api/resources";
import type { Company, Contact, FollowUpWritePayload, Opportunity } from "@/lib/api/types";
import {
  DEFAULT_FOLLOW_UP_TITLE,
  defaultFollowUpScheduledAt,
  ensureOption,
  readIdParam,
} from "@/lib/prefill";

/**
 * `useSearchParams` impose un Suspense boundary en App Router : la lecture du
 * pré-remplissage (`?contact=…&company=…`, liens de la liste des contacts)
 * est isolée dans un composant enfant.
 */
export default function NewFollowUpPage() {
  return (
    <Suspense fallback={<div className="p-8 text-sm text-black/50">Chargement…</div>}>
      <NewFollowUpPageContent />
    </Suspense>
  );
}

function NewFollowUpPageContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const prefilledContactId = readIdParam(searchParams, "contact");
  const prefilledCompanyId = readIdParam(searchParams, "company");
  const [companies, setCompanies] = useState<Company[]>([]);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [opportunities, setOpportunities] = useState<Opportunity[]>([]);
  // Arrivée depuis la colonne Actions de la liste des contacts : titre et
  // date prévue par défaut (toujours modifiables).
  const [values, setValues] = useState<FollowUpWritePayload>(() => ({
    title: prefilledContactId !== null ? DEFAULT_FOLLOW_UP_TITLE : "",
    status: "todo",
    ...(prefilledContactId !== null && { scheduledAt: defaultFollowUpScheduledAt() }),
    ...(prefilledContactId !== null && { contact: contactIri(prefilledContactId) }),
    ...(prefilledCompanyId !== null && { company: companyIri(prefilledCompanyId) }),
  }));
  const [validationError, setValidationError] = useState<ApiValidationError | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    Promise.all([
      companiesApi.list({ itemsPerPage: 100, "order[name]": "asc" }),
      contactsApi.list({ itemsPerPage: 100, "order[lastName]": "asc" }),
      opportunitiesApi.list({ itemsPerPage: 100, "order[createdAt]": "desc" }),
    ])
      .then(([companiesResult, contactsResult, opportunitiesResult]) =>
        Promise.all([
          ensureOption(companiesResult.items, prefilledCompanyId, (cid) => companiesApi.get(cid)),
          ensureOption(contactsResult.items, prefilledContactId, (cid) => contactsApi.get(cid)),
          opportunitiesResult.items,
        ]),
      )
      .then(([companiesItems, contactsItems, opportunitiesItems]) => {
        setCompanies(companiesItems);
        setContacts(contactsItems);
        setOpportunities(opportunitiesItems);
      })
      .catch((err) => setError(toDisplayMessage(err)));
  }, [prefilledCompanyId, prefilledContactId]);

  function onChange<K extends keyof FollowUpWritePayload>(
    field: K,
    value: FollowUpWritePayload[K],
  ) {
    setValues((prev) => ({ ...prev, [field]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setError(null);
    setValidationError(null);
    try {
      const created = await followUpsApi.create(values);
      // Depuis la colonne Actions de la liste des contacts : retour à cette liste.
      router.push(prefilledContactId !== null ? "/contacts" : `/follow-ups/${created.id}`);
    } catch (err) {
      if (err instanceof ApiValidationError) {
        setValidationError(err);
      } else {
        setError(toDisplayMessage(err));
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="p-8">
      <h1 className="text-2xl font-bold tracking-tight">Nouvelle relance</h1>

      <form onSubmit={handleSubmit} className="mt-6 max-w-3xl space-y-4">
        <ErrorBanner message={error} />
        <div className="rounded-lg border border-black/10 bg-white p-6">
          <FollowUpFormFields
            values={values}
            onChange={onChange}
            validationError={validationError}
            companies={companies}
            contacts={contacts}
            opportunities={opportunities}
          />
        </div>
        <div className="flex gap-3">
          <button
            type="submit"
            disabled={submitting}
            className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {submitting ? "Création..." : "Créer la relance"}
          </button>
        </div>
      </form>
    </div>
  );
}
