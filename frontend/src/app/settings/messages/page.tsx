"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { IconButton } from "@/components/ui/IconButton";
import { toDisplayMessage } from "@/lib/api/errors";
import { messageTemplatesApi } from "@/lib/api/resources";
import type { MessageTemplate } from "@/lib/api/types";

export default function MessageTemplatesPage() {
  const [items, setItems] = useState<MessageTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  // Message visé par la popup de suppression (null = popup fermée).
  const [pendingDelete, setPendingDelete] = useState<MessageTemplate | null>(null);
  const [deleting, setDeleting] = useState(false);

  function reload() {
    setLoading(true);
    setError(null);
    messageTemplatesApi
      .list({ "order[title]": "asc", itemsPerPage: 100 })
      .then((res) => setItems(res.items))
      .catch((err) => setError(toDisplayMessage(err)))
      .finally(() => setLoading(false));
  }

  useEffect(() => {
    const timeout = setTimeout(reload, 0);
    return () => clearTimeout(timeout);
  }, []);

  async function deleteTemplate() {
    if (!pendingDelete || deleting) return;
    setDeleting(true);
    setActionError(null);
    try {
      await messageTemplatesApi.remove(pendingDelete.id);
      reload();
    } catch (err) {
      setActionError(toDisplayMessage(err));
    } finally {
      setDeleting(false);
      setPendingDelete(null);
    }
  }

  return (
    <div className="p-8">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm text-black/50">Paramètres</p>
          <h1 className="text-2xl font-bold tracking-tight">Messages</h1>
        </div>
        <Link
          href="/settings/messages/new"
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
          Nouveau message
        </Link>
      </div>

      <div className="mt-4 space-y-3">
        <ErrorBanner message={error} />
        <ErrorBanner message={actionError} />
      </div>

      <div className="mt-4 overflow-hidden rounded-lg border border-black/10 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="bg-black/[0.03] text-xs uppercase tracking-wide text-black/50">
            <tr>
              <th className="px-4 py-3 font-medium">Titre</th>
              <th className="px-4 py-3 font-medium">Aperçu</th>
              <th className="px-4 py-3 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            {items.map((template) => (
              <tr key={template.id} className="border-t border-black/5 hover:bg-black/[0.02]">
                <td className="px-4 py-3">
                  <Link
                    href={`/settings/messages/${template.id}`}
                    className="font-medium text-blue-700 hover:underline"
                  >
                    {template.title}
                  </Link>
                </td>
                <td className="max-w-md truncate px-4 py-3 text-black/60">{template.body}</td>
                <td className="px-4 py-3">
                  <div className="flex gap-2">
                    <IconButton
                      icon="trash"
                      label="Supprimer"
                      tone="red"
                      onClick={() => setPendingDelete(template)}
                    />
                  </div>
                </td>
              </tr>
            ))}
            {!loading && !error && items.length === 0 && (
              <tr>
                <td colSpan={3} className="px-4 py-8 text-center text-black/50">
                  Aucun message.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <ConfirmDialog
        open={pendingDelete !== null}
        title="Supprimer ce message ?"
        message={`Le message « ${pendingDelete?.title ?? ""} » sera supprimé définitivement.`}
        busy={deleting}
        onConfirm={deleteTemplate}
        onCancel={() => setPendingDelete(null)}
      />
    </div>
  );
}
