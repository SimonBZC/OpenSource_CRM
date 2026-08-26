import { apiRequest, fetchCollection, type QueryParams } from "@/lib/api/http";
import type {
  Collection,
  Company,
  CompanyWritePayload,
  Contact,
  ContactWritePayload,
  FollowUp,
  FollowUpWritePayload,
  Interaction,
  Opportunity,
  OpportunityAnalyzeResponse,
  OpportunityWritePayload,
  Task,
  TaskWritePayload,
} from "@/lib/api/types";

/**
 * Fabrique un client CRUD générique pour une ressource API Platform.
 * Réutilisable pour les 6 entités du domaine (Phase 4 et Phase 5).
 */
function createResourceClient<TEntity, TWrite>(resourcePath: string) {
  return {
    list(params: QueryParams = {}): Promise<Collection<TEntity>> {
      return fetchCollection<TEntity>(resourcePath, params);
    },
    get(id: number | string): Promise<TEntity> {
      return apiRequest<TEntity>(`${resourcePath}/${id}`);
    },
    create(payload: TWrite): Promise<TEntity> {
      return apiRequest<TEntity>(resourcePath, {
        method: "POST",
        body: payload,
        contentType: "application/ld+json",
      });
    },
    update(id: number | string, payload: Partial<TWrite>): Promise<TEntity> {
      return apiRequest<TEntity>(`${resourcePath}/${id}`, {
        method: "PATCH",
        body: payload,
        contentType: "application/merge-patch+json",
      });
    },
    remove(id: number | string): Promise<void> {
      return apiRequest<void>(`${resourcePath}/${id}`, { method: "DELETE" });
    },
  };
}

export const companiesApi = createResourceClient<Company, CompanyWritePayload>(
  "/api/companies",
);
export const contactsApi = createResourceClient<Contact, ContactWritePayload>(
  "/api/contacts",
);
export const opportunitiesApi = createResourceClient<Opportunity, OpportunityWritePayload>(
  "/api/opportunities",
);

/**
 * Lance l'analyse IA d'une opportunité (Phase 7).
 * Fonctionnalité optionnelle : peut échouer si l'IA n'est pas configurée
 * côté backend (503 "ai_not_configured"), sans impact sur le reste du CRM.
 */
export function analyzeOpportunity(id: number | string): Promise<OpportunityAnalyzeResponse> {
  return apiRequest<OpportunityAnalyzeResponse>(`/api/opportunities/${id}/analyze`, {
    method: "POST",
  });
}

// Clients relances/tâches (Phase 5).
export const followUpsApi = createResourceClient<FollowUp, FollowUpWritePayload>(
  "/api/follow_ups",
);
export const tasksApi = createResourceClient<Task, TaskWritePayload>("/api/tasks");
export const interactionsApi = createResourceClient<Interaction, Partial<Interaction>>(
  "/api/interactions",
);
