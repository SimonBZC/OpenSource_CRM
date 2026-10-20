"use client";

import { FieldError } from "@/components/ui/FieldError";
import type { ApiValidationError } from "@/lib/api/errors";
import { contactIri, opportunityIri } from "@/lib/api/iri";
import {
  ALERT_PRIORITIES,
  ALERT_STATUSES,
  type Contact,
  type Opportunity,
  type AlertWritePayload,
} from "@/lib/api/types";
import { fromDateTimeInputValue, toDateTimeInputValue } from "@/lib/format";
import { alertPriorityLabels, alertStatusLabels } from "@/lib/labels";

export function AlertFormFields({
  values,
  onChange,
  validationError,
  contacts,
  opportunities,
}: {
  values: AlertWritePayload;
  onChange: <K extends keyof AlertWritePayload>(field: K, value: AlertWritePayload[K]) => void;
  validationError: ApiValidationError | null;
  contacts: Contact[];
  opportunities: Opportunity[];
}) {
  // Même convention que pour les relances : le backend ne renseigne pas
  // `completedAt` automatiquement, on le gère ici en fonction du statut.
  function handleStatusChange(status: AlertWritePayload["status"]) {
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
        <label className="block text-sm font-medium">Échéance</label>
        <input
          type="datetime-local"
          value={toDateTimeInputValue(values.dueDate)}
          onChange={(e) => onChange("dueDate", fromDateTimeInputValue(e.target.value))}
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("dueDate")} />
      </div>

      <div>
        <label className="block text-sm font-medium">Statut</label>
        <select
          value={values.status ?? "todo"}
          onChange={(e) => handleStatusChange(e.target.value as AlertWritePayload["status"])}
          className="mt-1 w-full rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
        >
          {ALERT_STATUSES.map((status) => (
            <option key={status} value={status}>
              {alertStatusLabels[status]}
            </option>
          ))}
        </select>
        <FieldError message={validationError?.messageFor("status")} />
      </div>

      <div>
        <label className="block text-sm font-medium">Priorité</label>
        <select
          value={values.priority ?? ""}
          onChange={(e) =>
            onChange("priority", (e.target.value || null) as AlertWritePayload["priority"])
          }
          className="mt-1 w-full rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
        >
          <option value="">—</option>
          {ALERT_PRIORITIES.map((priority) => (
            <option key={priority} value={priority}>
              {alertPriorityLabels[priority]}
            </option>
          ))}
        </select>
        <FieldError message={validationError?.messageFor("priority")} />
      </div>

      <div>
        <label className="block text-sm font-medium">Opportunité</label>
        <select
          value={values.opportunity ?? ""}
          onChange={(e) =>
            onChange("opportunity", (e.target.value || null) as AlertWritePayload["opportunity"])
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

      <div>
        <label className="block text-sm font-medium">Contact</label>
        <select
          value={values.contact ?? ""}
          onChange={(e) =>
            onChange("contact", (e.target.value || null) as AlertWritePayload["contact"])
          }
          className="mt-1 w-full rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
        >
          <option value="">—</option>
          {contacts.map((contact) => (
            <option key={contact.id} value={contactIri(contact.id)}>
              {[contact.firstName, contact.lastName].filter(Boolean).join(" ")}
              {!contact.active && " (inactif)"}
            </option>
          ))}
        </select>
        <FieldError message={validationError?.messageFor("contact")} />
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
