"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Badge } from "@/components/ui/Badge";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { toDisplayMessage } from "@/lib/api/errors";
import { followUpsApi, opportunitiesApi, alertsApi } from "@/lib/api/resources";
import {
  OPPORTUNITY_STATUSES,
  type FollowUp,
  type Opportunity,
  type OpportunityStatus,
  type Alert,
} from "@/lib/api/types";
import { formatDate, formatDateTime } from "@/lib/format";
import {
  opportunityStatusBadgeClasses,
  opportunityStatusLabels,
  alertPriorityBadgeClasses,
  alertPriorityLabels,
} from "@/lib/labels";

/** Statuts d'opportunité "actifs" (hors clôturées) pour la section fort potentiel. */
const ACTIVE_OPPORTUNITY_STATUSES: OpportunityStatus[] = [
  "new",
  "to_review",
  "contacted",
  "waiting",
  "follow_up",
  "interview",
];

/** Minuit local, décalé de `daysOffset` jours (même convention que `/follow-ups`). */
function localMidnightIso(daysOffset: number): string {
  const d = new Date();
  d.setHours(0, 0, 0, 0);
  d.setDate(d.getDate() + daysOffset);
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T00:00:00`;
}

interface SectionState<T> {
  loading: boolean;
  error: string | null;
  data: T | null;
}

function initialSectionState<T>(): SectionState<T> {
  return { loading: true, error: null, data: null };
}

export default function DashboardPage() {
  const [totalOpportunities, setTotalOpportunities] = useState<SectionState<number>>(
    initialSectionState(),
  );
  const [toTreatOpportunities, setToTreatOpportunities] = useState<
    SectionState<{ totalItems: number; items: Opportunity[] }>
  >(initialSectionState());
  const [overdueFollowUps, setOverdueFollowUps] = useState<
    SectionState<{ totalItems: number; items: FollowUp[] }>
  >(initialSectionState());
  const [todayFollowUps, setTodayFollowUps] = useState<SectionState<FollowUp[]>>(
    initialSectionState(),
  );
  const [alertsTodo, setAlertsTodo] = useState<
    SectionState<{ totalItems: number; items: Alert[] }>
  >(initialSectionState());
  const [highPotential, setHighPotential] = useState<SectionState<Opportunity[]>>(
    initialSectionState(),
  );
  const [recentOpportunities, setRecentOpportunities] = useState<SectionState<Opportunity[]>>(
    initialSectionState(),
  );
  const [statusBreakdown, setStatusBreakdown] = useState<
    SectionState<Record<OpportunityStatus, number>>
  >(initialSectionState());

  useEffect(() => {
    const todayStart = localMidnightIso(0);
    const tomorrowStart = localMidnightIso(1);

    // Toutes les requêtes sont lancées en parallèle. Chaque section gère son
    // propre état loading/error : une section en erreur ne bloque pas les autres.
    opportunitiesApi
      .list({ itemsPerPage: 1 })
      .then((res) => setTotalOpportunities({ loading: false, error: null, data: res.totalItems }))
      .catch((err) =>
        setTotalOpportunities({ loading: false, error: toDisplayMessage(err), data: null }),
      );

    opportunitiesApi
      .list({ "status[]": ["new", "to_review"], itemsPerPage: 20, "order[createdAt]": "desc" })
      .then((res) =>
        setToTreatOpportunities({
          loading: false,
          error: null,
          data: { totalItems: res.totalItems, items: res.items },
        }),
      )
      .catch((err) =>
        setToTreatOpportunities({ loading: false, error: toDisplayMessage(err), data: null }),
      );

    followUpsApi
      .list({
        "status[]": ["todo"],
        "scheduledAt[strictly_before]": todayStart,
        "order[scheduledAt]": "asc",
        itemsPerPage: 20,
      })
      .then((res) =>
        setOverdueFollowUps({
          loading: false,
          error: null,
          data: { totalItems: res.totalItems, items: res.items },
        }),
      )
      .catch((err) =>
        setOverdueFollowUps({ loading: false, error: toDisplayMessage(err), data: null }),
      );

    followUpsApi
      .list({
        "status[]": ["todo"],
        "scheduledAt[after]": todayStart,
        "scheduledAt[strictly_before]": tomorrowStart,
        "order[scheduledAt]": "asc",
        itemsPerPage: 20,
      })
      .then((res) => setTodayFollowUps({ loading: false, error: null, data: res.items }))
      .catch((err) => setTodayFollowUps({ loading: false, error: toDisplayMessage(err), data: null }));

    alertsApi
      .list({
        "status[]": ["todo", "in_progress"],
        "order[dueDate]": "asc",
        itemsPerPage: 8,
      })
      .then((res) =>
        setAlertsTodo({ loading: false, error: null, data: { totalItems: res.totalItems, items: res.items } }),
      )
      .catch((err) => setAlertsTodo({ loading: false, error: toDisplayMessage(err), data: null }));

    opportunitiesApi
      .list({
        "score[gte]": 70,
        "status[]": ACTIVE_OPPORTUNITY_STATUSES,
        itemsPerPage: 20,
      })
      .then((res) => {
        // Tri décroissant par score côté client (pas d'hypothèse sur order[score]
        // combiné aux filtres non vérifiée ici, on trie simplement le résultat déjà filtré).
        const sorted = [...res.items].sort((a, b) => (b.score ?? 0) - (a.score ?? 0));
        setHighPotential({ loading: false, error: null, data: sorted });
      })
      .catch((err) => setHighPotential({ loading: false, error: toDisplayMessage(err), data: null }));

    opportunitiesApi
      .list({ itemsPerPage: 5, "order[createdAt]": "desc" })
      .then((res) => setRecentOpportunities({ loading: false, error: null, data: res.items }))
      .catch((err) =>
        setRecentOpportunities({ loading: false, error: toDisplayMessage(err), data: null }),
      );

    // Répartition par statut : la base est petite (11 opportunités), on
    // rapatrie donc la collection complète pour l'agréger côté client plutôt
    // que de faire 9 requêtes itemsPerPage=1 (une par statut).
    opportunitiesApi
      .list({ itemsPerPage: 100 })
      .then((res) => {
        const counts = OPPORTUNITY_STATUSES.reduce(
          (acc, status) => {
            acc[status] = 0;
            return acc;
          },
          {} as Record<OpportunityStatus, number>,
        );
        for (const opp of res.items) {
          counts[opp.status] = (counts[opp.status] ?? 0) + 1;
        }
        setStatusBreakdown({ loading: false, error: null, data: counts });
      })
      .catch((err) => setStatusBreakdown({ loading: false, error: toDisplayMessage(err), data: null }));
  }, []);

  return (
    <div className="p-8">
      <h1 className="text-2xl font-bold tracking-tight">Tableau de bord</h1>

      {/* Cartes KPI */}
      <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <KpiCard
          title="Total opportunités"
          href="/opportunities"
          state={totalOpportunities}
          render={(value) => value}
        />
        <KpiCard
          title="Opportunités à traiter"
          href="/opportunities?status=new&status=to_review"
          state={toTreatOpportunities}
          render={(data) => data.totalItems}
        />
        <KpiCard
          title="Relances en retard"
          href="/follow-ups"
          state={overdueFollowUps}
          render={(data) => data.totalItems}
          valueClassName="text-red-600"
        />
        <KpiCard
          title="Alertes à traiter"
          href="/alerts"
          state={alertsTodo}
          render={(data) => data.totalItems}
        />
      </div>

      {/* Répartition par statut */}
      <div className="mt-4 rounded-lg border border-black/10 bg-white p-4">
        <h2 className="text-sm font-semibold text-black/70">Répartition par statut</h2>
        <div className="mt-2">
          {statusBreakdown.loading && <p className="text-sm text-black/50">Chargement…</p>}
          <ErrorBanner message={statusBreakdown.error} />
          {statusBreakdown.data && (
            <div className="flex flex-wrap gap-2">
              {OPPORTUNITY_STATUSES.filter((s) => (statusBreakdown.data?.[s] ?? 0) > 0).map((s) => (
                <Link key={s} href={`/opportunities?status=${s}`}>
                  <Badge
                    label={`${opportunityStatusLabels[s]} ${statusBreakdown.data?.[s]}`}
                    className={opportunityStatusBadgeClasses[s]}
                  />
                </Link>
              ))}
            </div>
          )}
        </div>
      </div>

      <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Relances en retard */}
        <Section title="Relances en retard" loading={overdueFollowUps.loading} error={overdueFollowUps.error}>
          {overdueFollowUps.data && overdueFollowUps.data.items.length > 0 ? (
            <ul className="divide-y divide-black/5">
              {overdueFollowUps.data.items.map((fu) => (
                <li key={fu.id} className="py-2">
                  <Link href={`/follow-ups/${fu.id}`} className="block hover:underline">
                    <span className="font-medium text-blue-700">{fu.title}</span>
                    <span className="ml-2 text-sm font-medium text-red-600">
                      {formatDateTime(fu.scheduledAt)}
                    </span>
                  </Link>
                </li>
              ))}
            </ul>
          ) : (
            !overdueFollowUps.loading && <EmptyState label="Aucune relance en retard." />
          )}
        </Section>

        {/* Relances du jour */}
        <Section title="Relances du jour" loading={todayFollowUps.loading} error={todayFollowUps.error}>
          {todayFollowUps.data && todayFollowUps.data.length > 0 ? (
            <ul className="divide-y divide-black/5">
              {todayFollowUps.data.map((fu) => (
                <li key={fu.id} className="py-2">
                  <Link href={`/follow-ups/${fu.id}`} className="block hover:underline">
                    <span className="font-medium text-blue-700">{fu.title}</span>
                    <span className="ml-2 text-sm text-black/60">{formatDateTime(fu.scheduledAt)}</span>
                  </Link>
                </li>
              ))}
            </ul>
          ) : (
            !todayFollowUps.loading && <EmptyState label="Aucune relance aujourd'hui." />
          )}
        </Section>

        {/* Alertes à traiter */}
        <Section
          title="Alertes à traiter"
          loading={alertsTodo.loading}
          error={alertsTodo.error}
          seeAllHref="/alerts"
        >
          {alertsTodo.data && alertsTodo.data.items.length > 0 ? (
            <ul className="divide-y divide-black/5">
              {alertsTodo.data.items.map((alert) => (
                <li key={alert.id} className="flex items-center justify-between py-2">
                  <Link href={`/alerts/${alert.id}`} className="font-medium text-blue-700 hover:underline">
                    {alert.title}
                  </Link>
                  <div className="flex items-center gap-2 text-sm text-black/60">
                    {alert.priority && (
                      <Badge
                        label={alertPriorityLabels[alert.priority]}
                        className={alertPriorityBadgeClasses[alert.priority]}
                      />
                    )}
                    <span>{formatDateTime(alert.dueDate)}</span>
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            !alertsTodo.loading && <EmptyState label="Aucune alerte à traiter." />
          )}
        </Section>

        {/* Opportunités à traiter */}
        <Section
          title="Opportunités à traiter"
          loading={toTreatOpportunities.loading}
          error={toTreatOpportunities.error}
        >
          {toTreatOpportunities.data && toTreatOpportunities.data.items.length > 0 ? (
            <ul className="divide-y divide-black/5">
              {toTreatOpportunities.data.items.map((opp) => (
                <li key={opp.id} className="flex items-center justify-between py-2">
                  <Link href={`/opportunities/${opp.id}`} className="font-medium text-blue-700 hover:underline">
                    {opp.title}
                  </Link>
                  <Badge
                    label={opportunityStatusLabels[opp.status]}
                    className={opportunityStatusBadgeClasses[opp.status]}
                  />
                </li>
              ))}
            </ul>
          ) : (
            !toTreatOpportunities.loading && <EmptyState label="Aucune opportunité à traiter." />
          )}
        </Section>

        {/* Opportunités à fort potentiel */}
        <Section title="Opportunités à fort potentiel" loading={highPotential.loading} error={highPotential.error}>
          {highPotential.data && highPotential.data.length > 0 ? (
            <ul className="divide-y divide-black/5">
              {highPotential.data.map((opp) => (
                <li key={opp.id} className="flex items-center justify-between py-2">
                  <Link href={`/opportunities/${opp.id}`} className="font-medium text-blue-700 hover:underline">
                    {opp.title}
                  </Link>
                  <span className="text-sm font-medium text-green-700">Score {opp.score}</span>
                </li>
              ))}
            </ul>
          ) : (
            !highPotential.loading && <EmptyState label="Aucune opportunité à fort potentiel." />
          )}
        </Section>

        {/* Opportunités récemment ajoutées */}
        <Section
          title="Opportunités récemment ajoutées"
          loading={recentOpportunities.loading}
          error={recentOpportunities.error}
          seeAllHref="/opportunities"
        >
          {recentOpportunities.data && recentOpportunities.data.length > 0 ? (
            <ul className="divide-y divide-black/5">
              {recentOpportunities.data.map((opp) => (
                <li key={opp.id} className="flex items-center justify-between py-2">
                  <Link href={`/opportunities/${opp.id}`} className="font-medium text-blue-700 hover:underline">
                    {opp.title}
                  </Link>
                  <span className="text-sm text-black/60">{formatDate(opp.createdAt)}</span>
                </li>
              ))}
            </ul>
          ) : (
            !recentOpportunities.loading && <EmptyState label="Aucune opportunité récente." />
          )}
        </Section>
      </div>
    </div>
  );
}

function KpiCard<T>({
  title,
  href,
  state,
  render,
  valueClassName = "text-black",
}: {
  title: string;
  href: string;
  state: SectionState<T>;
  render: (data: T) => number;
  valueClassName?: string;
}) {
  return (
    <Link
      href={href}
      className="block rounded-lg border border-black/10 bg-white p-4 transition-colors hover:border-blue-300 hover:bg-blue-50/40"
    >
      <p className="text-sm text-black/60">{title}</p>
      {state.loading ? (
        <p className="mt-1 text-2xl font-bold text-black/30">…</p>
      ) : state.error ? (
        <p className="mt-1 text-sm text-red-600">Erreur</p>
      ) : (
        <p className={`mt-1 text-2xl font-bold ${valueClassName}`}>
          {state.data !== null ? render(state.data) : "—"}
        </p>
      )}
    </Link>
  );
}

function Section({
  title,
  loading,
  error,
  seeAllHref,
  children,
}: {
  title: string;
  loading: boolean;
  error: string | null;
  seeAllHref?: string;
  children: React.ReactNode;
}) {
  return (
    <div className="rounded-lg border border-black/10 bg-white p-4">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold text-black/70">{title}</h2>
        {seeAllHref && (
          <Link href={seeAllHref} className="text-xs font-medium text-blue-700 hover:underline">
            Voir tout
          </Link>
        )}
      </div>
      <div className="mt-2">
        {loading && <p className="text-sm text-black/50">Chargement…</p>}
        <ErrorBanner message={error} />
        {!loading && !error && children}
      </div>
    </div>
  );
}

function EmptyState({ label }: { label: string }) {
  return <p className="py-2 text-sm text-black/50">{label}</p>;
}
