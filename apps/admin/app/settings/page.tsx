import { SettingsForm } from "../../components/client-actions";
import { requireAdminUser, serverApiClient } from "../../lib/server-api";
import { AppShell, SectionCard } from "@smartnews/ui";

export default async function SettingsPage() {
  await requireAdminUser();
  const api = await serverApiClient();
  const settings = (await api.admin.settings.show()).data;

  return (
    <AppShell
      eyebrow="SmartNews / Settings"
      title="Admin settings тоже переехали в новый frontend."
      description="Пока здесь только текущие настройки auto-refresh, но этот экран уже закладывает чистый паттерн для дальнейших admin preferences."
    >
      <SectionCard title="News auto-refresh">
        <SettingsForm
          enabled={settings.news_auto_refresh_enabled}
          intervalSeconds={settings.news_auto_refresh_interval_seconds}
          options={settings.news_auto_refresh_selection_options}
        />
      </SectionCard>
    </AppShell>
  );
}
