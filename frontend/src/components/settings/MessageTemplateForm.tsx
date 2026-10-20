"use client";

import { useLayoutEffect, useRef } from "react";
import { FieldError } from "@/components/ui/FieldError";
import type { ApiValidationError } from "@/lib/api/errors";
import type { MessageTemplateWritePayload } from "@/lib/api/types";
import { MESSAGE_PLACEHOLDERS, placeholderToken } from "@/lib/messagePlaceholders";

/**
 * Champs d'un message pré-construit : titre, puis éditeur en deux colonnes
 * (corps à gauche, champs utilisables à droite). Un clic sur un champ insère
 * son indicateur à la position du curseur (ou remplace la sélection).
 */
export function MessageTemplateForm({
  values,
  onChange,
  validationError,
}: {
  values: MessageTemplateWritePayload;
  onChange: <K extends keyof MessageTemplateWritePayload>(
    field: K,
    value: MessageTemplateWritePayload[K],
  ) => void;
  validationError: ApiValidationError | null;
}) {
  const bodyRef = useRef<HTMLTextAreaElement>(null);
  // Position du curseur à rétablir après une insertion, une fois la nouvelle
  // valeur contrôlée appliquée au DOM.
  const pendingCaret = useRef<number | null>(null);

  useLayoutEffect(() => {
    const textarea = bodyRef.current;
    const caret = pendingCaret.current;
    if (!textarea || caret === null) return;
    pendingCaret.current = null;
    textarea.focus();
    textarea.setSelectionRange(caret, caret);
  }, [values.body]);

  function insertPlaceholder(key: string) {
    const token = placeholderToken(key);
    const textarea = bodyRef.current;
    const body = values.body ?? "";
    // Sans textarea montée (cas théorique), ajout en fin de corps.
    const start = textarea?.selectionStart ?? body.length;
    const end = textarea?.selectionEnd ?? body.length;

    // Curseur replacé juste après l'indicateur inséré (voir useLayoutEffect).
    pendingCaret.current = start + token.length;
    onChange("body", body.slice(0, start) + token + body.slice(end));
  }

  return (
    <div className="space-y-4">
      <div>
        <label htmlFor="message-title" className="block text-sm font-medium">
          Titre *
        </label>
        <input
          id="message-title"
          type="text"
          value={values.title ?? ""}
          onChange={(e) => onChange("title", e.target.value)}
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("title")} />
      </div>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div className="md:col-span-2">
          <label htmlFor="message-body" className="block text-sm font-medium">
            Message *
          </label>
          <textarea
            id="message-body"
            ref={bodyRef}
            rows={12}
            value={values.body ?? ""}
            onChange={(e) => onChange("body", e.target.value)}
            className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 font-mono text-sm outline-none focus:border-blue-500"
          />
          <FieldError message={validationError?.messageFor("body")} />
        </div>

        <div>
          <p className="block text-sm font-medium">Champs utilisables</p>
          <p className="mt-1 text-xs text-black/50">
            Cliquez sur un champ pour l&apos;insérer à la position du curseur.
          </p>
          <ul className="mt-2 space-y-1.5">
            {MESSAGE_PLACEHOLDERS.map((placeholder) => (
              <li key={placeholder.key}>
                <button
                  type="button"
                  onClick={() => insertPlaceholder(placeholder.key)}
                  className="flex w-full items-center justify-between rounded-md border border-black/15 px-3 py-2 text-left text-sm hover:bg-black/5"
                >
                  <span className="font-medium">{placeholder.label}</span>
                  <code className="text-xs text-black/50">{placeholderToken(placeholder.key)}</code>
                </button>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </div>
  );
}
