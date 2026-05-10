"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";

function readCookie(name: string) {
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);

  if (parts.length < 2) {
    return null;
  }

  return decodeURIComponent(parts.pop()!.split(";").shift() ?? "");
}

export function LoginForm() {
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);
  const [isPending, startTransition] = useTransition();

  async function onSubmit(formData: FormData) {
    setError(null);

    const baseUrl = process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://api.localhost:8080/api/v1";
    const email = String(formData.get("email") ?? "");
    const password = String(formData.get("password") ?? "");

    try {
      await fetch("http://api.localhost:8080/sanctum/csrf-cookie", {
        credentials: "include",
      });

      const token = readCookie("XSRF-TOKEN");
      const response = await fetch(`${baseUrl}/auth/login`, {
        method: "POST",
        credentials: "include",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          ...(token ? { "X-XSRF-TOKEN": token } : {}),
        },
        body: JSON.stringify({ email, password }),
      });

      if (!response.ok) {
        const payload = (await response.json()) as { error?: { message?: string } };

        throw new Error(payload.error?.message ?? "Login failed");
      }

      startTransition(() => {
        router.push("/news");
        router.refresh();
      });
    } catch (caughtError) {
      setError(caughtError instanceof Error ? caughtError.message : "Login failed");
    }
  }

  return (
    <form
      action={(formData) => {
        startTransition(async () => {
          await onSubmit(formData);
        });
      }}
      className="space-y-4"
    >
      <div className="space-y-2">
        <label className="text-sm font-medium text-zinc-700" htmlFor="email">
          Email
        </label>
        <input
          id="email"
          name="email"
          type="email"
          defaultValue="admin@example.com"
          className="w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm outline-none transition focus:border-orange-400 focus:bg-white"
        />
      </div>
      <div className="space-y-2">
        <label className="text-sm font-medium text-zinc-700" htmlFor="password">
          Password
        </label>
        <input
          id="password"
          name="password"
          type="password"
          defaultValue="password"
          className="w-full rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm outline-none transition focus:border-orange-400 focus:bg-white"
        />
      </div>
      {error ? <p className="rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</p> : null}
      <button
        type="submit"
        disabled={isPending}
        className="w-full rounded-full bg-zinc-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800 disabled:opacity-60"
      >
        {isPending ? "Signing in..." : "Sign in"}
      </button>
    </form>
  );
}
