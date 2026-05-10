import type { Metadata } from "next";
import { Manrope, Space_Grotesk } from "next/font/google";
import "./globals.css";

const heading = Space_Grotesk({
  subsets: ["latin"],
  variable: "--font-heading",
});

const body = Manrope({
  subsets: ["latin"],
  variable: "--font-body",
});

export const metadata: Metadata = {
  title: "SmartNews Web",
  description: "Отдельный публичный фронт SmartNews на Next.js",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ru" className={`${heading.variable} ${body.variable}`}>
      <body className="font-[family-name:var(--font-body)] text-zinc-950 antialiased">{children}</body>
    </html>
  );
}
