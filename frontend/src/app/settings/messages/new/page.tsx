"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { MessageTemplateForm } from "@/components/settings/MessageTemplateForm";
import { useNotify } from "@/components/ui/Toaster";
import { ApiValidationError, toDisplayMessage } from "@/lib/api/errors";
import { messageTemplatesApi } from "@/lib/api/resources";
import type { MessageTemplateWritePayload } from "@/lib/api/types";

export default function NewMessageTemplatePage() {
  const router = useRouter();
  const notify = useNotify();
  const [values, setValues] = useState<MessageTemplateWritePayload>({ title: "", body: "" });
  const [validationError, setValidationError] = useState<ApiValidationError | null>(null);
  const [submitting, setSubmitting] = useState(false);

  function onChange<K extends keyof MessageTemplateWritePayload>(
    field: K,
    value: MessageTemplateWritePayload[K],
  ) {
    setValues((prev) => ({ ...prev, [field]: value }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setValidationError(null);
    try {
      await messageTemplatesApi.create(values);
      notify.success("Message créé");
      router.push("/settings/messages");
    } catch (err) {
      if (err instanceof ApiValidationError) {
        setValidationError(err);
      } else {
        notify.error(toDisplayMessage(err));
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="p-8">
      <Link href="/settings/messages" className="text-sm text-blue-700 hover:underline">
        ← Messages
      </Link>
      <h1 className="mt-1 text-2xl font-bold tracking-tight">Nouveau message</h1>

      <form onSubmit={handleSubmit} className="mt-6 max-w-5xl space-y-4">
        <div className="rounded-lg border border-black/10 bg-white p-6">
          <MessageTemplateForm values={values} onChange={onChange} validationError={validationError} />
        </div>
        <button
          type="submit"
          disabled={submitting}
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
        >
          {submitting ? "Création..." : "Créer le message"}
        </button>
      </form>
    </div>
  );
}
