"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { CompanyFormFields } from "@/components/companies/CompanyFormFields";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { ApiValidationError, toDisplayMessage } from "@/lib/api/errors";
import { companiesApi } from "@/lib/api/resources";
import type { CompanyWritePayload } from "@/lib/api/types";

export default function NewCompanyPage() {
  const router = useRouter();
  const [values, setValues] = useState<CompanyWritePayload>({ name: "" });
  const [validationError, setValidationError] = useState<ApiValidationError | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  function onChange<K extends keyof CompanyWritePayload>(
    field: K,
    value: CompanyWritePayload[K],
  ) {
    setValues((prev) => ({ ...prev, [field]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setError(null);
    setValidationError(null);
    try {
      const created = await companiesApi.create(values);
      router.push(`/companies/${created.id}`);
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
      <h1 className="text-2xl font-bold tracking-tight">Nouvelle entreprise</h1>

      <form onSubmit={handleSubmit} className="mt-6 max-w-2xl space-y-4">
        <ErrorBanner message={error} />
        <div className="rounded-lg border border-black/10 bg-white p-6">
          <CompanyFormFields
            values={values}
            onChange={onChange}
            validationError={validationError}
          />
        </div>
        <div className="flex gap-3">
          <button
            type="submit"
            disabled={submitting}
            className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {submitting ? "Création..." : "Créer l'entreprise"}
          </button>
        </div>
      </form>
    </div>
  );
}
