import { adminNavigation } from "@smartnews/config";
import { Space_Grotesk, IBM_Plex_Sans } from "next/font/google";
import Link from "next/link";
import "./globals.css";

const heading = Space_Grotesk({
  subsets: ["latin"],
  variable: "--font-admin-heading",
});

const body = IBM_Plex_Sans({
  subsets: ["latin"],
  weight: ["400", "500", "600"],
  variable: "--font-admin-body",
});

export const metadata = {
  title: "SmartNews Admin",
  description: "Новая admin-поверхность SmartNews на Next.js",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ru" className={`${heading.variable} ${body.variable}`}>
      <body className="font-[family-name:var(--font-admin-body)] text-zinc-950 antialiased">
        <div className="mx-auto flex min-h-screen max-w-7xl gap-6 px-6 py-8 sm:px-10">
          <aside className="hidden w-72 shrink-0 rounded-[2rem] border border-white/70 bg-white/90 p-6 shadow-[0_20px_80px_rgba(15,23,42,0.1)] lg:block">
            <p className="text-xs font-semibold uppercase tracking-[0.35em] text-orange-600">SmartNews Admin</p>
            <h1 className="mt-4 text-3xl font-semibold tracking-tight">Separated control plane</h1>
            <nav className="mt-8 space-y-2 text-sm">
              {adminNavigation.map((item) => (
                <Link key={item.href} href={item.href} className="block rounded-2xl px-4 py-3 text-zinc-700 transition hover:bg-zinc-100">
                  {item.label}
                </Link>
              ))}
            </nav>
          </aside>
          <div className="min-w-0 flex-1">{children}</div>
        </div>
      </body>
    </html>
  );
}
