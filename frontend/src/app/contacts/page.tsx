"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { Pagination } from "@/components/ui/Pagination";
import { toDisplayMessage } from "@/lib/api/errors";
import { idFromIri } from "@/lib/api/iri";
import { contactsApi } from "@/lib/api/resources";
import type { Contact } from "@/lib/api/types";

const ITEMS_PER_PAGE = 20;

/**
 * Paramètres de pré-remplissage des formulaires Opportunité et Relance
 * (FR-4, FR-5) : le contact, et son entreprise s'il en a une.
 */
function prefillQuery(contact: Contact): string {
  const params = new URLSearchParams({ contact: String(contact.id) });
  const companyId = idFromIri(contact.company);
  if (companyId !== null) params.set("company", String(companyId));
  return params.toString();
}

export default function ContactsPage() {
  const [items, setItems] = useState<Contact[]>([]);
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
        contactsApi
          .list({
            page,
            itemsPerPage: ITEMS_PER_PAGE,
            lastName: search || undefined,
            "order[lastName]": "asc",
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
        <h1 className="text-2xl font-bold tracking-tight">Contacts</h1>
        <Link
          href="/contacts/new"
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
          Nouveau contact
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
              <th className="px-4 py-3 font-medium">Poste</th>
              <th className="px-4 py-3 font-medium">Email</th>
              <th className="px-4 py-3 font-medium">Téléphone</th>
              <th className="px-4 py-3 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            {items.map((contact) => (
              <tr
                key={contact.id}
                className="border-t border-black/5 hover:bg-black/[0.02]"
              >
                <td className="px-4 py-3">
                  <Link
                    href={`/contacts/${contact.id}`}
                    className="font-medium text-blue-700 hover:underline"
                  >
                    {[contact.firstName, contact.lastName].filter(Boolean).join(" ")}
                  </Link>
                </td>
                <td className="px-4 py-3 text-black/70">{contact.jobTitle ?? "—"}</td>
                <td className="px-4 py-3 text-black/70">{contact.email ?? "—"}</td>
                <td className="px-4 py-3 text-black/70">{contact.phone ?? "—"}</td>
                <td className="px-4 py-3">
                  <div className="flex gap-1">
                    <Link
                      href={`/opportunities/new?${prefillQuery(contact)}`}
                      title="Nouvelle opportunité"
                      aria-label="Nouvelle opportunité"
                      className="rounded-md p-1.5 text-blue-700 hover:bg-blue-50"
                    >
                      {/* Mallette : opportunité */}
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5" aria-hidden="true">
                        <rect x="3" y="7" width="18" height="13" rx="2" />
                        <path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" />
                        <path d="M3 13h18" />
                      </svg>
                    </Link>
                    <Link
                      href={`/follow-ups/new?${prefillQuery(contact)}`}
                      title="Nouvelle relance"
                      aria-label="Nouvelle relance"
                      className="rounded-md p-1.5 text-blue-700 hover:bg-blue-50"
                    >
                      {/* Cloche : relance */}
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5" aria-hidden="true">
                        <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
                        <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
                      </svg>
                    </Link>
                  </div>
                </td>
              </tr>
            ))}
            {!loading && items.length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-black/50">
                  Aucun contact trouvé.
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
