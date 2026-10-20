import type { Metadata } from "next";
import { Sidebar } from "@/components/layout/Sidebar";
import { Toaster } from "@/components/ui/Toaster";
import "./globals.css";

export const metadata: Metadata = {
  title: "FreeCRM",
  description: "Outil local de prospection freelance",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="fr" className="h-full antialiased">
      <body className="flex min-h-full">
        <Toaster>
          <Sidebar />
          <main className="min-h-full flex-1 overflow-x-hidden">{children}</main>
        </Toaster>
      </body>
    </html>
  );
}
