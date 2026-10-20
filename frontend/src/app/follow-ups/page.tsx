"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { MessageDialog } from "@/components/follow-ups/MessageDialog";
import { Badge } from "@/components/ui/Badge";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { IconButton } from "@/components/ui/IconButton";
import { Pagination } from "@/components/ui/Pagination";
import { toDisplayMessage } from "@/lib/api/errors";
import { idFromIri } from "@/lib/api/iri";
import { companiesApi, contactsApi, followUpsApi, opportunitiesApi } from "@/lib/api/resources";
import { FOLLOW_UP_STATUSES, type FollowUp, type FollowUpStatus } from "@/lib/api/types";
import type { QueryParams } from "@/lib/api/http";
import { formatDateTime, isOverdue } from "@/lib/format";
import { followUpStatusBadgeClasses, followUpStatusLabels } from "@/lib/labels";

const ITEMS_PER_PAGE = 20;

type QuickFilter = "all" | "overdue" | "today" | "upcoming";

/** Minuit local, décalé de `daysOffset` jours, au format attendu par le DateFilter de l'API (sans fuseau). */
function localMidnightIso(daysOffset: number): string {
  const d = new Date();
  d.setHours(0, 0, 0, 0);
  d.setDate(d.getDate() + daysOffset);
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T00:00:00`;
}

export default function FollowUpsPage() {
  const [items, setItems] = useState<FollowUp[]>([]);
  const [totalItems, setTotalItems] = useState(0);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState<FollowUpStatus | "">("");
  const [quickFilter, setQuickFilter] = useState<QuickFilter>("all");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  // Relance visée par la popup de suppression (null = popup fermée).
  const [pendingDelete, setPendingDelete] = useState<FollowUp | null>(null);
  const [deleting, setDeleting] = useState(false);
  // Relance dont la popup « Message » est ouverte (null = popup fermée).
  const [messageFollowUp, setMessageFollowUp] = useState<FollowUp | null>(null);

  // Listes de résolution pour la colonne "Élément lié" (pas de dénormalisation
  // côté API pour FollowUp, contrairement à Opportunity.companyName).
  const [companyNames, setCompanyNames] = useState<Record<number, string>>({});
  const [contactNames, setContactNames] = useState<Record<number, string>>({});
  const [opportunityTitles, setOpportunityTitles] = useState<Record<number, string>>({});

  useEffect(() => {
    Promise.all([
      companiesApi.list({ itemsPerPage: 100 }),
      contactsApi.list({ itemsPerPage: 100 }),
      opportunitiesApi.list({ itemsPerPage: 100 }),
    ])
      .then(([companies, contacts, opportunities]) => {
        setCompanyNames(Object.fromEntries(companies.items.map((c) => [c.id, c.name])));
        setContactNames(
          Object.fromEntries(
            contacts.items.map((c) => [c.id, [c.firstName, c.lastName].filter(Boolean).join(" ")]),
          ),
        );
        setOpportunityTitles(Object.fromEntries(opportunities.items.map((o) => [o.id, o.title])));
      })
      .catch(() => {
        // Non bloquant : la liste des relances reste utilisable sans les libellés liés.
      });
  }, []);

  function reload() {
    setLoading(true);
    setError(null);

    const params: QueryParams = {
      page,
      itemsPerPage: ITEMS_PER_PAGE,
      "order[scheduledAt]": "asc",
    };

    // Le filtre temporel rapide implique toujours status=todo côté API : on
    // ignore alors le filtre de statut explicite pour éviter une requête
    // incohérente (ex. "En retard" + statut "Terminée" ne renverrait rien
    // de trompeur, mais le sens de l'action serait perdu). Le <select> de
    // statut est désactivé dans l'UI tant qu'un filtre rapide est actif.
    if (quickFilter === "all") {
      if (status) params["status[]"] = [status];
    } else {
      params["status[]"] = ["todo"];
      if (quickFilter === "overdue") {
        params["scheduledAt[strictly_before]"] = localMidnightIso(0);
      } else if (quickFilter === "today") {
        params["scheduledAt[after]"] = localMidnightIso(0);
        params["scheduledAt[strictly_before]"] = localMidnightIso(1);
      } else if (quickFilter === "upcoming") {
        params["scheduledAt[after]"] = localMidnightIso(1);
      }
    }

    followUpsApi
      .list(params)
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
  }, [page, status, quickFilter]);

  async function markDone(followUp: FollowUp) {
    setActionError(null);
    try {
      // completedAt n'est pas rempli par le backend (vérifié par curl) : on
      // l'envoie nous-mêmes avec la date/heure courante.
      await followUpsApi.update(followUp.id, {
        status: "done",
        completedAt: new Date().toISOString(),
      });
      reload();
    } catch (err) {
      setActionError(toDisplayMessage(err));
    }
  }

  async function deleteFollowUp() {
    if (!pendingDelete || deleting) return;
    setDeleting(true);
    setActionError(null);
    try {
      await followUpsApi.remove(pendingDelete.id);
      reload();
    } catch (err) {
      setActionError(toDisplayMessage(err));
    } finally {
      setDeleting(false);
      setPendingDelete(null);
    }
  }

  function linkedItem(followUp: FollowUp): { label: string; href: string | null } | null {
    if (followUp.opportunity) {
      const id = idFromIri(followUp.opportunity);
      if (!id) return null;
      return { label: `Opportunité : ${opportunityTitles[id] ?? `#${id}`}`, href: `/opportunities/${id}` };
    }
    if (followUp.contact) {
      const id = idFromIri(followUp.contact);
      if (!id) return null;
      return { label: `Contact : ${contactNames[id] ?? `#${id}`}`, href: `/contacts/${id}` };
    }
    if (followUp.company) {
      const id = idFromIri(followUp.company);
      if (!id) return null;
      // Pas de page Entreprise : libellé seul.
      return { label: `Entreprise : ${companyNames[id] ?? `#${id}`}`, href: null };
    }
    return null;
  }

  return (
    <div className="p-8">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold tracking-tight">Relances</h1>
        <Link
          href="/follow-ups/new"
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
          Nouvelle relance
        </Link>
      </div>

      <div className="mt-4 flex flex-wrap items-center gap-3">
        <select
          value={quickFilter}
          onChange={(e) => {
            setPage(1);
            setQuickFilter(e.target.value as QuickFilter);
          }}
          className="rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
        >
          <option value="all">Toutes les échéances</option>
          <option value="overdue">En retard</option>
          <option value="today">Aujourd&apos;hui</option>
          <option value="upcoming">À venir</option>
        </select>

        <select
          value={quickFilter === "all" ? status : "todo"}
          disabled={quickFilter !== "all"}
          onChange={(e) => {
            setPage(1);
            setStatus(e.target.value as FollowUpStatus | "");
          }}
          className="rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 disabled:opacity-50"
        >
          <option value="">Tous les statuts</option>
          {FOLLOW_UP_STATUSES.map((s) => (
            <option key={s} value={s}>
              {followUpStatusLabels[s]}
            </option>
          ))}
        </select>

        {quickFilter !== "all" && (
          <p className="text-xs text-black/50">
            Ce filtre temporel ne porte que sur les relances à faire.
          </p>
        )}
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
              <th className="px-4 py-3 font-medium">Date prévue</th>
              <th className="px-4 py-3 font-medium">Statut</th>
              <th className="px-4 py-3 font-medium">Élément lié</th>
              <th className="px-4 py-3 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            {items.map((followUp) => {
              const link = linkedItem(followUp);
              const overdue = followUp.status === "todo" && isOverdue(followUp.scheduledAt);
              return (
                <tr key={followUp.id} className="border-t border-black/5 hover:bg-black/[0.02]">
                  <td className="px-4 py-3">
                    <Link
                      href={`/follow-ups/${followUp.id}`}
                      className="font-medium text-blue-700 hover:underline"
                    >
                      {followUp.title}
                    </Link>
                  </td>
                  <td className={`px-4 py-3 ${overdue ? "font-medium text-red-600" : "text-black/70"}`}>
                    {formatDateTime(followUp.scheduledAt)}
                  </td>
                  <td className="px-4 py-3">
                    <Badge
                      label={followUpStatusLabels[followUp.status]}
                      className={followUpStatusBadgeClasses[followUp.status]}
                    />
                  </td>
                  <td className="px-4 py-3 text-black/70">
                    {link?.href ? (
                      <Link href={link.href} className="text-blue-700 hover:underline">
                        {link.label}
                      </Link>
                    ) : (
                      (link?.label ?? "—")
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex gap-2">
                      {followUp.status === "todo" && (
                        <IconButton
                          icon="check"
                          label="Marquer comme faite"
                          tone="green"
                          onClick={() => markDone(followUp)}
                        />
                      )}
                      {/* pas de message ni de suppression pour une relance terminée. Seule la suppression est aussi refusée par l'API (409, FollowUpDeletionGuardProcessor). */}
                      {followUp.status !== "done" && (
                        <>
                          <IconButton
                            icon="message"
                            label="Message"
                            tone="blue"
                            onClick={() => setMessageFollowUp(followUp)}
                          />
                          <IconButton
                            icon="trash"
                            label="Supprimer"
                            tone="red"
                            onClick={() => setPendingDelete(followUp)}
                          />
                        </>
                      )}
                    </div>
                  </td>
                </tr>
              );
            })}
            {!loading && items.length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-black/50">
                  Aucune relance trouvée.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <div className="mt-4">
        <Pagination page={page} itemsPerPage={ITEMS_PER_PAGE} totalItems={totalItems} onPageChange={setPage} />
      </div>

      <ConfirmDialog
        open={pendingDelete !== null}
        title="Supprimer cette relance ?"
        message={`La relance « ${pendingDelete?.title ?? ""} » sera supprimée définitivement.`}
        busy={deleting}
        onConfirm={deleteFollowUp}
        onCancel={() => setPendingDelete(null)}
      />

      <MessageDialog
        open={messageFollowUp !== null}
        contactId={idFromIri(messageFollowUp?.contact)}
        onClose={() => setMessageFollowUp(null)}
      />
    </div>
  );
}
