"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Badge } from "@/components/ui/Badge";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { IconButton } from "@/components/ui/IconButton";
import { Pagination } from "@/components/ui/Pagination";
import { toDisplayMessage } from "@/lib/api/errors";
import { idFromIri } from "@/lib/api/iri";
import { contactsApi, opportunitiesApi, alertsApi } from "@/lib/api/resources";
import {
  ALERT_PRIORITIES,
  ALERT_STATUSES,
  type Alert,
  type AlertPriority,
  type AlertStatus,
} from "@/lib/api/types";
import { formatDateTime, isOverdue } from "@/lib/format";
import {
  alertPriorityBadgeClasses,
  alertPriorityLabels,
  alertStatusBadgeClasses,
  alertStatusLabels,
} from "@/lib/labels";

const ITEMS_PER_PAGE = 20;

export default function AlertsPage() {
  const [items, setItems] = useState<Alert[]>([]);
  const [totalItems, setTotalItems] = useState(0);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState<AlertStatus | "">("");
  const [priority, setPriority] = useState<AlertPriority | "">("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  // Résolution des libellés d'élément lié (Alert n'expose que des IRI).
  const [contactNames, setContactNames] = useState<Record<number, string>>({});
  const [opportunityTitles, setOpportunityTitles] = useState<Record<number, string>>({});

  useEffect(() => {
    Promise.all([
      contactsApi.list({ itemsPerPage: 100 }),
      opportunitiesApi.list({ itemsPerPage: 100 }),
    ])
      .then(([contacts, opportunities]) => {
        setContactNames(
          Object.fromEntries(
            contacts.items.map((c) => [c.id, [c.firstName, c.lastName].filter(Boolean).join(" ")]),
          ),
        );
        setOpportunityTitles(Object.fromEntries(opportunities.items.map((o) => [o.id, o.title])));
      })
      .catch(() => {
        // Non bloquant.
      });
  }, []);

  function reload() {
    setLoading(true);
    setError(null);
    alertsApi
      .list({
        page,
        itemsPerPage: ITEMS_PER_PAGE,
        "order[dueDate]": "asc",
        "status[]": status ? [status] : undefined,
        "priority[]": priority ? [priority] : undefined,
      })
      .then((res) => {
        setItems(res.items);
        setTotalItems(res.totalItems);
      })
      .catch((err) => setError(toDisplayMessage(err)))
      .finally(() => setLoading(false));
  }

  useEffect(() => {
    const timeout = setTimeout(reload, 0);
    return () => clearTimeout(timeout);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [page, status, priority]);

  async function markDone(alert: Alert) {
    setActionError(null);
    try {
      // completedAt n'est pas rempli par le backend (même constat que pour
      // les relances, vérifié par curl) : on l'envoie nous-mêmes.
      await alertsApi.update(alert.id, { status: "done", completedAt: new Date().toISOString() });
      reload();
    } catch (err) {
      setActionError(toDisplayMessage(err));
    }
  }

  function linkedItem(alert: Alert): { label: string; href: string } | null {
    if (alert.opportunity) {
      const id = idFromIri(alert.opportunity);
      if (!id) return null;
      return { label: `Opportunité : ${opportunityTitles[id] ?? `#${id}`}`, href: `/opportunities/${id}` };
    }
    if (alert.contact) {
      const id = idFromIri(alert.contact);
      if (!id) return null;
      return { label: `Contact : ${contactNames[id] ?? `#${id}`}`, href: `/contacts/${id}` };
    }
    return null;
  }

  return (
    <div className="p-8">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold tracking-tight">Alertes</h1>
        <Link
          href="/alerts/new"
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
          Nouvelle alerte
        </Link>
      </div>

      <div className="mt-4 flex gap-3">
        <select
          value={status}
          onChange={(e) => {
            setPage(1);
            setStatus(e.target.value as AlertStatus | "");
          }}
          className="rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
        >
          <option value="">Tous les statuts</option>
          {ALERT_STATUSES.map((s) => (
            <option key={s} value={s}>
              {alertStatusLabels[s]}
            </option>
          ))}
        </select>

        <select
          value={priority}
          onChange={(e) => {
            setPage(1);
            setPriority(e.target.value as AlertPriority | "");
          }}
          className="rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
        >
          <option value="">Toutes les priorités</option>
          {ALERT_PRIORITIES.map((p) => (
            <option key={p} value={p}>
              {alertPriorityLabels[p]}
            </option>
          ))}
        </select>
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
              <th className="px-4 py-3 font-medium">Échéance</th>
              <th className="px-4 py-3 font-medium">Priorité</th>
              <th className="px-4 py-3 font-medium">Statut</th>
              <th className="px-4 py-3 font-medium">Élément lié</th>
              <th className="px-4 py-3 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            {items.map((alert) => {
              const link = linkedItem(alert);
              const overdue =
                (alert.status === "todo" || alert.status === "in_progress") && isOverdue(alert.dueDate);
              return (
                <tr key={alert.id} className="border-t border-black/5 hover:bg-black/[0.02]">
                  <td className="px-4 py-3">
                    <Link
                      href={`/alerts/${alert.id}`}
                      className="font-medium text-blue-700 hover:underline"
                    >
                      {alert.title}
                    </Link>
                  </td>
                  <td className={`px-4 py-3 ${overdue ? "font-medium text-red-600" : "text-black/70"}`}>
                    {formatDateTime(alert.dueDate)}
                  </td>
                  <td className="px-4 py-3">
                    {alert.priority ? (
                      <Badge
                        label={alertPriorityLabels[alert.priority]}
                        className={alertPriorityBadgeClasses[alert.priority]}
                      />
                    ) : (
                      "—"
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <Badge
                      label={alertStatusLabels[alert.status]}
                      className={alertStatusBadgeClasses[alert.status]}
                    />
                  </td>
                  <td className="px-4 py-3 text-black/70">
                    {link ? (
                      <Link href={link.href} className="text-blue-700 hover:underline">
                        {link.label}
                      </Link>
                    ) : (
                      "—"
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex gap-2">
                      {(alert.status === "todo" || alert.status === "in_progress") && (
                        <IconButton
                          icon="check"
                          label="Marquer comme faite"
                          tone="green"
                          onClick={() => markDone(alert)}
                        />
                      )}
                    </div>
                  </td>
                </tr>
              );
            })}
            {!loading && items.length === 0 && (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-black/50">
                  Aucune alerte trouvée.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <div className="mt-4">
        <Pagination page={page} itemsPerPage={ITEMS_PER_PAGE} totalItems={totalItems} onPageChange={setPage} />
      </div>
    </div>
  );
}
