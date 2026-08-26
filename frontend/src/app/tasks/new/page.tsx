"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { TaskFormFields } from "@/components/tasks/TaskFormFields";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { ApiValidationError, toDisplayMessage } from "@/lib/api/errors";
import { contactsApi, opportunitiesApi, tasksApi } from "@/lib/api/resources";
import type { Contact, Opportunity, TaskWritePayload } from "@/lib/api/types";

export default function NewTaskPage() {
  const router = useRouter();
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [opportunities, setOpportunities] = useState<Opportunity[]>([]);
  const [values, setValues] = useState<TaskWritePayload>({
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

  function onChange<K extends keyof TaskWritePayload>(field: K, value: TaskWritePayload[K]) {
    setValues((prev) => ({ ...prev, [field]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setError(null);
    setValidationError(null);
    try {
      const created = await tasksApi.create(values);
      router.push(`/tasks/${created.id}`);
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
      <h1 className="text-2xl font-bold tracking-tight">Nouvelle tâche</h1>

      <form onSubmit={handleSubmit} className="mt-6 max-w-3xl space-y-4">
        <ErrorBanner message={error} />
        <div className="rounded-lg border border-black/10 bg-white p-6">
          <TaskFormFields
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
            {submitting ? "Création..." : "Créer la tâche"}
          </button>
        </div>
      </form>
    </div>
  );
}
