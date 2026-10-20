import type {
  FollowUpStatus,
  InteractionType,
  OpportunitySource,
  OpportunityStatus,
  AlertPriority,
  AlertStatus,
} from "@/lib/api/types";

/** Libellés FR + classes Tailwind de badge pour les statuts d'opportunité. */
export const opportunityStatusLabels: Record<OpportunityStatus, string> = {
  new: "Nouvelle",
  to_review: "À qualifier",
  contacted: "Contactée",
  waiting: "En attente",
  follow_up: "Relance",
  interview: "Entretien",
  won: "Gagnée",
  lost: "Perdue",
  archived: "Archivée",
};

export const opportunityStatusBadgeClasses: Record<OpportunityStatus, string> = {
  new: "bg-blue-100 text-blue-700",
  to_review: "bg-amber-100 text-amber-800",
  contacted: "bg-indigo-100 text-indigo-700",
  waiting: "bg-slate-200 text-slate-700",
  follow_up: "bg-purple-100 text-purple-700",
  interview: "bg-cyan-100 text-cyan-700",
  won: "bg-green-100 text-green-700",
  lost: "bg-red-100 text-red-700",
  archived: "bg-gray-200 text-gray-600",
};

export const opportunitySourceLabels: Record<OpportunitySource, string> = {
  linkedin: "LinkedIn",
  malt: "Malt",
  email: "Email",
  website: "Site web",
  recommendation: "Recommandation",
  manual: "Saisie manuelle",
  other: "Autre",
};

export const followUpStatusLabels: Record<FollowUpStatus, string> = {
  todo: "À faire",
  done: "Terminée",
  cancelled: "Annulée",
};

export const followUpStatusBadgeClasses: Record<FollowUpStatus, string> = {
  todo: "bg-blue-100 text-blue-700",
  done: "bg-green-100 text-green-700",
  cancelled: "bg-gray-200 text-gray-600",
};

export const alertStatusLabels: Record<AlertStatus, string> = {
  todo: "À faire",
  in_progress: "En cours",
  done: "Terminée",
  cancelled: "Annulée",
};

export const alertStatusBadgeClasses: Record<AlertStatus, string> = {
  todo: "bg-blue-100 text-blue-700",
  in_progress: "bg-indigo-100 text-indigo-700",
  done: "bg-green-100 text-green-700",
  cancelled: "bg-gray-200 text-gray-600",
};

export const alertPriorityLabels: Record<AlertPriority, string> = {
  low: "Basse",
  medium: "Moyenne",
  high: "Haute",
  urgent: "Urgente",
};

export const alertPriorityBadgeClasses: Record<AlertPriority, string> = {
  low: "bg-gray-100 text-gray-600",
  medium: "bg-blue-100 text-blue-700",
  high: "bg-amber-100 text-amber-800",
  urgent: "bg-red-100 text-red-700",
};

export const interactionTypeLabels: Record<InteractionType, string> = {
  email: "Email",
  phone: "Téléphone",
  linkedin: "LinkedIn",
  meeting: "Rendez-vous",
  note: "Note",
  other: "Autre",
};
