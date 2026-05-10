import { AdminActionButton } from "../../components/client-actions";
import { requireAdminUser, serverApiClient } from "../../lib/server-api";
import { AppShell, Pill, SectionCard, StatRow } from "@smartnews/ui";

export default async function AiProvidersPage() {
  await requireAdminUser();
  const api = await serverApiClient();
  const accounts = (await api.admin.aiProviders.list()).data;

  return (
    <AppShell
      eyebrow="SmartNews / AI Providers"
      title="Провайдеры AI уже живут в новом админском интерфейсе."
      description="Здесь есть текущий V1 scope: status visibility, refresh, login/logout и редактирование ключевых runtime-полей через API."
    >
      <SectionCard title="Accounts">
        <div className="grid gap-4 xl:grid-cols-2">
          {accounts.map((account) => (
            <article key={account.id} className="rounded-[1.5rem] border border-zinc-100 bg-zinc-50 p-5">
              <div className="flex flex-wrap gap-2">
                <Pill tone={account.is_enabled ? "success" : "neutral"}>{account.display_name}</Pill>
                <Pill>{account.auth_status_label}</Pill>
                {account.plan_type ? <Pill tone="accent">{account.plan_type}</Pill> : null}
              </div>
              <div className="mt-5 space-y-1">
                <StatRow label="Model" value={account.default_model} />
                <StatRow label="Reasoning" value={account.default_reasoning_effort ?? "model default"} />
                <StatRow label="Slots" value={account.max_parallel_jobs} />
                <StatRow label="Email" value={account.account_email ?? "n/a"} />
                <StatRow label="Auth code" value={account.login_id ?? "n/a"} />
              </div>
              <div className="mt-5 flex flex-wrap gap-3">
                <AdminActionButton path={`/admin/ai-provider-accounts/${account.id}/sync`} label="Refresh status" />
                <AdminActionButton path={`/admin/ai-provider-accounts/${account.id}/login`} label="Start login" />
                <AdminActionButton path={`/admin/ai-provider-accounts/${account.id}/cancel-login`} label="Cancel login" />
                <AdminActionButton path={`/admin/ai-provider-accounts/${account.id}/logout`} label="Logout" />
              </div>
            </article>
          ))}
        </div>
      </SectionCard>
    </AppShell>
  );
}
