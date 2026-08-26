"use client";

import { FieldError } from "@/components/ui/FieldError";
import type { ApiValidationError } from "@/lib/api/errors";
import { companyIri } from "@/lib/api/iri";
import type { Company, ContactWritePayload } from "@/lib/api/types";

export function ContactFormFields({
  values,
  onChange,
  validationError,
  companies,
}: {
  values: ContactWritePayload;
  onChange: <K extends keyof ContactWritePayload>(field: K, value: ContactWritePayload[K]) => void;
  validationError: ApiValidationError | null;
  companies: Company[];
}) {
  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div>
        <label className="block text-sm font-medium">Prénom</label>
        <input
          type="text"
          value={values.firstName ?? ""}
          onChange={(e) => onChange("firstName", e.target.value)}
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("firstName")} />
      </div>

      <div>
        <label className="block text-sm font-medium">Nom *</label>
        <input
          type="text"
          value={values.lastName ?? ""}
          onChange={(e) => onChange("lastName", e.target.value)}
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("lastName")} />
      </div>

      <div>
        <label className="block text-sm font-medium">Email</label>
        <input
          type="email"
          value={values.email ?? ""}
          onChange={(e) => onChange("email", e.target.value)}
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("email")} />
      </div>

      <div>
        <label className="block text-sm font-medium">Téléphone</label>
        <input
          type="text"
          value={values.phone ?? ""}
          onChange={(e) => onChange("phone", e.target.value)}
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("phone")} />
      </div>

      <div>
        <label className="block text-sm font-medium">LinkedIn</label>
        <input
          type="text"
          value={values.linkedin ?? ""}
          onChange={(e) => onChange("linkedin", e.target.value)}
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("linkedin")} />
      </div>

      <div>
        <label className="block text-sm font-medium">Poste</label>
        <input
          type="text"
          value={values.jobTitle ?? ""}
          onChange={(e) => onChange("jobTitle", e.target.value)}
          className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
        />
        <FieldError message={validationError?.messageFor("jobTitle")} />
      </div>

      <div className="sm:col-span-2">
        <label className="block text-sm font-medium">Entreprise</label>
        <select
          value={values.company ?? ""}
          onChange={(e) => onChange("company", (e.target.value || null) as ContactWritePayload["company"])}
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
