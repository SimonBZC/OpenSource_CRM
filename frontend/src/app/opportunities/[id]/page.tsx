"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { use, useCallback, useEffect, useState } from "react";
import { OpportunityAnalysisPanel } from "@/components/opportunities/OpportunityAnalysisPanel";
import { OpportunityFormFields } from "@/components/opportunities/OpportunityFormFields";
import { Badge } from "@/components/ui/Badge";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { ApiError, ApiValidationError, toDisplayMessage } from "@/lib/api/errors";
import { analyzeOpportunity, companiesApi, contactsApi, opportunitiesApi } from "@/lib/api/resources";
import type {
  Company,
  Contact,
  Opportunity,
  OpportunityAnalyzeResponse,
  OpportunityWritePayload,
} from "@/lib/api/types";
import { formatDate } from "@/lib/format";
import { opportunityStatusBadgeClasses, opportunityStatusLabels } from "@/lib/labels";

export default function OpportunityDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  const router = useRouter();

  const [opportunity, setOpportunity] = useState<Opportunity | null>(null);
  const [companies, setCompanies] = useState<Company[]>([]);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [values, setValues] = useState<OpportunityWritePayload>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [validationError, setValidationError] = useState<ApiValidationError | null>(null);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [analyzing, setAnalyzing] = useState(false);
  const [analysisError, setAnalysisError] = useState<string | null>(null);
  const [analysisResult, setAnalysisResult] = useState<OpportunityAnalyzeResponse | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [data, companiesResult, contactsResult] = await Promise.all([
        opportunitiesApi.get(id),
        companiesApi.list({ itemsPerPage: 100, "order[name]": "asc" }),
        contactsApi.list({ itemsPerPage: 100, "order[lastName]": "asc" }),
      ]);
      setOpportunity(data);
      setCompanies(companiesResult.items);
      setContacts(contactsResult.items);
      setValues({
        title: data.title,
        description: data.description,
        company: data.company,
        contact: data.contact,
        source: data.source,
        status: data.status,
        dailyRate: data.dailyRate,
        remote: data.remote,
        location: data.location,
        duration: data.duration,
        startDate: data.startDate,
        technologies: data.technologies,
        notes: data.notes,
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

  function onChange<K extends keyof OpportunityWritePayload>(
    field: K,
    value: OpportunityWritePayload[K],
  ) {
    setValues((prev) => ({ ...prev, [field]: value }));
  }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setValidationError(null);
    try {
      const updated = await opportunitiesApi.update(id, values);
      setOpportunity(updated);
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
    if (deleting) return;
    setDeleting(true);
    setError(null);
    try {
      await opportunitiesApi.remove(id);
      router.push("/opportunities");
    } catch (err) {
      setError(toDisplayMessage(err));
      setDeleting(false);
      setConfirmOpen(false);
    }
  }

  async function handleAnalyze() {
    setAnalyzing(true);
    setAnalysisError(null);
    setAnalysisResult(null);
    try {
      const result = await analyzeOpportunity(id);
      setOpportunity(result.opportunity);
      setValues({
        title: result.opportunity.title,
        description: result.opportunity.description,
        company: result.opportunity.company,
        contact: result.opportunity.contact,
        source: result.opportunity.source,
        status: result.opportunity.status,
        dailyRate: result.opportunity.dailyRate,
        remote: result.opportunity.remote,
        location: result.opportunity.location,
        duration: result.opportunity.duration,
        startDate: result.opportunity.startDate,
        technologies: result.opportunity.technologies,
        notes: result.opportunity.notes,
      });
      setAnalysisResult(result);
    } catch (err) {
      if (err instanceof ApiError && err.errorCode === "ai_not_configured") {
        setAnalysisError(
          "L'analyse IA n'est pas configurée. Renseignez la clé OPENAI_API_KEY côté backend pour l'activer. Le CRM fonctionne normalement sans.",
        );
      } else {
        setAnalysisError(toDisplayMessage(err));
      }
    } finally {
      setAnalyzing(false);
    }
  }

  if (loading) {
    return <div className="p-8 text-sm text-black/50">Chargement...</div>;
  }

  if (!opportunity) {
    return (
      <div className="p-8">
        <ErrorBanner message={error ?? "Opportunité introuvable."} />
      </div>
    );
  }

  return (
    <div className="p-8">
      <div className="flex items-center justify-between">
        <div>
          <Link href="/opportunities" className="text-sm text-blue-700 hover:underline">
            ← Opportunités
          </Link>
          <div className="mt-1 flex items-center gap-3">
            <h1 className="text-2xl font-bold tracking-tight">{opportunity.title}</h1>
            <Badge
              label={opportunityStatusLabels[opportunity.status]}
              className={opportunityStatusBadgeClasses[opportunity.status]}
            />
          </div>
          {opportunity.companyName && (
            <p className="text-sm text-black/60">{opportunity.companyName}</p>
          )}
        </div>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={handleAnalyze}
            disabled={analyzing}
            className="rounded-md border border-blue-300 px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50 disabled:opacity-50"
          >
            {analyzing ? "Analyse en cours…" : "Analyser avec l'IA"}
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

      <div className="mt-4">
        <ErrorBanner message={error} />
        <div className="mt-2">
          <ErrorBanner message={analysisError} />
        </div>
      </div>

      <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <form onSubmit={handleSave} className="lg:col-span-2">
          <div className="rounded-lg border border-black/10 bg-white p-6">
            <OpportunityFormFields
              values={values}
              onChange={onChange}
              validationError={validationError}
              companies={companies}
              contacts={contacts}
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
          {analysisResult && <OpportunityAnalysisPanel result={analysisResult} />}

          {(opportunity.score !== null || opportunity.scoreReason) && (
            <div className="rounded-lg border border-black/10 bg-white p-4">
              <h2 className="text-sm font-semibold text-black/70">Score</h2>
              <p className="mt-1 text-2xl font-bold">{opportunity.score ?? "—"}</p>
              {opportunity.scoreReason && (
                <p className="mt-1 text-xs text-black/50">{opportunity.scoreReason}</p>
              )}
            </div>
          )}

          <div className="rounded-lg border border-black/10 bg-white p-4 text-xs text-black/50">
            <p>Créée le {formatDate(opportunity.createdAt)}</p>
            <p>Mise à jour le {formatDate(opportunity.updatedAt)}</p>
          </div>
        </div>
      </div>

      <ConfirmDialog
        open={confirmOpen}
        title="Supprimer cette opportunité ?"
        message={`L'opportunité « ${opportunity.title} » sera supprimée définitivement.`}
        busy={deleting}
        onConfirm={handleDelete}
        onCancel={() => setConfirmOpen(false)}
      />
    </div>
  );
}
