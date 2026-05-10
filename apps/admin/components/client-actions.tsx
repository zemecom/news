"use client";

import { useRouter } from "next/navigation";
import { useState, useTransition } from "react";

function readCookie(name: string) {
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);

  if (parts.length < 2) {
    return null;
  }

  return decodeURIComponent(parts.pop()!.split(";").shift() ?? "");
}

async function apiWrite(path: string, method: string, body?: unknown) {
  const baseUrl = process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://api.localhost:8080/api/v1";
  await fetch("http://api.localhost:8080/sanctum/csrf-cookie", {
    credentials: "include",
  });

  const xsrf = readCookie("XSRF-TOKEN");
  const response = await fetch(`${baseUrl}${path}`, {
    method,
    credentials: "include",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(xsrf ? { "X-XSRF-TOKEN": xsrf } : {}),
    },
    body: body === undefined ? undefined : JSON.stringify(body),
  });

  if (!response.ok) {
    const payload = (await response.json()) as { error?: { message?: string } };

    throw new Error(payload.error?.message ?? "Request failed");
  }

  return response.json();
}

export function AdminActionButton({
  path,
  method = "POST",
  body,
  label,
}: {
  path: string;
  method?: string;
  body?: unknown;
  label: string;
}) {
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);
  const [isPending, startTransition] = useTransition();

  return (
    <div className="space-y-2">
      <button
        type="button"
        onClick={() =>
          startTransition(async () => {
            setError(null);

            try {
              await apiWrite(path, method, body);
              router.refresh();
            } catch (caughtError) {
              setError(caughtError instanceof Error ? caughtError.message : "Request failed");
            }
          })
        }
        className="rounded-full border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-orange-300 hover:text-zinc-950"
      >
        {isPending ? "Working..." : label}
      </button>
      {error ? <p className="text-xs text-rose-600">{error}</p> : null}
    </div>
  );
}

export function SettingsForm({
  enabled,
  intervalSeconds,
  options,
}: {
  enabled: boolean;
  intervalSeconds: number;
  options: Record<string, string>;
}) {
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);
  const [isPending, startTransition] = useTransition();

  return (
    <form
      action={(formData) =>
        startTransition(async () => {
          setError(null);

          try {
            await apiWrite("/admin/settings", "PUT", {
              news_auto_refresh_enabled: formData.get("news_auto_refresh_enabled") === "on",
              news_auto_refresh_interval_seconds: Number(formData.get("news_auto_refresh_interval_seconds") ?? intervalSeconds),
            });
            router.refresh();
          } catch (caughtError) {
            setError(caughtError instanceof Error ? caughtError.message : "Update failed");
          }
        })
      }
      className="grid gap-4 md:grid-cols-2"
    >
      <label className="flex items-center gap-3 rounded-2xl bg-zinc-50 px-4 py-3 text-sm text-zinc-700">
        <input type="checkbox" name="news_auto_refresh_enabled" defaultChecked={enabled} />
        Enable news auto-refresh
      </label>
      <select
        name="news_auto_refresh_interval_seconds"
        defaultValue={String(intervalSeconds)}
        className="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm outline-none transition focus:border-orange-400 focus:bg-white"
      >
        {Object.entries(options)
          .filter(([key]) => key.endsWith("s"))
          .map(([key, label]) => (
            <option key={key} value={Number.parseInt(key, 10)}>
              {label}
            </option>
          ))}
      </select>
      <div className="md:col-span-2">
        <button className="rounded-full bg-zinc-950 px-5 py-3 text-sm font-semibold text-white">
          {isPending ? "Saving..." : "Save settings"}
        </button>
      </div>
      {error ? <p className="md:col-span-2 text-sm text-rose-600">{error}</p> : null}
    </form>
  );
}

export function SourceEditor({
  source,
}: {
  source: {
    id: number;
    name: string;
    url: string;
    type: string;
    language_default: string | null;
    cron_expression: string | null;
    is_active: boolean;
    error_streak: number;
  };
}) {
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);
  const [isPending, startTransition] = useTransition();

  return (
    <form
      action={(formData) =>
        startTransition(async () => {
          setError(null);

          try {
            await apiWrite(`/admin/sources/${source.id}`, "PATCH", {
              name: formData.get("name"),
              url: formData.get("url"),
              type: formData.get("type"),
              language_default: formData.get("language_default"),
              cron_expression: formData.get("cron_expression"),
              is_active: formData.get("is_active") === "on",
              error_streak: Number(formData.get("error_streak") ?? source.error_streak),
            });
            router.refresh();
          } catch (caughtError) {
            setError(caughtError instanceof Error ? caughtError.message : "Update failed");
          }
        })
      }
      className="grid gap-3 rounded-2xl border border-zinc-100 bg-zinc-50 p-4"
    >
      <input name="name" defaultValue={source.name} className="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
      <input name="url" defaultValue={source.url} className="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
      <div className="grid gap-3 md:grid-cols-4">
        <input name="type" defaultValue={source.type} className="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
        <input name="language_default" defaultValue={source.language_default ?? ""} className="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
        <input name="cron_expression" defaultValue={source.cron_expression ?? ""} className="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
        <input name="error_streak" type="number" defaultValue={source.error_streak} className="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
      </div>
      <label className="flex items-center gap-3 text-sm text-zinc-700">
        <input type="checkbox" name="is_active" defaultChecked={source.is_active} />
        Active source
      </label>
      <div className="flex items-center gap-3">
        <button className="rounded-full bg-zinc-950 px-4 py-2 text-sm font-semibold text-white">
          {isPending ? "Saving..." : "Save source"}
        </button>
        {error ? <p className="text-xs text-rose-600">{error}</p> : null}
      </div>
    </form>
  );
}

export function SourceCreator() {
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);
  const [isPending, startTransition] = useTransition();

  return (
    <form
      action={(formData) =>
        startTransition(async () => {
          setError(null);

          try {
            await apiWrite("/admin/sources", "POST", {
              name: formData.get("name"),
              url: formData.get("url"),
              type: formData.get("type"),
              language_default: formData.get("language_default"),
              cron_expression: formData.get("cron_expression"),
              is_active: true,
              error_streak: 0,
            });
            router.refresh();
          } catch (caughtError) {
            setError(caughtError instanceof Error ? caughtError.message : "Create failed");
          }
        })
      }
      className="grid gap-3 md:grid-cols-4"
    >
      <input name="name" placeholder="Name" className="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm" />
      <input name="url" placeholder="URL" className="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm" />
      <input name="type" defaultValue="rss" className="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm" />
      <input name="language_default" defaultValue="en" className="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm" />
      <input name="cron_expression" defaultValue="*/5 * * * *" className="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm md:col-span-3" />
      <div className="flex items-center gap-3">
        <button className="rounded-full bg-orange-500 px-5 py-3 text-sm font-semibold text-white">
          {isPending ? "Creating..." : "Create source"}
        </button>
        {error ? <p className="text-xs text-rose-600">{error}</p> : null}
      </div>
    </form>
  );
}

export function LogoutButton() {
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);
  const [isPending, startTransition] = useTransition();

  return (
    <div className="space-y-2">
      <button
        type="button"
        onClick={() =>
          startTransition(async () => {
            setError(null);

            try {
              await apiWrite("/auth/logout", "POST");
              router.push("/login");
              router.refresh();
            } catch (caughtError) {
              setError(caughtError instanceof Error ? caughtError.message : "Logout failed");
            }
          })
        }
        className="rounded-full border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-700"
      >
        {isPending ? "Signing out..." : "Logout"}
      </button>
      {error ? <p className="text-xs text-rose-600">{error}</p> : null}
    </div>
  );
}
