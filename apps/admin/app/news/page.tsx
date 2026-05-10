import { AdminActionButton, LogoutButton } from "../../components/client-actions";
import { requireAdminUser, serverApiClient } from "../../lib/server-api";
import { AppShell, Pill, SectionCard } from "@smartnews/ui";
import Link from "next/link";

export default async function AdminNewsPage() {
  const user = await requireAdminUser();
  const api = await serverApiClient();
  const news = (await api.admin.news.list()).data;
  const ids = news.map((item) => item.id);

  return (
    <AppShell
      eyebrow="SmartNews / Admin"
      title="Core admin уже переехал в отдельный Next.js app."
      description={`Сейчас ты смотришь на новый admin-контур от лица ${user.email}. Эта страница работает поверх /api/v1/admin/news и уже умеет запускать AI actions.`}
    >
      <SectionCard
        title="Batch controls"
        actions={<LogoutButton />}
        description="Кнопки ниже бьют прямо в versioned admin API и используют тот же cookie-based session, который должен потом использовать любой first-party frontend."
      >
        <div className="flex flex-wrap gap-3">
          <AdminActionButton path="/admin/news/bulk/reanalyze" body={{ ids }} label="Reanalyze visible" />
          <AdminActionButton path="/admin/news/bulk/enrich-missing-ai" body={{ ids }} label="Enrich missing AI" />
          <AdminActionButton path="/admin/news/bulk/refresh-ai" body={{ ids }} label="Refresh AI metadata" />
        </div>
      </SectionCard>

      <SectionCard title="News" description="Список уже отрисовывается без Filament table и остаётся готовым к дальнейшей декомпозиции на filters, infinite pagination и detail layouts.">
        <div className="space-y-4">
          {news.map((item) => (
            <article key={item.id} className="rounded-[1.5rem] border border-zinc-100 bg-zinc-50 p-5">
              <div className="flex flex-wrap items-center gap-2">
                <Pill tone={item.important ? "accent" : "neutral"}>{item.source.name}</Pill>
                <Pill>{item.status}</Pill>
                {item.analysis.provider ? <Pill tone="success">{item.analysis.provider}</Pill> : null}
              </div>
              <div className="mt-4 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0">
                  <h2 className="text-xl font-semibold tracking-tight text-zinc-900">{item.title.effective}</h2>
                  <p className="mt-2 text-sm text-zinc-600">
                    Original: {item.title.original}
                    {item.title.generated ? ` / Generated: ${item.title.generated}` : ""}
                  </p>
                </div>
                <div className="flex flex-wrap gap-3">
                  <Link
                    href={`/news/${item.id}`}
                    className="rounded-full border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-700"
                  >
                    Open detail
                  </Link>
                  <AdminActionButton path={`/admin/news/${item.id}/reanalyze`} label="Reanalyze" />
                </div>
              </div>
            </article>
          ))}
        </div>
      </SectionCard>
    </AppShell>
  );
}
