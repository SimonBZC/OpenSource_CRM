"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { use, useCallback, useEffect, useState } from "react";
import { ContactFormFields } from "@/components/contacts/ContactFormFields";
import { Badge } from "@/components/ui/Badge";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { ApiValidationError, toDisplayMessage } from "@/lib/api/errors";
import { idFromIri } from "@/lib/api/iri";
import { companiesApi, contactsApi, opportunitiesApi } from "@/lib/api/resources";
import type { Company, Contact, ContactWritePayload, Opportunity } from "@/lib/api/types";
import { formatDate } from "@/lib/format";
import { opportunityStatusBadgeClasses, opportunityStatusLabels } from "@/lib/labels";

export default function ContactDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  const router = useRouter();

  const [contact, setContact] = useState<Contact | null>(null);
  const [companies, setCompanies] = useState<Company[]>([]);
  const [opportunities, setOpportunities] = useState<Opportunity[]>([]);
  const [values, setValues] = useState<ContactWritePayload>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [validationError, setValidationError] = useState<ApiValidationError | null>(null);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [data, companiesResult] = await Promise.all([
        contactsApi.get(id),
        companiesApi.list({ itemsPerPage: 100, "order[name]": "asc" }),
      ]);
      setContact(data);
      setCompanies(companiesResult.items);
      setValues({
        firstName: data.firstName,
        lastName: data.lastName,
        email: data.email,
        phone: data.phone,
        linkedin: data.linkedin,
        jobTitle: data.jobTitle,
        company: data.company,
        notes: data.notes,
      });
      const relatedOpportunities = await Promise.all(
        data.opportunities
          .map((iri) => idFromIri(iri))
          .filter((oid): oid is number => oid !== null)
          .map((oid) => opportunitiesApi.get(oid)),
      );
      setOpportunities(relatedOpportunities);
    } catch (err) {
      setError(toDisplayMessage(err));
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    const timeout = setTimeout(load, 0);
    return () => clearTimeout(timeout);
  }, [load]);

  function onChange<K extends keyof ContactWritePayload>(
    field: K,
    value: ContactWritePayload[K],
  ) {
    setValues((prev) => ({ ...prev, [field]: value }));
  }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setValidationError(null);
    try {
      const updated = await contactsApi.update(id, values);
      setContact(updated);
    } catch (err) {
      if (err instanceof ApiValidationError) {
        setValidationError(err);
      } else {
        setError(toDisplayMessage(err));
      }
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete() {
    if (!confirm("Supprimer définitivement ce contact ?")) return;
    setDeleting(true);
    setError(null);
    try {
      await contactsApi.remove(id);
      router.push("/contacts");
    } catch (err) {
      setError(toDisplayMessage(err));
      setDeleting(false);
    }
  }

  if (loading) {
    return <div className="p-8 text-sm text-black/50">Chargement...</div>;
  }

  if (!contact) {
    return (
      <div className="p-8">
        <ErrorBanner message={error ?? "Contact introuvable."} />
      </div>
    );
  }

  const linkedCompany = companies.find(
    (company) => company.id === idFromIri(contact.company),
  );

  return (
    <div className="p-8">
      <div className="flex items-center justify-between">
        <div>
          <Link href="/contacts" className="text-sm text-blue-700 hover:underline">
            ← Contacts
          </Link>
          <h1 className="mt-1 text-2xl font-bold tracking-tight">
            {[contact.firstName, contact.lastName].filter(Boolean).join(" ")}
          </h1>
          {linkedCompany && (
            <Link
              href={`/companies/${linkedCompany.id}`}
              className="text-sm text-black/60 hover:underline"
            >
              {linkedCompany.name}
            </Link>
          )}
        </div>
        <button
          type="button"
          onClick={handleDelete}
          disabled={deleting}
          className="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50"
        >
          {deleting ? "Suppression..." : "Supprimer"}
        </button>
      </div>

      <div className="mt-4">
        <ErrorBanner message={error} />
      </div>

      <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <form onSubmit={handleSave} className="lg:col-span-2">
          <div className="rounded-lg border border-black/10 bg-white p-6">
            <ContactFormFields
              values={values}
              onChange={onChange}
              validationError={validationError}
              companies={companies}
            />
          </div>
          <div className="mt-4">
            <button
              type="submit"
              disabled={saving}
              className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
            >
              {saving ? "Enregistrement..." : "Enregistrer"}
            </button>
          </div>
        </form>

        <div className="space-y-6">
          <div className="rounded-lg border border-black/10 bg-white p-4">
            <h2 className="text-sm font-semibold text-black/70">
              Opportunités ({opportunities.length})
            </h2>
            <ul className="mt-2 space-y-2">
              {opportunities.map((opportunity) => (
                <li key={opportunity.id} className="text-sm">
                  <Link
                    href={`/opportunities/${opportunity.id}`}
                    className="text-blue-700 hover:underline"
                  >
                    {opportunity.title}
                  </Link>
                  <div className="mt-1">
                    <Badge
                      label={opportunityStatusLabels[opportunity.status]}
                      className={opportunityStatusBadgeClasses[opportunity.status]}
                    />
                  </div>
                </li>
              ))}
              {opportunities.length === 0 && (
                <li className="text-sm text-black/40">Aucune opportunité liée.</li>
              )}
            </ul>
          </div>

          <div className="rounded-lg border border-black/10 bg-white p-4 text-xs text-black/50">
            <p>Créé le {formatDate(contact.createdAt)}</p>
          </div>
        </div>
      </div>
    </div>
  );
}
