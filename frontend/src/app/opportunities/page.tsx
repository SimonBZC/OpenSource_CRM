"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { Badge } from "@/components/ui/Badge";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { Pagination } from "@/components/ui/Pagination";
import { toDisplayMessage } from "@/lib/api/errors";
import type { QueryParams } from "@/lib/api/http";
import { opportunitiesApi } from "@/lib/api/resources";
import {
  OPPORTUNITY_SOURCES,
  OPPORTUNITY_STATUSES,
  type Opportunity,
  type OpportunitySource,
  type OpportunityStatus,
} from "@/lib/api/types";
import { formatDailyRate, formatDate } from "@/lib/format";
import {
  opportunitySourceLabels,
  opportunityStatusBadgeClasses,
  opportunityStatusLabels,
} from "@/lib/labels";

const ITEMS_PER_PAGE = 20;
type RemoteFilter = "all" | "true" | "false";

/**
 * `useSearchParams` impose un Suspense boundary en App Router : on isole donc
 * la lecture des paramètres d'URL initiaux (utilisés par les liens du
 * dashboard, ex. "Opportunités à traiter") dans un composant enfant dédié.
 */
export default function OpportunitiesPage() {
  return (
    <Suspense fallback={<div className="p-8 text-sm text-black/50">Chargement…</div>}>
      <OpportunitiesPageContent />
    </Suspense>
  );
}

function OpportunitiesPageContent() {
  const searchParams = useSearchParams();
  const urlStatuses = searchParams.getAll("status") as OpportunityStatus[];
  // Cas particulier des liens du dashboard combinant plusieurs statuts (ex.
  // "Opportunités à traiter" = new + to_review) : le <select> de statut ne
  // peut représenter qu'une seule valeur, on garde alors ce filtre "forcé" à
  // part, tant que l'utilisateur n'a pas repris la main sur le select.
  const [statusOverride, setStatusOverride] = useState<OpportunityStatus[] | null>(
    urlStatuses.length > 1 ? urlStatuses : null,
  );

  const [items, setItems] = useState<Opportunity[]>([]);
  const [totalItems, setTotalItems] = useState(0);
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState<OpportunityStatus | "">(
    urlStatuses.length === 1 ? urlStatuses[0] : "",
  );
  const [source, setSource] = useState<OpportunitySource | "">("");

  // Recherche texte, avec debounce ~300ms avant application au filtre réel.
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");

  const [scoreMin, setScoreMin] = useState("");
  const [scoreMax, setScoreMax] = useState("");
  const [rateMin, setRateMin] = useState("");
  const [rateMax, setRateMax] = useState("");
  const [remote, setRemote] = useState<RemoteFilter>("all");
  const [selectedTechnologies, setSelectedTechnologies] = useState<string[]>([]);
  const [startDateAfter, setStartDateAfter] = useState("");
  const [createdAfter, setCreatedAfter] = useState("");

  const [availableTechnologies, setAvailableTechnologies] = useState<string[]>([]);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Liste des technologies réellement présentes dans les données (correspondance
  // exacte et sensible à la casse côté API : pas de champ texte libre possible).
  useEffect(() => {
    opportunitiesApi
      .list({ itemsPerPage: 100 })
      .then((res) => {
        const set = new Set<string>();
        for (const opp of res.items) {
          for (const tech of opp.technologies) set.add(tech);
        }
        setAvailableTechnologies([...set].sort((a, b) => a.localeCompare(b)));
      })
      .catch(() => {
        // Non bloquant : le filtre technologie reste vide si la requête échoue.
      });
  }, []);

  // Debounce de la recherche texte.
  useEffect(() => {
    const timeout = setTimeout(() => {
      setPage(1);
      setSearch(searchInput);
    }, 300);
    return () => clearTimeout(timeout);
  }, [searchInput]);

  useEffect(() => {
    let cancelled = false;
    const timeout = setTimeout(() => {
      if (cancelled) return;
      setLoading(true);
      setError(null);

      const params: QueryParams = {
        page,
        itemsPerPage: ITEMS_PER_PAGE,
        "order[createdAt]": "desc",
        source: source || undefined,
        search: search || undefined,
        "score[gte]": scoreMin !== "" ? Number(scoreMin) : undefined,
        "score[lte]": scoreMax !== "" ? Number(scoreMax) : undefined,
        "dailyRateMin[gte]": rateMin !== "" ? Number(rateMin) : undefined,
        "dailyRateMax[lte]": rateMax !== "" ? Number(rateMax) : undefined,
        remote: remote === "all" ? undefined : remote,
        "technology[]": selectedTechnologies.length > 0 ? selectedTechnologies : undefined,
        "startDate[after]": startDateAfter ? `${startDateAfter}T00:00:00` : undefined,
        "createdAt[after]": createdAfter ? `${createdAfter}T00:00:00` : undefined,
      };
      if (statusOverride) {
        params["status[]"] = statusOverride;
      } else if (status) {
        params.status = status;
      }

      opportunitiesApi
        .list(params)
        .then((res) => {
          if (cancelled) return;
          setItems(res.items);
          setTotalItems(res.totalItems);
        })
        .catch((err) => {
          if (cancelled) return;
          setError(toDisplayMessage(err));
        })
        .finally(() => {
          if (cancelled) return;
          setLoading(false);
        });
    }, 0);

    return () => {
      cancelled = true;
      clearTimeout(timeout);
    };
  }, [
    page,
    status,
    statusOverride,
    source,
    search,
    scoreMin,
    scoreMax,
    rateMin,
    rateMax,
    remote,
    selectedTechnologies,
    startDateAfter,
    createdAfter,
  ]);

  function resetFilters() {
    setPage(1);
    setStatus("");
    setStatusOverride(null);
    setSource("");
    setSearchInput("");
    setSearch("");
    setScoreMin("");
    setScoreMax("");
    setRateMin("");
    setRateMax("");
    setRemote("all");
    setSelectedTechnologies([]);
    setStartDateAfter("");
    setCreatedAfter("");
  }

  function toggleTechnology(tech: string) {
    setPage(1);
    setSelectedTechnologies((prev) =>
      prev.includes(tech) ? prev.filter((t) => t !== tech) : [...prev, tech],
    );
  }

  return (
    <div className="p-8">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold tracking-tight">Opportunités</h1>
        <Link
          href="/opportunities/new"
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
          Nouvelle opportunité
        </Link>
      </div>

      <div className="mt-4 rounded-lg border border-black/10 bg-white p-4">
        <div className="flex flex-wrap items-end gap-3">
          <div className="min-w-[220px] flex-1">
            <label className="block text-xs font-medium text-black/60">Recherche</label>
            <input
              type="text"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Titre ou description…"
              className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
            />
          </div>

          <div>
            <label className="block text-xs font-medium text-black/60">Statut</label>
            <select
              value={statusOverride ? "" : status}
              disabled={statusOverride !== null}
              onChange={(e) => {
                setPage(1);
                setStatusOverride(null);
                setStatus(e.target.value as OpportunityStatus | "");
              }}
              className="mt-1 rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 disabled:opacity-50"
            >
              <option value="">Tous les statuts</option>
              {OPPORTUNITY_STATUSES.map((s) => (
                <option key={s} value={s}>
                  {opportunityStatusLabels[s]}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-xs font-medium text-black/60">Source</label>
            <select
              value={source}
              onChange={(e) => {
                setPage(1);
                setSource(e.target.value as OpportunitySource | "");
              }}
              className="mt-1 rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
            >
              <option value="">Toutes les sources</option>
              {OPPORTUNITY_SOURCES.map((s) => (
                <option key={s} value={s}>
                  {opportunitySourceLabels[s]}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-xs font-medium text-black/60">Remote</label>
            <select
              value={remote}
              onChange={(e) => {
                setPage(1);
                setRemote(e.target.value as RemoteFilter);
              }}
              className="mt-1 rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
            >
              <option value="all">Tous</option>
              <option value="true">Oui</option>
              <option value="false">Non</option>
            </select>
          </div>
        </div>

        <div className="mt-3 flex flex-wrap items-end gap-3">
          <div>
            <label className="block text-xs font-medium text-black/60">Score min</label>
            <input
              type="number"
              min={0}
              max={100}
              value={scoreMin}
              onChange={(e) => {
                setPage(1);
                setScoreMin(e.target.value);
              }}
              className="mt-1 w-24 rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-black/60">Score max</label>
            <input
              type="number"
              min={0}
              max={100}
              value={scoreMax}
              onChange={(e) => {
                setPage(1);
                setScoreMax(e.target.value);
              }}
              className="mt-1 w-24 rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
            />
          </div>

          <div>
            <label className="block text-xs font-medium text-black/60">TJM min ≥</label>
            <input
              type="number"
              value={rateMin}
              onChange={(e) => {
                setPage(1);
                setRateMin(e.target.value);
              }}
              className="mt-1 w-28 rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-black/60">TJM max ≤</label>
            <input
              type="number"
              value={rateMax}
              onChange={(e) => {
                setPage(1);
                setRateMax(e.target.value);
              }}
              className="mt-1 w-28 rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
            />
          </div>

          <div>
            <label className="block text-xs font-medium text-black/60">Démarrage à partir du</label>
            <input
              type="date"
              value={startDateAfter}
              onChange={(e) => {
                setPage(1);
                setStartDateAfter(e.target.value);
              }}
              className="mt-1 rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-black/60">Créée après le</label>
            <input
              type="date"
              value={createdAfter}
              onChange={(e) => {
                setPage(1);
                setCreatedAfter(e.target.value);
              }}
              className="mt-1 rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
            />
          </div>

          <button
            type="button"
            onClick={resetFilters}
            className="rounded-md border border-black/15 px-3 py-2 text-sm font-medium text-black/60 hover:bg-black/5"
          >
            Réinitialiser les filtres
          </button>
        </div>

        {availableTechnologies.length > 0 && (
          <div className="mt-3">
            <label className="block text-xs font-medium text-black/60">Technologies</label>
            <div className="mt-1 flex flex-wrap gap-2">
              {availableTechnologies.map((tech) => {
                const active = selectedTechnologies.includes(tech);
                return (
                  <button
                    key={tech}
                    type="button"
                    onClick={() => toggleTechnology(tech)}
                    className={`rounded-full border px-2.5 py-0.5 text-xs font-medium ${
                      active
                        ? "border-blue-600 bg-blue-600 text-white"
                        : "border-black/15 bg-white text-black/70 hover:bg-black/5"
                    }`}
                  >
                    {tech}
                  </button>
                );
              })}
            </div>
          </div>
        )}

        {statusOverride && (
          <p className="mt-3 text-xs text-black/50">
            Filtre de statut appliqué depuis le tableau de bord ({statusOverride.map((s) => opportunityStatusLabels[s]).join(", ")}).{" "}
            <button
              type="button"
              onClick={() => {
                setPage(1);
                setStatusOverride(null);
              }}
              className="text-blue-700 hover:underline"
            >
              Réinitialiser
            </button>
          </p>
        )}
      </div>

      <div className="mt-4">
        <ErrorBanner message={error} />
      </div>

      <div className="mt-4 overflow-hidden rounded-lg border border-black/10 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="bg-black/[0.03] text-xs uppercase tracking-wide text-black/50">
            <tr>
              <th className="px-4 py-3 font-medium">Titre</th>
              <th className="px-4 py-3 font-medium">Entreprise</th>
              <th className="px-4 py-3 font-medium">Statut</th>
              <th className="px-4 py-3 font-medium">Score</th>
              <th className="px-4 py-3 font-medium">TJM</th>
              <th className="px-4 py-3 font-medium">Créée le</th>
            </tr>
          </thead>
          <tbody>
            {items.map((opportunity) => (
              <tr
                key={opportunity.id}
                className="border-t border-black/5 hover:bg-black/[0.02]"
              >
                <td className="px-4 py-3">
                  <Link
                    href={`/opportunities/${opportunity.id}`}
                    className="font-medium text-blue-700 hover:underline"
                  >
                    {opportunity.title}
                  </Link>
                </td>
                <td className="px-4 py-3 text-black/70">
                  {opportunity.companyName ?? "—"}
                </td>
                <td className="px-4 py-3">
                  <Badge
                    label={opportunityStatusLabels[opportunity.status]}
                    className={opportunityStatusBadgeClasses[opportunity.status]}
                  />
                </td>
                <td className="px-4 py-3 text-black/70">
                  {opportunity.score ?? "—"}
                </td>
                <td className="px-4 py-3 text-black/70">
                  {formatDailyRate(opportunity.dailyRateMin, opportunity.dailyRateMax)}
                </td>
                <td className="px-4 py-3 text-black/70">
                  {formatDate(opportunity.createdAt)}
                </td>
              </tr>
            ))}
            {!loading && items.length === 0 && (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-black/50">
                  Aucune opportunité trouvée.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      <div className="mt-4">
        <Pagination
          page={page}
          itemsPerPage={ITEMS_PER_PAGE}
          totalItems={totalItems}
          onPageChange={setPage}
        />
      </div>
    </div>
  );
}
