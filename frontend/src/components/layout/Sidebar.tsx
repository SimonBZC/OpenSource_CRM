"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

interface NavItem {
  href: string;
  label: string;
  /** Section pas encore développée (placeholder Phase 5). */
  comingSoon?: boolean;
}

const NAV_ITEMS: NavItem[] = [
  { href: "/dashboard", label: "Dashboard" },
  { href: "/opportunities", label: "Opportunités" },
  { href: "/contacts", label: "Contacts" },
  { href: "/follow-ups", label: "Relances" },
  { href: "/alerts", label: "Alertes" },
  { href: "/settings", label: "Paramètres", comingSoon: true },
];

export function Sidebar() {
  const pathname = usePathname();

  return (
    <aside className="flex w-56 shrink-0 flex-col border-r border-black/10 bg-white">
      <div className="border-b border-black/10 px-4 py-4">
        <Link href="/opportunities" className="text-lg font-bold tracking-tight">
          FreeCRM
        </Link>
      </div>
      <nav className="flex flex-1 flex-col gap-0.5 p-2">
        {NAV_ITEMS.map((item) => {
          const isActive =
            pathname === item.href || pathname.startsWith(`${item.href}/`);
          return (
            <Link
              key={item.href}
              href={item.href}
              className={`flex items-center justify-between rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                isActive
                  ? "bg-blue-600 text-white"
                  : "text-black/70 hover:bg-black/5"
              }`}
            >
              <span>{item.label}</span>
              {item.comingSoon && (
                <span
                  className={`text-[10px] font-normal ${
                    isActive ? "text-white/70" : "text-black/40"
                  }`}
                >
                  bientôt
                </span>
              )}
            </Link>
          );
        })}
      </nav>
    </aside>
  );
}
