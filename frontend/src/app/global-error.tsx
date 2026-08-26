"use client"; // Error boundaries must be Client Components

export default function GlobalError({
  error,
  retry,
}: {
  error: Error & { digest?: string };
  retry: () => void;
}) {
  return (
    // global-error must include html and body tags
    <html lang="fr" className="h-full antialiased">
      <body className="flex h-full min-h-full items-center justify-center bg-white p-6">
        <div className="max-w-md text-center">
          <h2 className="text-lg font-semibold text-gray-900">
            Une erreur inattendue est survenue
          </h2>
          <p className="mt-2 text-sm text-gray-500">
            {error.message || "Veuillez réessayer."}
          </p>
          <button
            type="button"
            onClick={() => retry()}
            className="mt-4 rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
          >
            Réessayer
          </button>
        </div>
      </body>
    </html>
  );
}
