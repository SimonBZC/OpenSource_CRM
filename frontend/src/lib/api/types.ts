/**
 * Types du contrat API (vérifiés par appels HTTP réels sur le backend
 * Symfony / API Platform 4.3). Voir `src/lib/api/http.ts` pour le client.
 */

// ---------------------------------------------------------------------------
// Enums (valeurs "backed" transmises sur le fil, en minuscules)
// ---------------------------------------------------------------------------

export const OPPORTUNITY_STATUSES = [
  "new",
  "to_review",
  "contacted",
  "waiting",
  "follow_up",
  "interview",
  "won",
  "lost",
  "archived",
] as const;
export type OpportunityStatus = (typeof OPPORTUNITY_STATUSES)[number];

export const OPPORTUNITY_SOURCES = [
  "linkedin",
  "malt",
  "email",
  "website",
  "recommendation",
  "manual",
  "other",
] as const;
export type OpportunitySource = (typeof OPPORTUNITY_SOURCES)[number];

// Le champ `source` de Company réutilise les mêmes valeurs (vérifié sur les
// fixtures : "linkedin", "malt", ...). Aucun enum dédié n'est exposé par
// l'API pour Company : on réutilise donc OpportunitySource par cohérence.
export type CompanySource = OpportunitySource;

export const FOLLOW_UP_STATUSES = ["todo", "done", "cancelled"] as const;
export type FollowUpStatus = (typeof FOLLOW_UP_STATUSES)[number];

export const TASK_STATUSES = ["todo", "in_progress", "done", "cancelled"] as const;
export type TaskStatus = (typeof TASK_STATUSES)[number];

export const TASK_PRIORITIES = ["low", "medium", "high", "urgent"] as const;
export type TaskPriority = (typeof TASK_PRIORITIES)[number];

export const INTERACTION_TYPES = [
  "email",
  "phone",
  "linkedin",
  "meeting",
  "note",
  "other",
] as const;
export type InteractionType = (typeof INTERACTION_TYPES)[number];

// ---------------------------------------------------------------------------
// IRI helper type : chaîne de la forme "/api/companies/8"
// ---------------------------------------------------------------------------
export type Iri = string;

// ---------------------------------------------------------------------------
// Entités (forme lue depuis l'API)
// ---------------------------------------------------------------------------

export interface Company {
  id: number;
  name: string;
  website: string | null;
  sector: string | null;
  location: string | null;
  description: string | null;
  notes: string | null;
  source: CompanySource | null;
  createdAt: string;
  lastInteractionAt: string | null;
  contacts: Iri[];
  opportunities: Iri[];
  interactions: Iri[];
}

export interface Contact {
  id: number;
  firstName: string | null;
  lastName: string;
  email: string | null;
  phone: string | null;
  linkedin: string | null;
  jobTitle: string | null;
  company: Iri | null;
  notes: string | null;
  createdAt: string;
  interactions: Iri[];
  opportunities: Iri[];
}

export interface Opportunity {
  id: number;
  title: string;
  description: string | null;
  company: Iri | null;
  contact: Iri | null;
  source: OpportunitySource | null;
  url: string | null;
  status: OpportunityStatus;
  dailyRateMin: number | null;
  dailyRateMax: number | null;
  remote: boolean | null;
  location: string | null;
  duration: string | null;
  startDate: string | null;
  technologies: string[];
  score: number | null;
  scoreReason: string | null;
  notes: string | null;
  createdAt: string;
  updatedAt: string;
  interactions: Iri[];
  followUps: Iri[];
  tasks: Iri[];
  companyName: string | null;
}

export interface FollowUp {
  id: number;
  title: string;
  description: string | null;
  scheduledAt: string;
  status: FollowUpStatus;
  completedAt: string | null;
  createdAt: string;
  company: Iri | null;
  contact: Iri | null;
  opportunity: Iri | null;
}

export interface Task {
  id: number;
  title: string;
  description: string | null;
  dueDate: string | null;
  status: TaskStatus;
  priority: TaskPriority | null;
  createdAt: string;
  completedAt: string | null;
  opportunity: Iri | null;
  contact: Iri | null;
}

export interface Interaction {
  id: number;
  type: InteractionType;
  occurredAt: string;
  content: string | null;
  contact: Iri | null;
  company: Iri | null;
  opportunity: Iri | null;
  createdAt: string;
}

// ---------------------------------------------------------------------------
// Payloads d'écriture : on exclut les champs en lecture seule
// (id, createdAt, updatedAt, companyName, collections inverses).
// ---------------------------------------------------------------------------

export type CompanyWritePayload = Partial<
  Pick<
    Company,
    | "name"
    | "website"
    | "sector"
    | "location"
    | "description"
    | "notes"
    | "source"
    | "lastInteractionAt"
  >
>;

export type ContactWritePayload = Partial<
  Pick<
    Contact,
    | "firstName"
    | "lastName"
    | "email"
    | "phone"
    | "linkedin"
    | "jobTitle"
    | "company"
    | "notes"
  >
>;

export type OpportunityWritePayload = Partial<
  Pick<
    Opportunity,
    | "title"
    | "description"
    | "company"
    | "contact"
    | "source"
    | "url"
    | "status"
    | "dailyRateMin"
    | "dailyRateMax"
    | "remote"
    | "location"
    | "duration"
    | "startDate"
    | "technologies"
    | "notes"
  >
>;

export type FollowUpWritePayload = Partial<
  Pick<
    FollowUp,
    "title" | "description" | "scheduledAt" | "status" | "completedAt" | "company" | "contact" | "opportunity"
  >
>;

export type TaskWritePayload = Partial<
  Pick<
    Task,
    "title" | "description" | "dueDate" | "status" | "priority" | "completedAt" | "opportunity" | "contact"
  >
>;

// ---------------------------------------------------------------------------
// Analyse IA d'une opportunité (Phase 7)
// POST /api/opportunities/{id}/analyze
// ---------------------------------------------------------------------------

export interface OpportunityAnalysis {
  score: number;
  relevant: boolean;
  technologies: string[];
  tjmMin: number | null;
  tjmMax: number | null;
  remote: boolean | null;
  location: string | null;
  duration: string | null;
  startDate: string | null;
  reason: string;
  missingInformation: string[];
}

export interface OpportunityAnalyzeResponse {
  opportunity: Opportunity;
  analysis: OpportunityAnalysis;
  appliedFields: string[];
}

// ---------------------------------------------------------------------------
// Enveloppe de collection Hydra (API Platform 4.3, sans préfixe "hydra:")
// ---------------------------------------------------------------------------

export interface HydraView {
  "@id": string;
  "@type": "PartialCollectionView";
  first?: string;
  last?: string;
  next?: string;
  previous?: string;
}

export interface HydraCollection<T> {
  "@context": string;
  "@id": string;
  "@type": "Collection";
  totalItems: number;
  member: T[];
  view?: HydraView;
}

export interface Collection<T> {
  items: T[];
  totalItems: number;
}
