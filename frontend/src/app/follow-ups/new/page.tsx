"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { FollowUpFormFields } from "@/components/follow-ups/FollowUpFormFields";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { ApiValidationError, toDisplayMessage } from "@/lib/api/errors";
import { companiesApi, contactsApi, followUpsApi, opportunitiesApi } from "@/lib/api/resources";
import type { Company, Contact, FollowUpWritePayload, Opportunity } from "@/lib/api/types";

export default function NewFollowUpPage() {
  const router = useRouter();
  const [companies, setCompanies] = useState<Company[]>([]);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [opportunities, setOpportunities] = useState<Opportunity[]>([]);
  const [values, setValues] = useState<FollowUpWritePayload>({
    title: "",
    status: "todo",
  });
  const [validationError, setValidationError] = useState<ApiValidationError | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    Promise.all([
      companiesApi.list({ itemsPerPage: 100, "order[name]": "asc" }),
      contactsApi.list({ itemsPerPage: 100, "order[lastName]": "asc" }),
      opportunitiesApi.list({ itemsPerPage: 100, "order[createdAt]": "desc" }),
    ])
      .then(([companiesResult, contactsResult, opportunitiesResult]) => {
        setCompanies(companiesResult.items);
        setContacts(contactsResult.items);
        setOpportunities(opportunitiesResult.items);
      })
      .catch((err) => setError(toDisplayMessage(err)));
  }, []);

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
      router.push(`/follow-ups/${created.id}`);
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
