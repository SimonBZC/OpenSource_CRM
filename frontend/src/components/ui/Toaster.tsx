"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from "react";

/** Nombre maximal de notifications affichées en même temps. */
const MAX_NOTIFICATIONS = 5;

/** Durée d'affichage d'une notification de succès, en millisecondes. */
const SUCCESS_DURATION_MS = 4000;

type NotificationKind = "success" | "error";

interface Notification {
  id: number;
  kind: NotificationKind;
  message: string;
}

interface Notifier {
  /** Notification verte, fermée automatiquement après 4 s. */
  success: (message: string) => void;
  /** Notification rouge, visible jusqu'à sa fermeture par la croix. */
  error: (message: string) => void;
}

const NotifyContext = createContext<Notifier | null>(null);

/**
 * Notifications flottantes de l'application (succès et erreurs d'action),
 * empilées en bas à droite. Monté une seule fois dans le layout racine : il
 * survit à la navigation côté client, si bien qu'une notification émise juste
 * avant un `router.push` s'affiche sur la page d'arrivée.
 */
export function Toaster({ children }: { children: React.ReactNode }) {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const nextId = useRef(0);

  const dismiss = useCallback((id: number) => {
    setNotifications((prev) => prev.filter((n) => n.id !== id));
  }, []);

  const push = useCallback((kind: NotificationKind, message: string) => {
    const id = ++nextId.current;
    // Au plus MAX_NOTIFICATIONS à l'écran : les plus anciennes cèdent la place.
    // Une notification identique déjà affichée est remplacée plutôt qu'empilée.
    setNotifications((prev) =>
      [...prev.filter((n) => n.kind !== kind || n.message !== message), { id, kind, message }].slice(
        -MAX_NOTIFICATIONS,
      ),
    );
  }, []);

  const notifier = useMemo<Notifier>(
    () => ({
      success: (message) => push("success", message),
      error: (message) => push("error", message),
    }),
    [push],
  );

  return (
    <NotifyContext.Provider value={notifier}>
      {children}
      <div className="pointer-events-none fixed right-4 bottom-4 left-4 z-[60] flex max-h-[calc(100vh-2rem)] flex-col gap-2 overflow-y-auto sm:left-auto sm:w-full sm:max-w-sm">
        {notifications.map((notification) => (
          <NotificationItem key={notification.id} notification={notification} onDismiss={dismiss} />
        ))}
      </div>
    </NotifyContext.Provider>
  );
}

function NotificationItem({
  notification,
  onDismiss,
}: {
  notification: Notification;
  onDismiss: (id: number) => void;
}) {
  const { id, kind, message } = notification;

  useEffect(() => {
    if (kind !== "success") return;
    const timeout = setTimeout(() => onDismiss(id), SUCCESS_DURATION_MS);
    return () => clearTimeout(timeout);
  }, [id, kind, onDismiss]);

  const isError = kind === "error";
  return (
    <div
      role={isError ? "alert" : "status"}
      className={`pointer-events-auto flex items-start gap-3 rounded-md border px-4 py-3 text-sm shadow-lg ${
        isError ? "border-red-200 bg-red-50 text-red-700" : "border-green-200 bg-green-50 text-green-800"
      }`}
    >
      <p className="flex-1 break-words">{message}</p>
      <button
        type="button"
        onClick={() => onDismiss(id)}
        aria-label="Fermer la notification"
        className="-mr-1 shrink-0 rounded p-0.5 opacity-70 hover:opacity-100 focus-visible:outline-2 focus-visible:outline-blue-500"
      >
        <svg
          viewBox="0 0 24 24"
          className="h-4 w-4"
          fill="none"
          stroke="currentColor"
          strokeWidth={2}
          strokeLinecap="round"
          aria-hidden="true"
        >
          <path d="M6 6l12 12M18 6L6 18" />
        </svg>
      </button>
    </div>
  );
}

/** Émet une notification de succès ou d'erreur (voir `Toaster`). */
export function useNotify(): Notifier {
  const notifier = useContext(NotifyContext);
  if (!notifier) {
    throw new Error("useNotify doit être utilisé à l'intérieur de <Toaster>.");
  }
  return notifier;
}
