"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { use, useCallback, useEffect, useState } from "react";
import { AlertFormFields } from "@/components/alerts/AlertFormFields";
import { Badge } from "@/components/ui/Badge";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { useNotify } from "@/components/ui/Toaster";
import { ApiValidationError, toDisplayMessage } from "@/lib/api/errors";
import { contactsApi, opportunitiesApi, alertsApi } from "@/lib/api/resources";
import type { Contact, Opportunity, Alert, AlertWritePayload } from "@/lib/api/types";
import { formatDate } from "@/lib/format";
import { alertStatusBadgeClasses, alertStatusLabels } from "@/lib/labels";

export default function AlertDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();
  const notify = useNotify();

  const [alert, setAlert] = useState<Alert | null>(null);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [opportunities, setOpportunities] = useState<Opportunity[]>([]);
  const [values, setValues] = useState<AlertWritePayload>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [validationError, setValidationError] = useState<ApiValidationError | null>(null);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [data, contactsResult, opportunitiesResult] = await Promise.all([
        alertsApi.get(id),
        contactsApi.list({ itemsPerPage: 100, "order[lastName]": "asc" }),
        opportunitiesApi.list({ itemsPerPage: 100, "order[createdAt]": "desc" }),
      ]);
      setAlert(data);
      setContacts(contactsResult.items);
      setOpportunities(opportunitiesResult.items);
      setValues({
        title: data.title,
        description: data.description,
        dueDate: data.dueDate,
        status: data.status,
        priority: data.priority,
        completedAt: data.completedAt,
        opportunity: data.opportunity,
        contact: data.contact,
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

  function onChange<K extends keyof AlertWritePayload>(field: K, value: AlertWritePayload[K]) {
    setValues((prev) => ({ ...prev, [field]: value }));
  }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setValidationError(null);
    try {
      const updated = await alertsApi.update(id, values);
      setAlert(updated);
      notify.success("Alerte modifiée");
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
      await alertsApi.remove(id);
      notify.success("Alerte supprimée");
      router.push("/alerts");
    } catch (err) {
      notify.error(toDisplayMessage(err));
      setDeleting(false);
      setConfirmOpen(false);
    }
  }

  if (loading) {
    return <div className="p-8 text-sm text-black/50">Chargement...</div>;
  }

  if (!alert) {
    return (
      <div className="p-8">
        <ErrorBanner message={error ?? "Alerte introuvable."} />
      </div>
    );
  }

  return (
    <div className="p-8">
      <div className="flex items-center justify-between">
        <div>
          <Link href="/alerts" className="text-sm text-blue-700 hover:underline">
            ← Alertes
          </Link>
          <div className="mt-1 flex items-center gap-3">
            <h1 className="text-2xl font-bold tracking-tight">{alert.title}</h1>
            <Badge
              label={alertStatusLabels[alert.status]}
              className={alertStatusBadgeClasses[alert.status]}
            />
          </div>
        </div>
        <button
          type="button"
          onClick={() => setConfirmOpen(true)}
          disabled={deleting}
          className="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50"
        >
          {deleting ? "Suppression..." : "Supprimer"}
        </button>
      </div>

      <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <form onSubmit={handleSave} className="lg:col-span-2">
          <div className="rounded-lg border border-black/10 bg-white p-6">
            <AlertFormFields
              values={values}
              onChange={onChange}
              validationError={validationError}
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
            <p>Créée le {formatDate(alert.createdAt)}</p>
            {alert.completedAt && <p>Terminée le {formatDate(alert.completedAt)}</p>}
          </div>
        </div>
      </div>

      <ConfirmDialog
        open={confirmOpen}
        title="Supprimer cette alerte ?"
        message={`L'alerte « ${alert.title} » sera supprimée définitivement.`}
        busy={deleting}
        onConfirm={handleDelete}
        onCancel={() => setConfirmOpen(false)}
      />
    </div>
  );
}
