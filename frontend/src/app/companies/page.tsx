"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Badge } from "@/components/ui/Badge";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { Pagination } from "@/components/ui/Pagination";
import { toDisplayMessage } from "@/lib/api/errors";
import { companiesApi } from "@/lib/api/resources";
import type { Company } from "@/lib/api/types";
import { formatDate } from "@/lib/format";
import { companySourceLabels } from "@/lib/labels";

const ITEMS_PER_PAGE = 20;

export default function CompaniesPage() {
  const [items, setItems] = useState<Company[]>([]);
  const [totalItems, setTotalItems] = useState(0);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    const timeout = setTimeout(
      () => {
        if (cancelled) return;
        setLoading(true);
        setError(null);
        companiesApi
          .list({
            page,
            itemsPerPage: ITEMS_PER_PAGE,
            name: search || undefined,
            "order[name]": "asc",
          })
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
      },
      search ? 300 : 0,
    );
    return () => {
      cancelled = true;
      clearTimeout(timeout);
    };
  }, [page, search]);

  return (
    <div className="p-8">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold tracking-tight">Entreprises</h1>
        <Link
          href="/companies/new"
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
          Nouvelle entreprise
        </Link>
      </div>

      <div className="mt-4">
        <input
          type="search"
          value={search}
          onChange={(e) => {
            setPage(1);
            setSearch(e.target.value);
          }}
          placeholder="Rechercher par nom..."
          className="w-full max-w-sm rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
      </div>

      <div className="mt-4">
        <ErrorBanner message={error} />
      </div>

      <div className="mt-4 overflow-hidden rounded-lg border border-black/10 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="bg-black/[0.03] text-xs uppercase tracking-wide text-black/50">
            <tr>
              <th className="px-4 py-3 font-medium">Nom</th>
              <th className="px-4 py-3 font-medium">Secteur</th>
              <th className="px-4 py-3 font-medium">Localisation</th>
              <th className="px-4 py-3 font-medium">Source</th>
              <th className="px-4 py-3 font-medium">Dernier contact</th>
            </tr>
          </thead>
          <tbody>
            {items.map((company) => (
              <tr
                key={company.id}
                className="border-t border-black/5 hover:bg-black/[0.02]"
              >
                <td className="px-4 py-3">
                  <Link
                    href={`/companies/${company.id}`}
                    className="font-medium text-blue-700 hover:underline"
                  >
                    {company.name}
                  </Link>
                </td>
                <td className="px-4 py-3 text-black/70">{company.sector ?? "—"}</td>
                <td className="px-4 py-3 text-black/70">{company.location ?? "—"}</td>
                <td className="px-4 py-3">
                  {company.source ? (
                    <Badge label={companySourceLabels[company.source]} />
                  ) : (
                    "—"
                  )}
                </td>
                <td className="px-4 py-3 text-black/70">
                  {formatDate(company.lastInteractionAt)}
                </td>
              </tr>
            ))}
            {!loading && items.length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-black/50">
                  Aucune entreprise trouvée.
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
