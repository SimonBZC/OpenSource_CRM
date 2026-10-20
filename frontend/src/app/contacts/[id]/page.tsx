"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { use, useCallback, useEffect, useState } from "react";
import { ContactFormFields } from "@/components/contacts/ContactFormFields";
import { useInlineCompany } from "@/components/contacts/useInlineCompany";
import { Badge } from "@/components/ui/Badge";
import { MessageDialog } from "@/components/follow-ups/MessageDialog";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { useNotify } from "@/components/ui/Toaster";
import { ApiValidationError, toDisplayMessage } from "@/lib/api/errors";
import { companyIri, idFromIri } from "@/lib/api/iri";
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
  const notify = useNotify();

  const [contact, setContact] = useState<Contact | null>(null);
  const [companies, setCompanies] = useState<Company[]>([]);
  const [opportunities, setOpportunities] = useState<Opportunity[]>([]);
  const [values, setValues] = useState<ContactWritePayload>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [validationError, setValidationError] = useState<ApiValidationError | null>(null);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [messageOpen, setMessageOpen] = useState(false);
  const inlineCompany = useInlineCompany();

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
        presentationSent: data.presentationSent,
        active: data.active,
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
    setValidationError(null);
    try {
      const result = await inlineCompany.saveContact(values, (payload) =>
        contactsApi.update(id, payload),
      );
      if (!result) return;
      setContact(result.saved);
      const createdCompany = result.createdCompany;
      if (createdCompany) {
        setCompanies((prev) =>
          [...prev, createdCompany].sort((a, b) => a.name.localeCompare(b.name)),
        );
        setValues((prev) => ({ ...prev, company: companyIri(createdCompany.id) }));
        inlineCompany.reset();
      }
      notify.success("Contact modifié");
    } catch (err) {
      if (err instanceof ApiValidationError) {
        setValidationError(err);
      } else {
        notify.error(toDisplayMessage(err));
      }
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete() {
    if (deleting) return;
    setDeleting(true);
    try {
      await contactsApi.remove(id);
      notify.success("Contact supprimé");
      router.push("/contacts");
    } catch (err) {
      notify.error(toDisplayMessage(err));
      setDeleting(false);
      setConfirmOpen(false);
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
          <div className="mt-1 flex items-center gap-3">
            <h1 className="text-2xl font-bold tracking-tight">
              {[contact.firstName, contact.lastName].filter(Boolean).join(" ")}
            </h1>
            {!contact.active && <Badge label="Inactif" className="bg-gray-100 text-gray-600" />}
          </div>
          {linkedCompany && (
            <p className="text-sm text-black/60">{linkedCompany.name}</p>
          )}
        </div>
        <div className="flex gap-2">
          <button
            type="button"
            onClick={() => setMessageOpen(true)}
            className="rounded-md border border-blue-300 px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50"
          >
            Message
          </button>
          <button
            type="button"
            onClick={() => setConfirmOpen(true)}
            disabled={deleting}
            className="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50"
          >
            {deleting ? "Suppression..." : "Supprimer"}
          </button>
        </div>
      </div>

      {/* Erreur de chargement partiel (ex. opportunités liées) : reste en bandeau. */}
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
              inlineCompany={inlineCompany}
              onError={(err) => notify.error(toDisplayMessage(err))}
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

      <MessageDialog open={messageOpen} contactId={contact.id} onClose={() => setMessageOpen(false)} />

      <ConfirmDialog
        open={confirmOpen}
        title="Supprimer ce contact ?"
        message={`Le contact « ${[contact.firstName, contact.lastName].filter(Boolean).join(" ")} » sera supprimé définitivement.`}
        busy={deleting}
        onConfirm={handleDelete}
        onCancel={() => setConfirmOpen(false)}
      />
    </div>
  );
}
