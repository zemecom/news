import { createApiClient } from "@smartnews/api-client";
import { AppShell, Pill, SectionCard } from "@smartnews/ui";
import Link from "next/link";

const apiClient = createApiClient({
  apiBaseUrl: process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://api.localhost:8080/api/v1",
  credentials: "include",
});

export default async function NewsDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  const news = (await apiClient.publicNews.detail(id)).data;

  return (
    <AppShell
      eyebrow="SmartNews / Story"
      title={news.title}
      description="Детальная карточка уже использует тот же стабильный `/api/v1/news/{id}` контракт, так что этот экран можно без переделки переиспользовать и для mobile-клиента."
    >
      <div className="flex items-center gap-3 px-1 text-sm text-zinc-600">
        <Link href="/" className="rounded-full border border-zinc-200 bg-white px-4 py-2">
          Back to feed
        </Link>
        <Pill tone={news.important ? "accent" : "neutral"}>{news.source.name}</Pill>
      </div>

      <SectionCard title="Summary" description={news.published_at ? new Date(news.published_at).toLocaleString("ru-RU") : undefined}>
        <div className="flex flex-wrap gap-3">
          <Pill>{news.status}</Pill>
          <Pill tone={news.sentiment >= 3 ? "success" : news.sentiment <= -3 ? "danger" : "neutral"}>
            Sentiment {news.sentiment}
          </Pill>
          {news.analysis.provider ? <Pill tone="accent">{news.analysis.provider}</Pill> : null}
        </div>
        <article className="prose prose-zinc mt-6 max-w-none">
          <p className="whitespace-pre-line text-base leading-8 text-zinc-700">{news.content}</p>
        </article>
      </SectionCard>

      <SectionCard title="Metadata">
        <dl className="grid gap-4 md:grid-cols-2">
          <div className="rounded-2xl bg-zinc-50 p-4">
            <dt className="text-xs uppercase tracking-[0.2em] text-zinc-400">Tags</dt>
            <dd className="mt-3 flex flex-wrap gap-2">
              {news.tags.map((tag) => (
                <span key={tag} className="rounded-full bg-white px-3 py-1 text-sm text-zinc-700 shadow-sm">
                  #{tag}
                </span>
              ))}
            </dd>
          </div>
          <div className="rounded-2xl bg-zinc-50 p-4">
            <dt className="text-xs uppercase tracking-[0.2em] text-zinc-400">AI analysis</dt>
            <dd className="mt-3 space-y-2 text-sm text-zinc-700">
              <p>Provider: {news.analysis.provider ?? "n/a"}</p>
              <p>Model: {news.analysis.model ?? "n/a"}</p>
              <p>Reasoning: {news.analysis.reasoning_effort ?? "n/a"}</p>
              <p>Status: {news.analysis.status ?? "n/a"}</p>
            </dd>
          </div>
        </dl>
      </SectionCard>
    </AppShell>
  );
}
