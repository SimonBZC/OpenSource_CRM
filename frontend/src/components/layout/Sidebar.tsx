"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

interface NavItem {
  href: string;
  label: string;
  /** Section pas encore développée (placeholder Phase 5). */
  comingSoon?: boolean;
  /** Sous-entrées, toujours affichées et indentées sous l'entrée. */
  children?: NavItem[];
}

const NAV_ITEMS: NavItem[] = [
  { href: "/dashboard", label: "Dashboard" },
  { href: "/contacts", label: "Contacts" },
  { href: "/follow-ups", label: "Relances" },
  { href: "/opportunities", label: "Opportunités" },
  { href: "/alerts", label: "Alertes" },
  {
    href: "/settings",
    label: "Paramètres",
    children: [
      { href: "/settings/messages", label: "Messages" },
      { href: "/settings/rules", label: "Règles" },
    ],
  },
];

function matches(pathname: string, href: string): boolean {
  return pathname === href || pathname.startsWith(`${href}/`);
}

function NavLink({
  item,
  active,
  highlighted,
  indented = false,
}: {
  item: NavItem;
  /** Entrée de la page courante : fond bleu. */
  active: boolean;
  /** Parent d'une sous-entrée active : texte mis en avant, sans fond. */
  highlighted?: boolean;
  indented?: boolean;
}) {
  return (
    <Link
      href={item.href}
      className={`flex items-center justify-between rounded-md py-2 text-sm font-medium transition-colors ${
        indented ? "ml-4 pl-3 pr-3" : "px-3"
      } ${
        active
          ? "bg-blue-600 text-white"
          : highlighted
            ? "text-black hover:bg-black/5"
            : "text-black/70 hover:bg-black/5"
      }`}
    >
      <span>{item.label}</span>
      {item.comingSoon && (
        <span className={`text-[10px] font-normal ${active ? "text-white/70" : "text-black/40"}`}>
          bientôt
        </span>
      )}
    </Link>
  );
}

export function Sidebar() {
  const pathname = usePathname();

  return (
    <aside className="flex w-56 shrink-0 flex-col border-r border-black/10 bg-white">
      <div className="border-b border-black/10 px-4 py-4">
        <Link href="/dashboard" className="text-lg font-bold tracking-tight">
          FreeCRM
        </Link>
      </div>
      <nav className="flex flex-1 flex-col gap-0.5 p-2">
        {NAV_ITEMS.map((item) => {
          const childActive = (item.children ?? []).some((child) => matches(pathname, child.href));
          return (
            <div key={item.href} className="flex flex-col gap-0.5">
              <NavLink
                item={item}
                active={matches(pathname, item.href) && !childActive}
                highlighted={childActive}
              />
              {item.children?.map((child) => (
                <NavLink key={child.href} item={child} active={matches(pathname, child.href)} indented />
              ))}
            </div>
          );
        })}
      </nav>
    </aside>
  );
}
