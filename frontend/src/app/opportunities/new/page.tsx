"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { OpportunityFormFields } from "@/components/opportunities/OpportunityFormFields";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { ApiValidationError, toDisplayMessage } from "@/lib/api/errors";
import { companiesApi, contactsApi, opportunitiesApi } from "@/lib/api/resources";
import type { Company, Contact, OpportunityWritePayload } from "@/lib/api/types";

export default function NewOpportunityPage() {
  const router = useRouter();
  const [companies, setCompanies] = useState<Company[]>([]);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [values, setValues] = useState<OpportunityWritePayload>({
    title: "",
    status: "new",
    technologies: [],
  });
  const [validationError, setValidationError] = useState<ApiValidationError | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    Promise.all([
      companiesApi.list({ itemsPerPage: 100, "order[name]": "asc" }),
      contactsApi.list({ itemsPerPage: 100, "order[lastName]": "asc" }),
    ])
      .then(([companiesResult, contactsResult]) => {
        setCompanies(companiesResult.items);
        setContacts(contactsResult.items);
      })
      .catch((err) => setError(toDisplayMessage(err)));
  }, []);

  function onChange<K extends keyof OpportunityWritePayload>(
    field: K,
    value: OpportunityWritePayload[K],
  ) {
    setValues((prev) => ({ ...prev, [field]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setError(null);
    setValidationError(null);
    try {
      const created = await opportunitiesApi.create(values);
      router.push(`/opportunities/${created.id}`);
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
