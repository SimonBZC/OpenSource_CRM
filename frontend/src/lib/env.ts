/**
 * Configuration d'environnement du frontend.
 *
 * `NEXT_PUBLIC_API_URL` est inlinée au build par Next.js : elle doit être
 * référencée littéralement (pas d'accès dynamique à `process.env`).
 *
 * Phase 1 : la valeur est seulement lue et exposée, l'API n'est pas consommée.
 */
const apiUrl = process.env.NEXT_PUBLIC_API_URL;

if (!apiUrl) {
  // Ni throw ni crash : en Phase 1 aucune requête n'est émise.
  console.warn(
    "[FreeCRM] NEXT_PUBLIC_API_URL n'est pas définie. Voir frontend/.env.example.",
  );
}

export const env = {
  /** URL de base de l'API backend (ex. http://localhost:8080). */
  apiUrl: apiUrl ?? "",
} as const;
