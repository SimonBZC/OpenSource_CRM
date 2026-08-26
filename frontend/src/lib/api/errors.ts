/**
 * Erreurs typées du client API.
 *
 * Format 422 réel de l'API (vérifié par curl) :
 * {
 *   "@type": "ConstraintViolation",
 *   "status": 422,
 *   "violations": [{ "propertyPath": "name", "message": "...", "code": "..." }],
 *   "detail": "name: This value should not be blank.",
 *   "title": "An error occurred"
 * }
 */

export interface ApiViolation {
  propertyPath: string;
  message: string;
  code?: string;
}

/** Erreur de validation (HTTP 422) : contient les violations par champ. */
export class ApiValidationError extends Error {
  readonly status = 422;
  readonly violations: ApiViolation[];

  constructor(violations: ApiViolation[], detail?: string) {
    super(detail ?? "Erreur de validation");
    this.name = "ApiValidationError";
    this.violations = violations;
  }

  /** Message de validation pour un champ donné, s'il existe. */
  messageFor(propertyPath: string): string | undefined {
    return this.violations.find((v) => v.propertyPath === propertyPath)?.message;
  }
}

/** Ressource introuvable (HTTP 404). */
export class ApiNotFoundError extends Error {
  readonly status = 404;

  constructor(message = "Ressource introuvable") {
    super(message);
    this.name = "ApiNotFoundError";
  }
}

/** Toute autre erreur HTTP renvoyée par l'API (4xx/5xx non gérés ci-dessus). */
export class ApiError extends Error {
  readonly status: number;
  /** Code d'erreur métier optionnel renvoyé par l'API (ex. "ai_not_configured"). */
  readonly errorCode?: string;

  constructor(status: number, message: string, errorCode?: string) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errorCode = errorCode;
  }
}

/** Échec réseau (API injoignable, CORS, timeout, etc). */
export class ApiNetworkError extends Error {
  constructor(message = "Impossible de contacter l'API. Vérifiez que le backend est démarré.") {
    super(message);
    this.name = "ApiNetworkError";
  }
}

export type KnownApiError =
  | ApiValidationError
  | ApiNotFoundError
  | ApiError
  | ApiNetworkError;

/** Message d'erreur lisible à afficher à l'utilisateur, pour tout type d'erreur. */
export function toDisplayMessage(error: unknown): string {
  if (error instanceof ApiValidationError) {
    return error.message || "Le formulaire contient des erreurs.";
  }
  if (
    error instanceof ApiNotFoundError ||
    error instanceof ApiError ||
    error instanceof ApiNetworkError
  ) {
    return error.message;
  }
  if (error instanceof Error) {
    return error.message;
  }
  return "Une erreur inattendue est survenue.";
}
