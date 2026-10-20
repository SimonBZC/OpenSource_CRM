"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { use, useCallback, useEffect, useState } from "react";
import { MessageTemplateForm } from "@/components/settings/MessageTemplateForm";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { ApiValidationError, toDisplayMessage } from "@/lib/api/errors";
import { messageTemplatesApi } from "@/lib/api/resources";
import type { MessageTemplate, MessageTemplateWritePayload } from "@/lib/api/types";

export default function MessageTemplateDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();

  const [template, setTemplate] = useState<MessageTemplate | null>(null);
  const [values, setValues] = useState<MessageTemplateWritePayload>({});
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
      const data = await messageTemplatesApi.get(id);
      setTemplate(data);
      setValues({ title: data.title, body: data.body });
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

  function onChange<K extends keyof MessageTemplateWritePayload>(
    field: K,
    value: MessageTemplateWritePayload[K],
  ) {
    setValues((prev) => ({ ...prev, [field]: value }));
  }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setValidationError(null);
    try {
      const updated = await messageTemplatesApi.update(id, values);
      setTemplate(updated);
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
      await messageTemplatesApi.remove(id);
      router.push("/settings/messages");
    } catch (err) {
      setError(toDisplayMessage(err));
      setDeleting(false);
      setConfirmOpen(false);
    }
  }

  if (loading) {
    return <div className="p-8 text-sm text-black/50">Chargement...</div>;
  }

  if (!template) {
    return (
      <div className="p-8">
        <ErrorBanner message={error ?? "Message introuvable."} />
      </div>
    );
  }

  return (
    <div className="p-8">
      <div className="flex items-center justify-between">
        <div>
          <Link href="/settings/messages" className="text-sm text-blue-700 hover:underline">
            ← Messages
          </Link>
          <h1 className="mt-1 text-2xl font-bold tracking-tight">{template.title}</h1>
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

      <form onSubmit={handleSave} className="mt-6 max-w-5xl space-y-4">
        <ErrorBanner message={error} />
        <div className="rounded-lg border border-black/10 bg-white p-6">
          <MessageTemplateForm values={values} onChange={onChange} validationError={validationError} />
        </div>
        <button
          type="submit"
          disabled={saving}
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
        >
          {saving ? "Enregistrement..." : "Enregistrer"}
        </button>
      </form>

      <ConfirmDialog
        open={confirmOpen}
        title="Supprimer ce message ?"
        message={`Le message « ${template.title} » sera supprimé définitivement.`}
        busy={deleting}
        onConfirm={handleDelete}
        onCancel={() => setConfirmOpen(false)}
      />
    </div>
  );
}
