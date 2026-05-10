import type {
  AdminNewsDetail,
  AdminNewsListItem,
  AdminSettings,
  AiProviderAccount,
  ApiErrorPayload,
  AuthenticatedUser,
  BulkActionResult,
  NewsDetail,
  NewsFiltersMeta,
  NewsListItem,
  PaginatedResponse,
  SourceRecord,
  SourceSummary,
} from "@smartnews/types";

type JsonEnvelope<T> = {
  data: T;
  meta?: Record<string, unknown>;
};

type RequestOptions = {
  method?: string;
  body?: unknown;
  headers?: HeadersInit;
  credentials?: RequestCredentials;
};

export class ApiClientError extends Error {
  public readonly status: number;
  public readonly code: string;
  public readonly details?: Record<string, unknown>;

  public constructor(status: number, payload: ApiErrorPayload) {
    super(payload.error.message);

    this.name = "ApiClientError";
    this.status = status;
    this.code = payload.error.code;
    this.details = payload.error.details;
  }
}

export type ApiClientConfig = {
  apiBaseUrl: string;
  headers?: HeadersInit;
  credentials?: RequestCredentials;
};

export function createApiClient(config: ApiClientConfig) {
  const request = async <T>(
    path: string,
    options: RequestOptions = {},
  ): Promise<JsonEnvelope<T>> => {
    const response = await fetch(`${config.apiBaseUrl}${path}`, {
      method: options.method ?? "GET",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...(config.headers ?? {}),
        ...(options.headers ?? {}),
      },
      credentials: options.credentials ?? config.credentials ?? "include",
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
      cache: "no-store",
    });

    const payload = (await response.json()) as JsonEnvelope<T> | ApiErrorPayload;

    if (!response.ok) {
      throw new ApiClientError(response.status, payload as ApiErrorPayload);
    }

    return payload as JsonEnvelope<T>;
  };

  return {
    request,
    publicNews: {
      list: async (query: string) => {
        const envelope = await request<NewsListItem[]>(`/news${query}`);

        return envelope as JsonEnvelope<NewsListItem[]> & {
          meta: PaginatedResponse<NewsListItem>["meta"];
        };
      },
      detail: async (id: string) => request<NewsDetail>(`/news/${id}`),
      filters: async () => request<NewsFiltersMeta>("/news/filters"),
      sources: async () => request<SourceSummary[]>("/sources"),
    },
    auth: {
      login: async (email: string, password: string) =>
        request<AuthenticatedUser>("/auth/login", {
          method: "POST",
          body: { email, password },
        }),
      me: async () => request<AuthenticatedUser>("/auth/me"),
      logout: async () =>
        request<{ logged_out: boolean }>("/auth/logout", {
          method: "POST",
        }),
    },
    admin: {
      news: {
        list: async (query = "") => request<AdminNewsListItem[]>(`/admin/news${query}`),
        detail: async (id: string) => request<AdminNewsDetail>(`/admin/news/${id}`),
        reanalyze: async (id: string) =>
          request<BulkActionResult>(`/admin/news/${id}/reanalyze`, { method: "POST" }),
        bulkReanalyze: async (ids: Array<string | number>) =>
          request<BulkActionResult>("/admin/news/bulk/reanalyze", {
            method: "POST",
            body: { ids },
          }),
        enrichMissingAi: async (ids: Array<string | number>) =>
          request<BulkActionResult>("/admin/news/bulk/enrich-missing-ai", {
            method: "POST",
            body: { ids },
          }),
        refreshAi: async (ids: Array<string | number>) =>
          request<BulkActionResult>("/admin/news/bulk/refresh-ai", {
            method: "POST",
            body: { ids },
          }),
      },
      sources: {
        list: async () => request<SourceRecord[]>("/admin/sources"),
        show: async (id: number) => request<SourceRecord>(`/admin/sources/${id}`),
        create: async (payload: Partial<SourceRecord>) =>
          request<SourceRecord>("/admin/sources", { method: "POST", body: payload }),
        update: async (id: number, payload: Partial<SourceRecord>) =>
          request<SourceRecord>(`/admin/sources/${id}`, { method: "PATCH", body: payload }),
      },
      aiProviders: {
        list: async () => request<AiProviderAccount[]>("/admin/ai-provider-accounts"),
        update: async (id: number, payload: Partial<AiProviderAccount>) =>
          request<AiProviderAccount>(`/admin/ai-provider-accounts/${id}`, {
            method: "PATCH",
            body: payload,
          }),
        sync: async (id: number) =>
          request<{ status: string }>(`/admin/ai-provider-accounts/${id}/sync`, { method: "POST" }),
        login: async (id: number) =>
          request<{ status: string }>(`/admin/ai-provider-accounts/${id}/login`, { method: "POST" }),
        cancelLogin: async (id: number) =>
          request<{ status: string }>(`/admin/ai-provider-accounts/${id}/cancel-login`, { method: "POST" }),
        logout: async (id: number) =>
          request<{ status: string }>(`/admin/ai-provider-accounts/${id}/logout`, { method: "POST" }),
      },
      settings: {
        show: async () => request<AdminSettings>("/admin/settings"),
        update: async (payload: Pick<AdminSettings, "news_auto_refresh_enabled" | "news_auto_refresh_interval_seconds">) =>
          request<AdminSettings>("/admin/settings", { method: "PUT", body: payload }),
      },
    },
  };
}
