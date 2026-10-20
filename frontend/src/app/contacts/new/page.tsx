"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { ContactFormFields } from "@/components/contacts/ContactFormFields";
import { useInlineCompany } from "@/components/contacts/useInlineCompany";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { ApiValidationError, toDisplayMessage } from "@/lib/api/errors";
import { companiesApi, contactsApi } from "@/lib/api/resources";
import type { Company, ContactWritePayload } from "@/lib/api/types";

export default function NewContactPage() {
  const router = useRouter();
  const [companies, setCompanies] = useState<Company[]>([]);
  const [values, setValues] = useState<ContactWritePayload>({ lastName: "" });
  const [validationError, setValidationError] = useState<ApiValidationError | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const inlineCompany = useInlineCompany();

  useEffect(() => {
    companiesApi
      .list({ itemsPerPage: 100, "order[name]": "asc" })
      .then((res) => setCompanies(res.items))
      .catch((err) => setError(toDisplayMessage(err)));
  }, []);

  function onChange<K extends keyof ContactWritePayload>(
    field: K,
    value: ContactWritePayload[K],
  ) {
    setValues((prev) => ({ ...prev, [field]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setError(null);
    setValidationError(null);
    try {
      const result = await inlineCompany.saveContact(values, (payload) => contactsApi.create(payload));
      if (!result) return;
      router.push(`/contacts/${result.saved.id}`);
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
      <h1 className="text-2xl font-bold tracking-tight">Nouveau contact</h1>

      <form onSubmit={handleSubmit} className="mt-6 max-w-2xl space-y-4">
        <ErrorBanner message={error} />
        <div className="rounded-lg border border-black/10 bg-white p-6">
          <ContactFormFields
            values={values}
            onChange={onChange}
            validationError={validationError}
            companies={companies}
            inlineCompany={inlineCompany}
            onError={(err) => setError(toDisplayMessage(err))}
          />
        </div>
        <div className="flex gap-3">
          <button
            type="submit"
            disabled={submitting}
            className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {submitting ? "Création..." : "Créer le contact"}
          </button>
        </div>
      </form>
    </div>
  );
}
