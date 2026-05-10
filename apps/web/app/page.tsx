import { createApiClient } from "@smartnews/api-client";
import { publicNavigation } from "@smartnews/config";
import type { NewsListItem, NewsFiltersMeta } from "@smartnews/types";
import { AppShell, Pill, SectionCard } from "@smartnews/ui";
import Link from "next/link";

type SearchParams = Record<string, string | string[] | undefined>;

const apiClient = createApiClient({
  apiBaseUrl: process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://api.localhost:8080/api/v1",
  credentials: "include",
});

function first(value: string | string[] | undefined) {
  return Array.isArray(value) ? value[0] : value;
}

function queryString(searchParams: SearchParams) {
  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(searchParams)) {
    const normalized = first(value);

    if (normalized) {
      params.set(key, normalized);
    }
  }

  const encoded = params.toString();

  return encoded ? `?${encoded}` : "";
}

function cardTone(item: NewsListItem) {
  if (item.important) {
    return "accent" as const;
  }

  if (item.sentiment >= 3) {
    return "success" as const;
  }

  if (item.sentiment <= -3) {
    return "danger" as const;
  }

  return "neutral" as const;
}

export default async function Page({
  searchParams,
}: {
  searchParams: Promise<SearchParams>;
}) {
  const resolvedSearchParams = await searchParams;
  const [newsEnvelope, filtersEnvelope] = await Promise.all([
    apiClient.publicNews.list(queryString(resolvedSearchParams)),
    apiClient.publicNews.filters(),
  ]);

  const news = newsEnvelope.data;
  const filters = filtersEnvelope.data as NewsFiltersMeta;

  return (
    <AppShell
      eyebrow="SmartNews / Web"
      title="Новый публичный контур на Next.js уже разговаривает с versioned Laravel API."
      description="Это SSR-first лента: фильтры, поиск и cursor pagination живут в query params, поэтому URL сразу остаётся шэримым и индексируемым."
    >
      <div className="flex flex-wrap items-center gap-3 px-1 text-sm text-zinc-600">
        {publicNavigation.map((item) => (
          <Link key={item.href} href={item.href} className="rounded-full border border-zinc-200 bg-white px-4 py-2">
            {item.label}
          </Link>
        ))}
        <Pill tone="accent">{newsEnvelope.meta?.total ?? news.length} news items</Pill>
      </div>

      <SectionCard
        title="Фильтры"
        description="Форма работает через стандартный GET, так что все параметры сразу попадают в URL и подходят для deep-linking."
      >
        <form className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <input
            name="q"
            defaultValue={first(resolvedSearchParams.q) ?? ""}
            placeholder="Search headlines"
            className="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm outline-none transition focus:border-orange-400 focus:bg-white"
          />
          <select
            name="category"
            defaultValue={first(resolvedSearchParams.category) ?? ""}
            className="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm outline-none transition focus:border-orange-400 focus:bg-white"
          >
            <option value="">All categories</option>
            {filters.categories.map((category) => (
              <option key={category} value={category}>
                {category}
              </option>
            ))}
          </select>
          <select
            name="source_id"
            defaultValue={first(resolvedSearchParams.source_id) ?? ""}
            className="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm outline-none transition focus:border-orange-400 focus:bg-white"
          >
            <option value="">All sources</option>
            {filters.sources.map((source) => (
              <option key={source.id} value={source.id}>
                {source.name}
              </option>
            ))}
          </select>
          <select
            name="important"
            defaultValue={first(resolvedSearchParams.important) ?? ""}
            className="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm outline-none transition focus:border-orange-400 focus:bg-white"
          >
            <option value="">All priorities</option>
            <option value="1">Important only</option>
            <option value="0">Regular only</option>
          </select>
          <input
            type="number"
            name="sentiment_min"
            min={filters.sentiment_range.min}
            max={filters.sentiment_range.max}
            defaultValue={first(resolvedSearchParams.sentiment_min) ?? ""}
            placeholder="Sentiment min"
            className="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm outline-none transition focus:border-orange-400 focus:bg-white"
          />
          <input
            type="number"
            name="sentiment_max"
            min={filters.sentiment_range.min}
            max={filters.sentiment_range.max}
            defaultValue={first(resolvedSearchParams.sentiment_max) ?? ""}
            placeholder="Sentiment max"
            className="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm outline-none transition focus:border-orange-400 focus:bg-white"
          />
          <input
            type="date"
            name="date_from"
            defaultValue={first(resolvedSearchParams.date_from) ?? ""}
            className="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm outline-none transition focus:border-orange-400 focus:bg-white"
          />
          <input
            type="date"
            name="date_to"
            defaultValue={first(resolvedSearchParams.date_to) ?? ""}
            className="rounded-2xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm outline-none transition focus:border-orange-400 focus:bg-white"
          />
          <div className="flex gap-3 xl:col-span-4">
            <button className="rounded-full bg-zinc-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800">
              Apply filters
            </button>
            <Link href="/" className="rounded-full border border-zinc-200 bg-white px-5 py-3 text-sm font-semibold text-zinc-700">
              Reset
            </Link>
          </div>
        </form>
      </SectionCard>

      <div className="grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
        {news.map((item) => (
          <Link key={item.id} href={`/news/${item.id}`} className="group">
            <article className="h-full rounded-[1.75rem] border border-white/70 bg-white/95 p-6 shadow-[0_18px_60px_rgba(15,23,42,0.08)] transition duration-300 group-hover:-translate-y-1 group-hover:shadow-[0_30px_90px_rgba(15,23,42,0.14)]">
              <div className="flex flex-wrap gap-2">
                <Pill tone={cardTone(item)}>{item.important ? "Important" : "Feed"}</Pill>
                <Pill>{item.source.name}</Pill>
              </div>
              <h2 className="mt-4 text-2xl font-semibold tracking-tight text-zinc-900">{item.title}</h2>
              <p className="mt-3 text-sm leading-6 text-zinc-600">{item.excerpt}</p>
              <div className="mt-5 flex flex-wrap gap-2">
                {item.tags.slice(0, 3).map((tag) => (
                  <span key={tag} className="rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600">
                    #{tag}
                  </span>
                ))}
              </div>
              <div className="mt-6 flex items-center justify-between border-t border-zinc-100 pt-4 text-xs uppercase tracking-[0.2em] text-zinc-400">
                <span>{item.status}</span>
                <span>{item.published_at ? new Date(item.published_at).toLocaleString("ru-RU") : "n/a"}</span>
              </div>
            </article>
          </Link>
        ))}
      </div>

      <SectionCard title="Pagination" description="Cursor pagination использует те же query params, так что переходы между страницами остаются детерминированными.">
        <div className="flex flex-wrap gap-3">
          {newsEnvelope.meta?.prev_cursor ? (
            <Link
              href={`/?${new URLSearchParams({ ...Object.fromEntries(Object.entries(resolvedSearchParams).map(([key, value]) => [key, first(value) ?? ""])), cursor: newsEnvelope.meta.prev_cursor }).toString()}`}
              className="rounded-full border border-zinc-200 bg-white px-5 py-3 text-sm font-semibold text-zinc-700"
            >
              Previous page
            </Link>
          ) : null}
          {newsEnvelope.meta?.next_cursor ? (
            <Link
              href={`/?${new URLSearchParams({ ...Object.fromEntries(Object.entries(resolvedSearchParams).map(([key, value]) => [key, first(value) ?? ""])), cursor: newsEnvelope.meta.next_cursor }).toString()}`}
              className="rounded-full bg-orange-500 px-5 py-3 text-sm font-semibold text-white"
            >
              Next page
            </Link>
          ) : null}
        </div>
      </SectionCard>
    </AppShell>
  );
}
