import type { ReactNode } from "react";

type IconName = "check" | "message" | "trash";
type IconTone = "green" | "blue" | "red";

/** Tracés SVG inline (style « outline », 24×24), sans librairie d'icônes. */
const ICON_PATHS: Record<IconName, ReactNode> = {
  check: <path d="M5 13l4 4L19 7" />,
  message: (
    <path d="M8 10h8M8 14h5M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.4A8 8 0 1 1 21 12z" />
  ),
  trash: (
    <path d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12M9 7V4h6v3" />
  ),
};

const TONE_CLASSES: Record<IconTone, string> = {
  green: "border-green-300 text-green-700 hover:bg-green-50",
  blue: "border-blue-300 text-blue-700 hover:bg-blue-50",
  red: "border-red-300 text-red-700 hover:bg-red-50",
};

type IconButtonProps = {
  icon: IconName;
  /** Libellé de l'action : lu par les lecteurs d'écran et affiché en infobulle. */
  label: string;
  tone: IconTone;
  onClick: () => void;
};

/** Bouton d'action compact réduit à une icône (actions de ligne d'une liste). */
export function IconButton({ icon, label, tone, onClick }: IconButtonProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-label={label}
      title={label}
      className={`rounded-md border p-1.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500 ${TONE_CLASSES[tone]}`}
    >
      <svg
        viewBox="0 0 24 24"
        className="h-4 w-4"
        fill="none"
        stroke="currentColor"
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
        aria-hidden="true"
      >
        {ICON_PATHS[icon]}
      </svg>
    </button>
  );
}
