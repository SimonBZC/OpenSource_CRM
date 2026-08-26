import type { OpportunityAnalyzeResponse } from "@/lib/api/types";

/** Libellés FR des champs d'opportunité, pour afficher les champs mis à jour par l'IA. */
const FIELD_LABELS: Record<string, string> = {
  technologies: "Technologies",
  dailyRateMin: "TJM min",
  dailyRateMax: "TJM max",
  location: "Lieu",
  duration: "Durée",
  startDate: "Date de début",
  score: "Score",
  scoreReason: "Raison du score",
};

function fieldLabel(field: string): string {
  return FIELD_LABELS[field] ?? field;
}

/**
 * Panneau affichant le résultat d'une analyse IA d'opportunité.
 * Règle backend : score et raison toujours mis à jour ; les autres champs
 * (technologies, TJM, lieu, durée, date de début) ne sont complétés que
 * s'ils étaient vides ; `remote` n'est jamais modifié automatiquement.
 */
export function OpportunityAnalysisPanel({
  result,
}: {
  result: OpportunityAnalyzeResponse;
}) {
  const { analysis, appliedFields } = result;
  const hasMissingInfo = analysis.missingInformation.length > 0;

  // Champs "appliqués" hors score/scoreReason (déjà affichés ailleurs), pour
  // ne pas dupliquer l'info à l'utilisateur.
  const otherAppliedFields = appliedFields.filter(
    (field) => field !== "score" && field !== "scoreReason",
  );

  return (
    <div className="rounded-lg border border-blue-200 bg-blue-50 p-4">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold text-blue-900">Résultat de l&apos;analyse IA</h2>
        <span
          className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
            analysis.relevant ? "bg-green-100 text-green-700" : "bg-gray-200 text-gray-600"
          }`}
        >
          {analysis.relevant ? "Pertinente" : "Non pertinente"}
        </span>
      </div>

      <p className="mt-2 text-2xl font-bold text-blue-900">{analysis.score}</p>
      <p className="mt-1 text-sm text-black/70">{analysis.reason}</p>

      {hasMissingInfo && (
        <div className="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
          <p className="font-medium">Informations manquantes détectées par l&apos;IA :</p>
          <ul className="mt-1 list-inside list-disc">
            {analysis.missingInformation.map((info, index) => (
              <li key={index}>{info}</li>
            ))}
          </ul>
        </div>
      )}

      {otherAppliedFields.length > 0 && (
        <p className="mt-3 text-xs text-black/60">
          Champs complétés par l&apos;IA (vides auparavant) :{" "}
          {otherAppliedFields.map(fieldLabel).join(", ")}.
        </p>
      )}

      <p className="mt-2 text-xs text-black/40">
        L&apos;IA ne modifie jamais un champ déjà renseigné manuellement (sauf le score et sa
        raison, toujours recalculés), ni le champ « à distance ».
      </p>
    </div>
  );
}
