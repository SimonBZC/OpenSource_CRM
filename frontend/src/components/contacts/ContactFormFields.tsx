"use client";

import { FieldError } from "@/components/ui/FieldError";
import type { ApiValidationError } from "@/lib/api/errors";
import { companyIri } from "@/lib/api/iri";
import type { Company, ContactWritePayload } from "@/lib/api/types";
import type { InlineCompanyController } from "@/components/contacts/useInlineCompany";

export function ContactFormFields({
  values,
  onChange,
  validationError,
  companies,
  inlineCompany,
  onError,
}: {
  values: ContactWritePayload;
  onChange: <K extends keyof ContactWritePayload>(field: K, value: ContactWritePayload[K]) => void;
  validationError: ApiValidationError | null;
  companies: Company[];
  /** Bloc « Ou créer une entreprise », affiché quand aucune entreprise n'est choisie. */
  inlineCompany: InlineCompanyController;
  /** Remonte une erreur non liée à la validation (réseau, 5xx) au bandeau de la page. */
  onError: (error: unknown) => void;
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

      {!values.company && (
        <div className="sm:col-span-2 rounded-md border border-dashed border-black/15 p-4">
          <label className="block text-sm font-medium">Ou créer une entreprise</label>
          <div className="mt-1 flex gap-2">
            <input
              type="text"
              value={inlineCompany.name}
              onChange={(e) => inlineCompany.setName(e.target.value)}
              placeholder="Nom de l'entreprise"
              className="w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
            />
            <button
              type="button"
              onClick={() => inlineCompany.check().catch(onError)}
              disabled={inlineCompany.name.trim() === "" || inlineCompany.status === "checking"}
              className="rounded-md border border-black/15 px-4 py-2 text-sm font-medium hover:bg-black/[0.03] disabled:opacity-50"
            >
              {inlineCompany.status === "checking" ? "Recherche..." : "Rechercher"}
            </button>
          </div>
          <FieldError message={inlineCompany.error ?? undefined} />
          {inlineCompany.status === "available" && (
            <p className="mt-1 text-xs text-green-700">Nom disponible</p>
          )}

          {inlineCompany.name.trim() !== "" && inlineCompany.status !== "duplicate" && (
            <div className="mt-3">
              <label className="block text-sm font-medium">Secteur d&apos;activité</label>
              <input
                type="text"
                value={inlineCompany.sector}
                onChange={(e) => inlineCompany.setSector(e.target.value)}
                className="mt-1 w-full rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
              />
            </div>
          )}
        </div>
      )}

      <div className="sm:col-span-2">
        <div className="flex items-center gap-2">
          <input
            id="presentationSent"
            type="checkbox"
            checked={values.presentationSent ?? false}
            onChange={(e) => onChange("presentationSent", e.target.checked)}
            className="h-4 w-4"
          />
          <label htmlFor="presentationSent" className="text-sm font-medium">
            Je me suis présenté
          </label>
        </div>
        <FieldError message={validationError?.messageFor("presentationSent")} />
      </div>

      <div className="sm:col-span-2">
        <div className="flex items-center gap-2">
          <input
            id="active"
            type="checkbox"
            checked={values.active ?? true}
            onChange={(e) => onChange("active", e.target.checked)}
            className="h-4 w-4"
          />
          <label htmlFor="active" className="text-sm font-medium">
            Contact actif
          </label>
        </div>
        <p className="mt-1 text-xs text-black/50">
          Un contact inactif est masqué de la liste par défaut, n&apos;est plus proposé dans les
          listes de choix et est ignoré par les règles d&apos;alerte.
        </p>
        <FieldError message={validationError?.messageFor("active")} />
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
