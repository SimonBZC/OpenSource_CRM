"use client";

import Link from "next/link";
import { useEffect, useId, useRef, useState } from "react";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { toDisplayMessage } from "@/lib/api/errors";
import { messageTemplatesApi, renderMessageTemplate } from "@/lib/api/resources";
import type { MessageTemplate, RenderedMessage } from "@/lib/api/types";
import { missingPlaceholderLabel } from "@/lib/messagePlaceholders";

type MessageDialogProps = {
  open: boolean;
  /** Contact enregistré de la relance (null : relance sans contact). */
  contactId: number | null;
  onClose: () => void;
};

/**
 * Popup « Message » d'une relance : choix d'un message pré-construit par
 * titre, affichage du message rempli avec les données du contact (rendu côté
 * backend), puis copie dans le presse-papiers. Aucun envoi.
 *
 * Le contenu n'est monté que lorsque la popup est ouverte : chaque ouverture
 * repart d'un état vierge.
 */
export function MessageDialog({ open, contactId, onClose }: MessageDialogProps) {
  if (!open) return null;
  return <MessageDialogContent contactId={contactId} onClose={onClose} />;
}

type CopyState = "idle" | "copied" | "error";

function copyFromTextarea(textarea: HTMLTextAreaElement | null): boolean {
  if (!textarea) return false;
  textarea.focus();
  textarea.select();
  try {
    return document.execCommand("copy");
  } catch {
    return false;
  }
}

function MessageDialogContent({ contactId, onClose }: Omit<MessageDialogProps, "open">) {
  const titleId = useId();
  const selectId = useId();
  const closeRef = useRef<HTMLButtonElement>(null);
  // Identifiant du dernier rendu demandé : ignore les réponses obsolètes si
  // l'utilisateur change de message avant la fin d'une requête.
  const renderRequest = useRef(0);
  const bodyRef = useRef<HTMLTextAreaElement>(null);

  const [templates, setTemplates] = useState<MessageTemplate[] | null>(null);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [rendered, setRendered] = useState<RenderedMessage | null>(null);
  const [rendering, setRendering] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [copyState, setCopyState] = useState<CopyState>("idle");

  useEffect(() => {
    closeRef.current?.focus();
    messageTemplatesApi
      .list({ "order[title]": "asc", itemsPerPage: 100 })
      .then((res) => setTemplates(res.items))
      .catch((err) => {
        setTemplates([]);
        setError(toDisplayMessage(err));
      });
  }, []);

  useEffect(() => {
    function onKeyDown(e: KeyboardEvent) {
      if (e.key === "Escape") onClose();
    }
    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, [onClose]);

  async function selectTemplate(id: number | null) {
    setSelectedId(id);
    setRendered(null);
    setCopyState("idle");
    setError(null);
    // Toute sélection, y compris le retour à « Choisir un message… », périme
    // le rendu éventuellement en cours.
    const request = ++renderRequest.current;
    if (id === null) {
      setRendering(false);
      return;
    }

    setRendering(true);
    try {
      const result = await renderMessageTemplate(id, contactId);
      if (request === renderRequest.current) setRendered(result);
    } catch (err) {
      if (request === renderRequest.current) setError(toDisplayMessage(err));
    } finally {
      if (request === renderRequest.current) setRendering(false);
    }
  }

  async function copy() {
    if (!rendered) return;
    try {
      if (!navigator.clipboard) throw new Error("Presse-papiers indisponible");
      await navigator.clipboard.writeText(rendered.body);
      setCopyState("copied");
    } catch {
      // Repli quand l'API asynchrone est refusée (permission, contexte non
      // sécurisé) : copie de la sélection du textarea en lecture seule.
      setCopyState(copyFromTextarea(bodyRef.current) ? "copied" : "error");
    }
  }

  const loadingTemplates = templates === null;
  const noTemplate = templates !== null && templates.length === 0 && error === null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      onClick={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        className="w-full max-w-2xl rounded-lg border border-black/10 bg-white p-6 shadow-lg"
      >
        <h2 id={titleId} className="text-lg font-semibold">
          Message
        </h2>

        <div className="mt-4 space-y-4">
          <ErrorBanner message={error} />

          {loadingTemplates && <p className="text-sm text-black/50">Chargement...</p>}

          {noTemplate && (
            <p className="text-sm text-black/70">
              Aucun message.{" "}
              <Link href="/settings/messages" className="text-blue-700 hover:underline">
                Créer un message dans Paramètres &gt; Messages
              </Link>
            </p>
          )}

          {templates !== null && templates.length > 0 && (
            <div>
              <label htmlFor={selectId} className="block text-sm font-medium">
                Message à utiliser
              </label>
              <select
                id={selectId}
                value={selectedId ?? ""}
                onChange={(e) => selectTemplate(e.target.value === "" ? null : Number(e.target.value))}
                className="mt-1 w-full rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
              >
                <option value="">Choisir un message…</option>
                {templates.map((template) => (
                  <option key={template.id} value={template.id}>
                    {template.title}
                  </option>
                ))}
              </select>
            </div>
          )}

          {rendering && <p className="text-sm text-black/50">Préparation du message...</p>}

          {rendered && (
            <>
              {rendered.missing.length > 0 && (
                <div className="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                  {contactId === null && <p>Aucun contact n&apos;est lié à cette relance.</p>}
                  <p>
                    Données manquantes : {rendered.missing.map(missingPlaceholderLabel).join(", ")}. Les
                    indicateurs correspondants restent visibles dans le message.
                  </p>
                </div>
              )}
              <textarea
                ref={bodyRef}
                readOnly
                rows={10}
                value={rendered.body}
                aria-label="Message rempli"
                className="w-full rounded-md border border-black/15 bg-black/[0.02] px-3 py-2 text-sm outline-none"
              />
            </>
          )}
        </div>

        <div className="mt-6 flex items-center justify-end gap-2">
          {copyState === "copied" && <span className="text-sm text-green-700">Copié</span>}
          {copyState === "error" && (
            <span className="text-sm text-red-700">Copie impossible : sélectionnez le texte et copiez-le.</span>
          )}
          <button
            ref={closeRef}
            type="button"
            onClick={onClose}
            className="rounded-md border border-black/15 px-4 py-2 text-sm font-medium text-black/70 hover:bg-black/5"
          >
            Fermer
          </button>
          <button
            type="button"
            onClick={copy}
            disabled={!rendered}
            className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            Copier
          </button>
        </div>
      </div>
    </div>
  );
}
