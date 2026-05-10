import type { PropsWithChildren, ReactNode } from "react";

export function AppShell({
  eyebrow,
  title,
  description,
  children,
}: PropsWithChildren<{
  eyebrow: string;
  title: string;
  description: string;
}>) {
  return (
    <main className="mx-auto min-h-screen max-w-7xl px-6 py-10 text-zinc-950 sm:px-10">
      <section className="mb-10 rounded-[2rem] border border-white/60 bg-white/80 p-8 shadow-[0_20px_80px_rgba(15,23,42,0.12)] backdrop-blur">
        <p className="text-xs font-semibold uppercase tracking-[0.35em] text-orange-600">{eyebrow}</p>
        <h1 className="mt-3 max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
          {title}
        </h1>
        <p className="mt-4 max-w-2xl text-sm leading-6 text-zinc-600 sm:text-base">{description}</p>
      </section>
      <section className="space-y-6">{children}</section>
    </main>
  );
}

export function SectionCard({
  title,
  description,
  actions,
  children,
}: PropsWithChildren<{
  title: string;
  description?: string;
  actions?: ReactNode;
}>) {
  return (
    <section className="rounded-[1.75rem] border border-zinc-200 bg-white/90 p-6 shadow-[0_12px_40px_rgba(15,23,42,0.08)]">
      <div className="mb-5 flex flex-col gap-3 border-b border-zinc-100 pb-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h2 className="text-xl font-semibold tracking-tight text-zinc-900">{title}</h2>
          {description ? <p className="mt-1 text-sm text-zinc-500">{description}</p> : null}
        </div>
        {actions}
      </div>
      {children}
    </section>
  );
}

export function Pill({ children, tone = "neutral" }: PropsWithChildren<{ tone?: "neutral" | "accent" | "success" | "danger" }>) {
  const toneClass = {
    neutral: "bg-zinc-100 text-zinc-700",
    accent: "bg-orange-100 text-orange-700",
    success: "bg-emerald-100 text-emerald-700",
    danger: "bg-rose-100 text-rose-700",
  }[tone];

  return (
    <span className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ${toneClass}`}>
      {children}
    </span>
  );
}

export function StatRow({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-4 border-b border-zinc-100 py-2 text-sm last:border-b-0">
      <span className="text-zinc-500">{label}</span>
      <span className="text-right font-medium text-zinc-900">{value}</span>
    </div>
  );
}
