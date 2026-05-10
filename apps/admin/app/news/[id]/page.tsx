import { AdminActionButton } from "../../../components/client-actions";
import { requireAdminUser, serverApiClient } from "../../../lib/server-api";
import { AppShell, Pill, SectionCard, StatRow } from "@smartnews/ui";
import Link from "next/link";

export default async function AdminNewsDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  await requireAdminUser();
  const api = await serverApiClient();
  const { id } = await params;
  const news = (await api.admin.news.detail(id)).data;

  return (
    <AppShell
      eyebrow="SmartNews / Admin detail"
      title={news.title.effective}
      description="Detail view даёт точку опоры для более богатого admin UX, но уже сейчас работает на новом API-контракте и умеет запускать per-item AI flow."
    >
      <SectionCard
        title="Actions"
        actions={<Link href="/news" className="rounded-full border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-700">Back to list</Link>}
      >
        <div className="flex flex-wrap gap-3">
          <AdminActionButton path={`/admin/news/${news.id}/reanalyze`} label="Reanalyze item" />
        </div>
      </SectionCard>

      <div className="grid gap-6 xl:grid-cols-[2fr_1fr]">
        <SectionCard title="Content">
          <p className="text-sm leading-7 whitespace-pre-line text-zinc-700">{news.content_translated ?? news.content_original}</p>
        </SectionCard>
        <SectionCard title="Metadata">
          <StatRow label="Source" value={news.source.name} />
          <StatRow label="Status" value={<Pill>{news.status}</Pill>} />
          <StatRow label="Fingerprint" value={news.raw_fingerprint} />
          <StatRow label="AI Provider" value={news.analysis.provider ?? "n/a"} />
          <StatRow label="AI Model" value={news.analysis.model ?? "n/a"} />
          <StatRow label="Published" value={news.published_at ?? "n/a"} />
        </SectionCard>
      </div>
    </AppShell>
  );
}
