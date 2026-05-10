export type CursorMeta = {
  per_page: number;
  next_cursor: string | null;
  prev_cursor: string | null;
  total: number;
};

export type PaginatedResponse<T> = {
  data: T[];
  meta: CursorMeta;
};

export type ApiErrorPayload = {
  error: {
    code: string;
    message: string;
    details?: Record<string, unknown>;
  };
};

export type AuthenticatedUser = {
  id: number;
  name: string;
  email: string;
  role: string;
  abilities: string[];
};

export type SourceSummary = {
  id: number;
  name: string;
};

export type SourceRecord = SourceSummary & {
  url: string;
  type: string;
  language_default: string | null;
  cron_expression: string | null;
  is_active: boolean;
  retry_backoff_state: Record<string, unknown> | null;
  last_success_at: string | null;
  last_error_at: string | null;
  error_streak: number;
};

export type NewsMedia = {
  image_url: string | null;
  image_url_original: string | null;
  image_url_local: string | null;
  items: Array<Record<string, unknown>>;
  original_items: Array<Record<string, unknown>>;
  local_items: Array<Record<string, unknown>>;
};

export type NewsAnalysis = {
  provider: string | null;
  model: string | null;
  reasoning_effort: string | null;
  status: string | null;
};

export type NewsListItem = {
  id: string;
  source: SourceSummary;
  title: string;
  excerpt: string;
  sentiment: number;
  tags: string[];
  important: boolean;
  status: string;
  published_at: string | null;
  media: NewsMedia;
};

export type NewsDetail = NewsListItem & {
  content: string;
  analysis: NewsAnalysis;
};

export type NewsFiltersMeta = {
  categories: string[];
  sources: SourceSummary[];
  sentiment_range: {
    min: number;
    max: number;
  };
};

export type AdminNewsListItem = {
  id: string;
  source: SourceSummary;
  title: {
    original: string;
    generated: string | null;
    effective: string;
  };
  status: string;
  important: boolean;
  sentiment: number;
  analysis: NewsAnalysis;
  published_at: string | null;
};

export type AdminNewsDetail = AdminNewsListItem & {
  content_original: string;
  content_translated: string | null;
  raw_fingerprint: string;
  tags: string[];
  source_metadata: Record<string, unknown>;
  media: Array<Record<string, unknown>>;
};

export type AiProviderAccount = {
  id: number;
  slug: string;
  provider: string;
  display_name: string;
  is_enabled: boolean;
  codex_home_subpath: string;
  default_model: string;
  default_reasoning_effort: string | null;
  max_parallel_jobs: number;
  auth_status: string;
  auth_status_label: string;
  auth_mode: string | null;
  login_id: string | null;
  auth_url: string | null;
  account_email: string | null;
  plan_type: string | null;
  rate_limit_snapshot: Record<string, unknown> | null;
  last_status_checked_at: string | null;
  last_authenticated_at: string | null;
  last_error_at: string | null;
  last_error_message: string | null;
};

export type AdminSettings = {
  news_auto_refresh_enabled: boolean;
  news_auto_refresh_interval_seconds: number;
  news_auto_refresh_selection_options: Record<string, string>;
};

export type BulkActionResult = {
  enqueued: number;
};
