import type { Metadata } from "next";
import { Sidebar } from "@/components/layout/Sidebar";
import "./globals.css";

export const metadata: Metadata = {
  title: "FreeCRM",
  description: "Outil local de prospection freelance",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="fr" className="h-full antialiased">
      <body className="flex min-h-full">
        <Sidebar />
        <main className="min-h-full flex-1 overflow-x-hidden">{children}</main>
      </body>
    </html>
  );
}
