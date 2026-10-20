"use client";

import { useEffect, useState } from "react";
import { ErrorBanner } from "@/components/ui/ErrorBanner";
import { FieldError } from "@/components/ui/FieldError";
import { useNotify } from "@/components/ui/Toaster";
import { ApiValidationError, toDisplayMessage } from "@/lib/api/errors";
import { alertRulesApi } from "@/lib/api/resources";
import { ALERT_PRIORITIES, type AlertPriority, type AlertRule } from "@/lib/api/types";
import { alertPriorityLabels, alertRuleTypeDescriptions, alertRuleTypeLabels } from "@/lib/labels";

/** Valeurs en cours d'édition d'une ligne : le délai reste une chaîne tant qu'il n'est pas validé. */
interface RuleDraft {
  enabled: boolean;
  delayDays: string;
  priority: AlertPriority;
}

/** État d'enregistrement propre à chaque ligne. */
interface RowState {
  saving: boolean;
  validationError: ApiValidationError | null;
  /** Erreur de saisie détectée avant l'envoi (délai vide ou non entier). */
  delayError: string | null;
}

const EMPTY_ROW_STATE: RowState = {
  saving: false,
  validationError: null,
  delayError: null,
};

function toDraft(rule: AlertRule): RuleDraft {
  return { enabled: rule.enabled, delayDays: String(rule.delayDays), priority: rule.priority };
}

export default function AlertRulesPage() {
  const [rules, setRules] = useState<AlertRule[]>([]);
  const [drafts, setDrafts] = useState<Record<number, RuleDraft>>({});
  const [rowStates, setRowStates] = useState<Record<number, RowState>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const notify = useNotify();

  function reload() {
    setLoading(true);
    setError(null);
    alertRulesApi
      .list({ itemsPerPage: 100 })
      .then((res) => {
        setRules(res.items);
        setDrafts(Object.fromEntries(res.items.map((rule) => [rule.id, toDraft(rule)])));
        setRowStates({});
      })
      .catch((err) => setError(toDisplayMessage(err)))
      .finally(() => setLoading(false));
  }

  useEffect(() => {
    const timeout = setTimeout(reload, 0);
    return () => clearTimeout(timeout);
  }, []);

  function updateDraft<K extends keyof RuleDraft>(id: number, field: K, value: RuleDraft[K]) {
    setDrafts((prev) => ({ ...prev, [id]: { ...prev[id], [field]: value } }));
  }

  function setRowState(id: number, patch: Partial<RowState>) {
    setRowStates((prev) => ({ ...prev, [id]: { ...(prev[id] ?? EMPTY_ROW_STATE), ...patch } }));
  }

  async function save(rule: AlertRule) {
    const draft = drafts[rule.id];
    if (!draft) return;

    const raw = draft.delayDays.trim();
    // Saisie vérifiée côté navigateur : l'API refuse un délai négatif (422)
    // mais un délai vide ou décimal ne serait pas un entier valide.
    if (raw === "" || !/^-?\d+$/.test(raw)) {
      setRowState(rule.id, {
        validationError: null,
        delayError: "Le délai doit être un nombre entier de jours.",
      });
      return;
    }

    setRowState(rule.id, { saving: true, validationError: null, delayError: null });
    try {
      const updated = await alertRulesApi.update(rule.id, {
        enabled: draft.enabled,
        delayDays: Number(raw),
        priority: draft.priority,
      });
      setRules((prev) => prev.map((r) => (r.id === updated.id ? updated : r)));
      setDrafts((prev) => ({ ...prev, [updated.id]: toDraft(updated) }));
      setRowState(rule.id, { saving: false });
      notify.success("Règle enregistrée");
    } catch (err) {
      if (err instanceof ApiValidationError) {
        setRowState(rule.id, { saving: false, validationError: err });
      } else {
        setRowState(rule.id, { saving: false });
        notify.error(`${alertRuleTypeLabels[rule.type] ?? rule.label} : ${toDisplayMessage(err)}`);
      }
    }
  }

  return (
    <div className="p-8">
      <div>
        <p className="text-sm text-black/50">Paramètres</p>
        <h1 className="text-2xl font-bold tracking-tight">Règles</h1>
        <p className="mt-1 text-sm text-black/60">
          Les règles activées sont vérifiées avec le bouton « Vérifier les règles » de la page Alertes.
        </p>
      </div>

      <div className="mt-4 space-y-3">
        <ErrorBanner message={error} />
      </div>

      <div className="mt-4 overflow-hidden rounded-lg border border-black/10 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="bg-black/[0.03] text-xs uppercase tracking-wide text-black/50">
            <tr>
              <th className="px-4 py-3 font-medium">Règle</th>
              <th className="px-4 py-3 font-medium">Activée</th>
              <th className="px-4 py-3 font-medium">Délai (jours)</th>
              <th className="px-4 py-3 font-medium">Priorité de l&apos;alerte</th>
              <th className="px-4 py-3 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            {rules.map((rule) => {
              const draft = drafts[rule.id] ?? toDraft(rule);
              const state = rowStates[rule.id] ?? EMPTY_ROW_STATE;
              const delayMessage = state.delayError ?? state.validationError?.messageFor("delayDays");
              const otherViolation =
                state.validationError && !state.validationError.messageFor("delayDays")
                  ? state.validationError.message
                  : null;
              return (
                <tr key={rule.id} className="border-t border-black/5 align-top">
                  <td className="max-w-sm px-4 py-3">
                    <p className="font-medium">{alertRuleTypeLabels[rule.type] ?? rule.label}</p>
                    <p className="mt-0.5 text-xs text-black/50">{alertRuleTypeDescriptions[rule.type]}</p>
                    <FieldError message={otherViolation ?? undefined} />
                  </td>
                  <td className="px-4 py-3">
                    <input
                      id={`rule-enabled-${rule.id}`}
                      type="checkbox"
                      checked={draft.enabled}
                      onChange={(e) => updateDraft(rule.id, "enabled", e.target.checked)}
                      className="h-4 w-4"
                      aria-label={`Activer la règle ${alertRuleTypeLabels[rule.type] ?? rule.label}`}
                    />
                  </td>
                  <td className="px-4 py-3">
                    <input
                      type="number"
                      min={0}
                      step={1}
                      value={draft.delayDays}
                      onChange={(e) => updateDraft(rule.id, "delayDays", e.target.value)}
                      className="w-24 rounded-md border border-black/15 px-3 py-2 text-sm outline-none focus:border-blue-500"
                      aria-label="Délai en jours"
                    />
                    <FieldError message={delayMessage} />
                    <FieldError message={state.validationError?.messageFor("enabled")} />
                  </td>
                  <td className="px-4 py-3">
                    <select
                      value={draft.priority}
                      onChange={(e) => updateDraft(rule.id, "priority", e.target.value as AlertPriority)}
                      className="rounded-md border border-black/15 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500"
                      aria-label="Priorité de l'alerte"
                    >
                      {ALERT_PRIORITIES.map((p) => (
                        <option key={p} value={p}>
                          {alertPriorityLabels[p]}
                        </option>
                      ))}
                    </select>
                    <FieldError message={state.validationError?.messageFor("priority")} />
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-3">
                      <button
                        type="button"
                        onClick={() => save(rule)}
                        disabled={state.saving}
                        className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                      >
                        {state.saving ? "Enregistrement..." : "Enregistrer"}
                      </button>
                    </div>
                  </td>
                </tr>
              );
            })}
            {!loading && !error && rules.length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-black/50">
                  Aucune règle. La migration de création des règles a-t-elle été lancée ?
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
