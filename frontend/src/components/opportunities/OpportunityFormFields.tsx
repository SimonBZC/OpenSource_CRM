"use client";

import { FieldError } from "@/components/ui/FieldError";
import type { ApiValidationError } from "@/lib/api/errors";
import { companyIri, contactIri } from "@/lib/api/iri";
import {
  OPPORTUNITY_SOURCES,
  OPPORTUNITY_STATUSES,
  type Company,
  type Contact,
  type OpportunityWritePayload,
} from "@/lib/api/types";
import { opportunitySourceLabels, opportunityStatusLabels } from "@/lib/labels";

export function OpportunityFormFields({
  values,
  onChange,
  validationError,
  companies,
  contacts,
}: {
  values: OpportunityWritePayload;
  onChange: <K extends keyof OpportunityWritePayload>(
    field: K,
    value: OpportunityWritePayload[K],
  ) => void;
  validationError: ApiValidationError | null;
  companies: Company[];
  contacts: Contact[];
}) {
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
        <label className="block text-sm font-medium">Statut</label>
        <select
          value={values.status ?? ""}
          onChange={(e) =>
            onChange("status", e.target.value as OpportunityWritePayload["status"])
          }
          className="mt-1 w-full rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
        >
          {OPPORTUNITY_STATUSES.map((status) => (
            <option key={status} value={status}>
              {opportunityStatusLabels[status]}
            </option>
          ))}
        </select>
        <FieldError message={validationError?.messageFor("status")} />
      </div>

      <div>
        <label className="block text-sm font-medium">Source</label>
        <select
          value={values.source ?? ""}
          onChange={(e) =>
            onChange(
              "source",
              (e.target.value || null) as OpportunityWritePayload["source"],
            )
          }
          className="mt-1 w-full rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
        >
          <option value="">—</option>
          {OPPORTUNITY_SOURCES.map((source) => (
            <option key={source} value={source}>
              {opportunitySourceLabels[source]}
            </option>
          ))}
        </select>
        <FieldError message={validationError?.messageFor("source")} />
      </div>

      <div>
        <label className="block text-sm font-medium">Entreprise</label>
        <select
          value={values.company ?? ""}
          onChange={(e) =>
            onChange("company", (e.target.value || null) as OpportunityWritePayload["company"])
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
            onChange("contact", (e.target.value || null) as OpportunityWritePayload["contact"])
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
        <label className="block text-sm font-medium">URL</label>
        <input
          type="text"
          value={values.url ?? ""}
          onChange={(e) => onChange("url", e.target.value)}
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("url")} />
      </div>

      <div>
        <label className="block text-sm font-medium">TJM min (€)</label>
        <input
          type="number"
          value={values.dailyRateMin ?? ""}
          onChange={(e) =>
            onChange("dailyRateMin", e.target.value === "" ? null : Number(e.target.value))
          }
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("dailyRateMin")} />
      </div>

      <div>
        <label className="block text-sm font-medium">TJM max (€)</label>
        <input
          type="number"
          value={values.dailyRateMax ?? ""}
          onChange={(e) =>
            onChange("dailyRateMax", e.target.value === "" ? null : Number(e.target.value))
          }
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("dailyRateMax")} />
      </div>

      <div className="flex items-center gap-2 pt-6">
        <input
          id="remote"
          type="checkbox"
          checked={values.remote ?? false}
          onChange={(e) => onChange("remote", e.target.checked)}
          className="h-4 w-4"
        />
        <label htmlFor="remote" className="text-sm font-medium">
          Télétravail possible
        </label>
      </div>

      <div>
        <label className="block text-sm font-medium">Localisation</label>
        <input
          type="text"
          value={values.location ?? ""}
          onChange={(e) => onChange("location", e.target.value)}
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("location")} />
      </div>

      <div>
        <label className="block text-sm font-medium">Durée</label>
        <input
          type="text"
          value={values.duration ?? ""}
          onChange={(e) => onChange("duration", e.target.value)}
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("duration")} />
      </div>

      <div>
        <label className="block text-sm font-medium">Date de démarrage</label>
        <input
          type="date"
          value={values.startDate ? values.startDate.slice(0, 10) : ""}
          onChange={(e) =>
            onChange("startDate", e.target.value ? `${e.target.value}T00:00:00+00:00` : null)
          }
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("startDate")} />
      </div>

      <div className="sm:col-span-2">
        <label className="block text-sm font-medium">Technologies (séparées par des virgules)</label>
        <input
          type="text"
          value={(values.technologies ?? []).join(", ")}
          onChange={(e) =>
            onChange(
              "technologies",
              e.target.value
                .split(",")
                .map((t) => t.trim())
                .filter(Boolean),
            )
          }
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("technologies")} />
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

      <div className="sm:col-span-2">
        <label className="block text-sm font-medium">Notes</label>
        <textarea
          value={values.notes ?? ""}
          onChange={(e) => onChange("notes", e.target.value)}
          rows={3}
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("notes")} />
      </div>
    </div>
  );
}
