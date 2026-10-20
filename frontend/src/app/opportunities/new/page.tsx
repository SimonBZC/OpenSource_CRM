"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { OpportunityFormFields } from "@/components/opportunities/OpportunityFormFields";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { useNotify } from "@/components/ui/Toaster";
import { ApiValidationError, toDisplayMessage } from "@/lib/api/errors";
import { companyIri, contactIri } from "@/lib/api/iri";
import { companiesApi, contactsApi, opportunitiesApi } from "@/lib/api/resources";
import type { Company, Contact, OpportunityWritePayload } from "@/lib/api/types";
import { ensureOption, readIdParam } from "@/lib/prefill";

/**
 * `useSearchParams` impose un Suspense boundary en App Router : la lecture du
 * pré-remplissage (`?contact=…&company=…`, liens de la liste des contacts)
 * est isolée dans un composant enfant.
 */
export default function NewOpportunityPage() {
  return (
    <Suspense fallback={<div className="p-8 text-sm text-black/50">Chargement…</div>}>
      <NewOpportunityPageContent />
    </Suspense>
  );
}

function NewOpportunityPageContent() {
  const router = useRouter();
  const notify = useNotify();
  const searchParams = useSearchParams();
  const prefilledContactId = readIdParam(searchParams, "contact");
  const prefilledCompanyId = readIdParam(searchParams, "company");
  const [companies, setCompanies] = useState<Company[]>([]);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [values, setValues] = useState<OpportunityWritePayload>({
    title: "",
    status: "new",
    technologies: [],
    ...(prefilledContactId !== null && { contact: contactIri(prefilledContactId) }),
    ...(prefilledCompanyId !== null && { company: companyIri(prefilledCompanyId) }),
  });
  const [validationError, setValidationError] = useState<ApiValidationError | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    Promise.all([
      companiesApi.list({ itemsPerPage: 100, "order[name]": "asc" }),
      contactsApi.list({ itemsPerPage: 100, active: true, "order[lastName]": "asc" }),
    ])
      .then(([companiesResult, contactsResult]) =>
        Promise.all([
          ensureOption(companiesResult.items, prefilledCompanyId, (cid) => companiesApi.get(cid)),
          ensureOption(contactsResult.items, prefilledContactId, (cid) => contactsApi.get(cid)),
        ]),
      )
      .then(([companiesItems, contactsItems]) => {
        setCompanies(companiesItems);
        setContacts(contactsItems);
      })
      .catch((err) => setError(toDisplayMessage(err)));
  }, [prefilledCompanyId, prefilledContactId]);

  function onChange<K extends keyof OpportunityWritePayload>(
    field: K,
    value: OpportunityWritePayload[K],
  ) {
    setValues((prev) => ({ ...prev, [field]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setValidationError(null);
    try {
      const created = await opportunitiesApi.create(values);
      notify.success("Opportunité créée");
      router.push(`/opportunities/${created.id}`);
    } catch (err) {
      if (err instanceof ApiValidationError) {
        setValidationError(err);
      } else {
        notify.error(toDisplayMessage(err));
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="p-8">
      <h1 className="text-2xl font-bold tracking-tight">Nouvelle opportunité</h1>

      <form onSubmit={handleSubmit} className="mt-6 max-w-3xl space-y-4">
        <ErrorBanner message={error} />
        <div className="rounded-lg border border-black/10 bg-white p-6">
          <OpportunityFormFields
            values={values}
            onChange={onChange}
            validationError={validationError}
            companies={companies}
            contacts={contacts}
          />
        </div>
        <div className="flex gap-3">
          <button
            type="submit"
            disabled={submitting}
            className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {submitting ? "Création..." : "Créer l'opportunité"}
          </button>
        </div>
      </form>
    </div>
  );
}
