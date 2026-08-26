export function ComingSoon({ title }: { title: string }) {
  return (
    <div className="p-8">
      <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
      <p className="mt-3 max-w-md rounded-lg border border-dashed border-black/15 bg-white px-4 py-3 text-sm text-black/60">
        Cette section sera disponible en Phase 5.
      </p>
    </div>
  );
}
