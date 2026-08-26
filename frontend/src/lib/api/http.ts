import { env } from "@/lib/env";
import {
  ApiError,
  ApiNetworkError,
  ApiNotFoundError,
  ApiValidationError,
  type ApiViolation,
} from "@/lib/api/errors";
import type { Collection, HydraCollection } from "@/lib/api/types";

/**
 * Client HTTP bas niveau pour l'API Symfony / API Platform.
 *
 * IMPORTANT : ce module est destiné à être appelé UNIQUEMENT depuis des
 * Client Components (navigateur). `NEXT_PUBLIC_API_URL` pointe vers
 * `localhost`, résolu côté hôte : un appel depuis le runtime serveur Next
 * (dans le container Docker) ne pourrait pas joindre le backend.
 */

export type QueryValue =
  | string
  | number
  | boolean
  | undefined
  | null
  | Array<string | number>;

export type QueryParams = Record<string, QueryValue>;

/**
 * Construit une query string à partir de paramètres de filtre/tri/pagination.
 * Les valeurs de type tableau sont répétées sous la même clé, ce qui permet
 * de passer directement des clés comme "status[]" pour les filtres API
 * Platform multi-valeurs (ex. status[]=won&status[]=lost).
 */
export function buildQueryString(params: QueryParams = {}): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === "") continue;
    if (Array.isArray(value)) {
      for (const item of value) {
        if (item === undefined || item === null || item === "") continue;
        search.append(key, String(item));
      }
      continue;
    }
    search.append(key, String(value));
  }
  const qs = search.toString();
  return qs ? `?${qs}` : "";
}

interface RequestOptions {
  method?: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  body?: unknown;
  /** Content-Type explicite. Par défaut : application/ld+json en écriture. */
  contentType?: string;
}

interface ValidationErrorBody {
  "@type"?: string;
  status?: number;
  violations?: ApiViolation[];
  detail?: string;
  title?: string;
  errorCode?: string;
}

async function parseErrorBody(response: Response): Promise<ValidationErrorBody | null> {
  try {
    return (await response.json()) as ValidationErrorBody;
  } catch {
    return null;
  }
}

/** Effectue une requête HTTP vers l'API et retourne le JSON typé. */
export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { method = "GET", body, contentType } = options;
  const url = `${env.apiUrl}${path}`;

  const headers: Record<string, string> = {
    Accept: "application/ld+json",
  };
  if (body !== undefined) {
    headers["Content-Type"] = contentType ?? "application/ld+json";
  }

  let response: Response;
  try {
    response = await fetch(url, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch {
    throw new ApiNetworkError();
  }

  if (response.status === 204) {
    return undefined as T;
  }

  if (!response.ok) {
    if (response.status === 422) {
      const errorBody = await parseErrorBody(response);
      throw new ApiValidationError(errorBody?.violations ?? [], errorBody?.detail);
    }
    if (response.status === 404) {
      throw new ApiNotFoundError();
    }
    const errorBody = await parseErrorBody(response);
    throw new ApiError(
      response.status,
      errorBody?.detail ?? errorBody?.title ?? `Erreur API (HTTP ${response.status})`,
      errorBody?.errorCode,
    );
  }

  const text = await response.text();
  if (!text) return undefined as T;
  return JSON.parse(text) as T;
}

/** Récupère une collection Hydra et la normalise en {items, totalItems}. */
export async function fetchCollection<T>(
  resourcePath: string,
  params: QueryParams = {},
): Promise<Collection<T>> {
  const data = await apiRequest<HydraCollection<T>>(`${resourcePath}${buildQueryString(params)}`);
  return { items: data.member, totalItems: data.totalItems };
}
