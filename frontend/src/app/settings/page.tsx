import { redirect } from "next/navigation";

/** Paramètres n'a pas de page propre : on ouvre directement le sous-menu Messages. */
export default function SettingsPage() {
  redirect("/settings/messages");
}
