export function Pagination({
  page,
  itemsPerPage,
  totalItems,
  onPageChange,
}: {
  page: number;
  itemsPerPage: number;
  totalItems: number;
  onPageChange: (page: number) => void;
}) {
  const totalPages = Math.max(1, Math.ceil(totalItems / itemsPerPage));
  if (totalPages <= 1) return null;

  return (
    <div className="flex items-center justify-between border-t border-black/10 pt-3 text-sm">
      <p className="text-black/60">
        Page {page} / {totalPages} · {totalItems} résultat{totalItems > 1 ? "s" : ""}
      </p>
      <div className="flex gap-2">
        <button
          type="button"
          onClick={() => onPageChange(page - 1)}
          disabled={page <= 1}
          className="rounded-md border border-black/15 px-3 py-1 disabled:opacity-40"
        >
          Précédent
        </button>
        <button
          type="button"
          onClick={() => onPageChange(page + 1)}
          disabled={page >= totalPages}
          className="rounded-md border border-black/15 px-3 py-1 disabled:opacity-40"
        >
          Suivant
        </button>
      </div>
    </div>
  );
}
