"use client";

import { FieldError } from "@/components/ui/FieldError";
import type { ApiValidationError } from "@/lib/api/errors";
import { companyIri, contactIri, opportunityIri } from "@/lib/api/iri";
import {
  FOLLOW_UP_STATUSES,
  type Company,
  type Contact,
  type FollowUpWritePayload,
  type Opportunity,
} from "@/lib/api/types";
import { fromDateTimeInputValue, toDateTimeInputValue } from "@/lib/format";
import { followUpStatusLabels } from "@/lib/labels";

export function FollowUpFormFields({
  values,
  onChange,
  validationError,
  companies,
  contacts,
  opportunities,
}: {
  values: FollowUpWritePayload;
  onChange: <K extends keyof FollowUpWritePayload>(
    field: K,
    value: FollowUpWritePayload[K],
  ) => void;
  validationError: ApiValidationError | null;
  companies: Company[];
  contacts: Contact[];
  opportunities: Opportunity[];
}) {
  // Le statut pilote aussi `completedAt` : le backend ne le renseigne pas
  // automatiquement (vérifié par curl), donc on le gère ici côté front.
  // On ne touche `completedAt` que si l'utilisateur change explicitement
  // le statut, pas à chaque rendu.
  function handleStatusChange(status: FollowUpWritePayload["status"]) {
    onChange("status", status);
    if (status === "done") {
      onChange("completedAt", new Date().toISOString());
    } else {
      onChange("completedAt", null);
    }
  }

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div className="sm:col-span-2">
        <label className="block text-sm font-medium">Titre *</label>
        <input
          type="text"
          value={values.title ?? ""}
          onChange={(e) => onChange("title", e.target.value)}
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("title")} />
      </div>

      <div>
        <label className="block text-sm font-medium">Date prévue *</label>
        <input
          type="datetime-local"
          value={toDateTimeInputValue(values.scheduledAt)}
          onChange={(e) => onChange("scheduledAt", fromDateTimeInputValue(e.target.value) ?? "")}
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("scheduledAt")} />
      </div>

      <div>
        <label className="block text-sm font-medium">Statut</label>
        <select
          value={values.status ?? "todo"}
          onChange={(e) =>
            handleStatusChange(e.target.value as FollowUpWritePayload["status"])
          }
          className="mt-1 w-full rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
        >
          {FOLLOW_UP_STATUSES.map((status) => (
            <option key={status} value={status}>
              {followUpStatusLabels[status]}
            </option>
          ))}
        </select>
        <FieldError message={validationError?.messageFor("status")} />
      </div>

      <div>
        <label className="block text-sm font-medium">Entreprise</label>
        <select
          value={values.company ?? ""}
          onChange={(e) =>
            onChange("company", (e.target.value || null) as FollowUpWritePayload["company"])
          }
          className="mt-1 w-full rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
        >
          <option value="">—</option>
          {companies.map((company) => (
            <option key={company.id} value={companyIri(company.id)}>
              {company.name}
            </option>
          ))}
        </select>
        <FieldError message={validationError?.messageFor("company")} />
      </div>

      <div>
        <label className="block text-sm font-medium">Contact</label>
        <select
          value={values.contact ?? ""}
          onChange={(e) =>
            onChange("contact", (e.target.value || null) as FollowUpWritePayload["contact"])
          }
          className="mt-1 w-full rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
        >
          <option value="">—</option>
          {contacts.map((contact) => (
            <option key={contact.id} value={contactIri(contact.id)}>
              {[contact.firstName, contact.lastName].filter(Boolean).join(" ")}
            </option>
          ))}
        </select>
        <FieldError message={validationError?.messageFor("contact")} />
      </div>

      <div>
        <label className="block text-sm font-medium">Opportunité</label>
        <select
          value={values.opportunity ?? ""}
          onChange={(e) =>
            onChange(
              "opportunity",
              (e.target.value || null) as FollowUpWritePayload["opportunity"],
            )
          }
          className="mt-1 w-full rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
        >
          <option value="">—</option>
          {opportunities.map((opportunity) => (
            <option key={opportunity.id} value={opportunityIri(opportunity.id)}>
              {opportunity.title}
            </option>
          ))}
        </select>
        <FieldError message={validationError?.messageFor("opportunity")} />
      </div>

      <div className="sm:col-span-2">
        <label className="block text-sm font-medium">Description</label>
        <textarea
          value={values.description ?? ""}
          onChange={(e) => onChange("description", e.target.value)}
          rows={3}
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("description")} />
      </div>
    </div>
  );
}
