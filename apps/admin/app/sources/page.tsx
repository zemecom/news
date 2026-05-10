import { SourceCreator, SourceEditor } from "../../components/client-actions";
import { requireAdminUser, serverApiClient } from "../../lib/server-api";
import { AppShell, SectionCard } from "@smartnews/ui";

export default async function SourcesPage() {
  await requireAdminUser();
  const api = await serverApiClient();
  const sources = (await api.admin.sources.list()).data;

  return (
    <AppShell
      eyebrow="SmartNews / Sources"
      title="Источники управляются уже из отдельного admin frontend."
      description="Этот раздел закрывает базовый CRUD-контур для источников и больше не зависит от Filament resource pages."
    >
      <SectionCard title="Create source" description="Новая запись сразу уходит в `/api/v1/admin/sources`.">
        <SourceCreator />
      </SectionCard>

      <SectionCard title="Existing sources">
        <div className="space-y-4">
          {sources.map((source) => (
            <SourceEditor key={source.id} source={source} />
          ))}
        </div>
      </SectionCard>
    </AppShell>
  );
}
