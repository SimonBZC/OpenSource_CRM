"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { AlertFormFields } from "@/components/alerts/AlertFormFields";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { ApiValidationError, toDisplayMessage } from "@/lib/api/errors";
import { contactsApi, opportunitiesApi, alertsApi } from "@/lib/api/resources";
import type { Contact, Opportunity, AlertWritePayload } from "@/lib/api/types";

export default function NewAlertPage() {
  const router = useRouter();
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [opportunities, setOpportunities] = useState<Opportunity[]>([]);
  const [values, setValues] = useState<AlertWritePayload>({
    title: "",
    status: "todo",
  });
  const [validationError, setValidationError] = useState<ApiValidationError | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    Promise.all([
      contactsApi.list({ itemsPerPage: 100, "order[lastName]": "asc" }),
      opportunitiesApi.list({ itemsPerPage: 100, "order[createdAt]": "desc" }),
    ])
      .then(([contactsResult, opportunitiesResult]) => {
        setContacts(contactsResult.items);
        setOpportunities(opportunitiesResult.items);
      })
      .catch((err) => setError(toDisplayMessage(err)));
  }, []);

  function onChange<K extends keyof AlertWritePayload>(field: K, value: AlertWritePayload[K]) {
    setValues((prev) => ({ ...prev, [field]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setError(null);
    setValidationError(null);
    try {
      const created = await alertsApi.create(values);
      router.push(`/alerts/${created.id}`);
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
      <h1 className="text-2xl font-bold tracking-tight">Nouvelle alerte</h1>

      <form onSubmit={handleSubmit} className="mt-6 max-w-3xl space-y-4">
        <ErrorBanner message={error} />
        <div className="rounded-lg border border-black/10 bg-white p-6">
          <AlertFormFields
            values={values}
            onChange={onChange}
            validationError={validationError}
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
            {submitting ? "Création..." : "Créer l'alerte"}
          </button>
        </div>
      </form>
    </div>
  );
}
