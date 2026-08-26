"use client";

import { FieldError } from "@/components/ui/FieldError";
import type { ApiValidationError } from "@/lib/api/errors";
import { OPPORTUNITY_SOURCES, type CompanyWritePayload } from "@/lib/api/types";
import { companySourceLabels } from "@/lib/labels";

export function CompanyFormFields({
  values,
  onChange,
  validationError,
}: {
  values: CompanyWritePayload;
  onChange: <K extends keyof CompanyWritePayload>(field: K, value: CompanyWritePayload[K]) => void;
  validationError: ApiValidationError | null;
}) {
  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div className="sm:col-span-2">
        <label className="block text-sm font-medium">Nom *</label>
        <input
          type="text"
          value={values.name ?? ""}
          onChange={(e) => onChange("name", e.target.value)}
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("name")} />
      </div>

      <div>
        <label className="block text-sm font-medium">Site web</label>
        <input
          type="text"
          value={values.website ?? ""}
          onChange={(e) => onChange("website", e.target.value)}
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("website")} />
      </div>

      <div>
        <label className="block text-sm font-medium">Secteur</label>
        <input
          type="text"
          value={values.sector ?? ""}
          onChange={(e) => onChange("sector", e.target.value)}
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("sector")} />
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
        <label className="block text-sm font-medium">Source</label>
        <select
          value={values.source ?? ""}
          onChange={(e) =>
            onChange(
              "source",
              (e.target.value || null) as CompanyWritePayload["source"],
            )
          }
          className="mt-1 w-full rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
        >
          <option value="">—</option>
          {OPPORTUNITY_SOURCES.map((source) => (
            <option key={source} value={source}>
              {companySourceLabels[source]}
            </option>
          ))}
        </select>
        <FieldError message={validationError?.messageFor("source")} />
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
