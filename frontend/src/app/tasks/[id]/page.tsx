"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { use, useCallback, useEffect, useState } from "react";
import { TaskFormFields } from "@/components/tasks/TaskFormFields";
import { Badge } from "@/components/ui/Badge";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { ApiValidationError, toDisplayMessage } from "@/lib/api/errors";
import { contactsApi, opportunitiesApi, tasksApi } from "@/lib/api/resources";
import type { Contact, Opportunity, Task, TaskWritePayload } from "@/lib/api/types";
import { formatDate } from "@/lib/format";
import { taskStatusBadgeClasses, taskStatusLabels } from "@/lib/labels";

export default function TaskDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();

  const [task, setTask] = useState<Task | null>(null);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [opportunities, setOpportunities] = useState<Opportunity[]>([]);
  const [values, setValues] = useState<TaskWritePayload>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [validationError, setValidationError] = useState<ApiValidationError | null>(null);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [data, contactsResult, opportunitiesResult] = await Promise.all([
        tasksApi.get(id),
        contactsApi.list({ itemsPerPage: 100, "order[lastName]": "asc" }),
        opportunitiesApi.list({ itemsPerPage: 100, "order[createdAt]": "desc" }),
      ]);
      setTask(data);
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

  function onChange<K extends keyof TaskWritePayload>(field: K, value: TaskWritePayload[K]) {
    setValues((prev) => ({ ...prev, [field]: value }));
  }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setValidationError(null);
    try {
      const updated = await tasksApi.update(id, values);
      setTask(updated);
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
    if (!confirm("Supprimer définitivement cette tâche ?")) return;
    setDeleting(true);
    setError(null);
    try {
      await tasksApi.remove(id);
      router.push("/tasks");
    } catch (err) {
      setError(toDisplayMessage(err));
      setDeleting(false);
    }
  }

  if (loading) {
    return <div className="p-8 text-sm text-black/50">Chargement...</div>;
  }

  if (!task) {
    return (
      <div className="p-8">
        <ErrorBanner message={error ?? "Tâche introuvable."} />
      </div>
    );
  }

  return (
    <div className="p-8">
      <div className="flex items-center justify-between">
        <div>
          <Link href="/tasks" className="text-sm text-blue-700 hover:underline">
            ← Tâches
          </Link>
          <div className="mt-1 flex items-center gap-3">
            <h1 className="text-2xl font-bold tracking-tight">{task.title}</h1>
            <Badge
              label={taskStatusLabels[task.status]}
              className={taskStatusBadgeClasses[task.status]}
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
            <TaskFormFields
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
            <p>Créée le {formatDate(task.createdAt)}</p>
            {task.completedAt && <p>Terminée le {formatDate(task.completedAt)}</p>}
          </div>
        </div>
      </div>
    </div>
  );
}
