import { LoginForm } from "../../components/login-form";

export default function LoginPage() {
  return (
    <main className="mx-auto flex min-h-screen max-w-lg items-center px-6 py-16">
      <section className="w-full rounded-[2rem] border border-white/70 bg-white/90 p-8 shadow-[0_20px_80px_rgba(15,23,42,0.12)]">
        <p className="text-xs font-semibold uppercase tracking-[0.35em] text-orange-600">SmartNews Admin</p>
        <h1 className="mt-4 text-4xl font-semibold tracking-tight text-zinc-950">Session-based control plane</h1>
        <p className="mt-3 text-sm leading-6 text-zinc-600">
          Этот экран логинится напрямую в Laravel API через Sanctum cookie flow, без legacy Filament-формы и без отдельного BFF.
        </p>
        <div className="mt-8">
          <LoginForm />
        </div>
      </section>
    </main>
  );
}
