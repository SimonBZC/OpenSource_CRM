"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { use, useCallback, useEffect, useState } from "react";
import { FollowUpFormFields } from "@/components/follow-ups/FollowUpFormFields";
import { Badge } from "@/components/ui/Badge";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { ApiValidationError, toDisplayMessage } from "@/lib/api/errors";
import { companiesApi, contactsApi, followUpsApi, opportunitiesApi } from "@/lib/api/resources";
import type {
  Company,
  Contact,
  FollowUp,
  FollowUpWritePayload,
  Opportunity,
} from "@/lib/api/types";
import { formatDate } from "@/lib/format";
import { followUpStatusBadgeClasses, followUpStatusLabels } from "@/lib/labels";

export default function FollowUpDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();

  const [followUp, setFollowUp] = useState<FollowUp | null>(null);
  const [companies, setCompanies] = useState<Company[]>([]);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [opportunities, setOpportunities] = useState<Opportunity[]>([]);
  const [values, setValues] = useState<FollowUpWritePayload>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [validationError, setValidationError] = useState<ApiValidationError | null>(null);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [data, companiesResult, contactsResult, opportunitiesResult] = await Promise.all([
        followUpsApi.get(id),
        companiesApi.list({ itemsPerPage: 100, "order[name]": "asc" }),
        contactsApi.list({ itemsPerPage: 100, "order[lastName]": "asc" }),
        opportunitiesApi.list({ itemsPerPage: 100, "order[createdAt]": "desc" }),
      ]);
      setFollowUp(data);
      setCompanies(companiesResult.items);
      setContacts(contactsResult.items);
      setOpportunities(opportunitiesResult.items);
      setValues({
        title: data.title,
        description: data.description,
        scheduledAt: data.scheduledAt,
        status: data.status,
        completedAt: data.completedAt,
        company: data.company,
        contact: data.contact,
        opportunity: data.opportunity,
      });
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

  function onChange<K extends keyof FollowUpWritePayload>(
    field: K,
    value: FollowUpWritePayload[K],
  ) {
    setValues((prev) => ({ ...prev, [field]: value }));
  }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setValidationError(null);
    try {
      const updated = await followUpsApi.update(id, values);
      setFollowUp(updated);
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
    if (!confirm("Supprimer définitivement cette relance ?")) return;
    setDeleting(true);
    setError(null);
    try {
      await followUpsApi.remove(id);
      router.push("/follow-ups");
    } catch (err) {
      setError(toDisplayMessage(err));
      setDeleting(false);
    }
  }

  if (loading) {
    return <div className="p-8 text-sm text-black/50">Chargement...</div>;
  }

  if (!followUp) {
    return (
      <div className="p-8">
        <ErrorBanner message={error ?? "Relance introuvable."} />
      </div>
    );
  }

  return (
    <div className="p-8">
      <div className="flex items-center justify-between">
        <div>
          <Link href="/follow-ups" className="text-sm text-blue-700 hover:underline">
            ← Relances
          </Link>
          <div className="mt-1 flex items-center gap-3">
            <h1 className="text-2xl font-bold tracking-tight">{followUp.title}</h1>
            <Badge
              label={followUpStatusLabels[followUp.status]}
              className={followUpStatusBadgeClasses[followUp.status]}
            />
          </div>
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
            <FollowUpFormFields
              values={values}
              onChange={onChange}
              validationError={validationError}
              companies={companies}
              contacts={contacts}
              opportunities={opportunities}
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
          <div className="rounded-lg border border-black/10 bg-white p-4 text-xs text-black/50">
            <p>Créée le {formatDate(followUp.createdAt)}</p>
            {followUp.completedAt && <p>Terminée le {formatDate(followUp.completedAt)}</p>}
          </div>
        </div>
      </div>
    </div>
  );
}
