import { api } from "@/api/client";

/** Wire shapes — mirror what the PHP services return after their JSON envelope. */

export interface DashboardSummary {
	"404s"?: { unresolved: number; redirects: number; ignored: number };
	integrity?: {
		pages_with_missing_template: number;
		pages_total: number;
		resources_total: number;
		orphan_resource_allocations: number;
	};
	messages: { unread: number; total_in: number };
	pending_changes: { mine: number; publishable: number | null };
	recent_activity: Array<{
		id: number;
		table: string;
		entry: string;
		type: string;
		date: string;
	}>;
	user: { id: number; name: string; email: string; level: number };
}

export interface AnalyticsCachePeriod {
	average_time: string;
	average_time_seconds: number;
	bounce_rate: number;
	bounces: number;
	total_duration: number;
	views: number;
	visits: number;
}

export interface AnalyticsCache {
	browsers?: Record<string, { sessions: number; screenPageViews: number }>;
	month?: AnalyticsCachePeriod;
	quarter?: AnalyticsCachePeriod;
	referrers?: Record<string, { sessions: number; screenPageViews: number }>;
	/** Sessions per day, keyed by YYYYMMDD. Used for the 2-week bar chart. */
	two_week?: Record<string, number>;
	year?: AnalyticsCachePeriod;
	year_ago_month?: AnalyticsCachePeriod;
	year_ago_quarter?: AnalyticsCachePeriod;
	year_ago_year?: AnalyticsCachePeriod;
}

export interface AnalyticsResponse {
	cache: AnalyticsCache | null;
	cache_age_seconds: number | null;
	cache_at: string | null;
	configured: boolean;
	has_cache: boolean;
	property_id: string | null;
	verified: boolean;
}

export interface ContentAlert {
	age_days: number;
	nav_title: string;
	page_id: number;
	path: string;
	threshold_days: number;
	updated_at: string;
}

export interface IntegrityStats {
	/**
	 * Verbose mode (the /dashboard/integrity endpoint) also returns row lists
	 * (e.g. arrays of pages that hit each condition). We keep them loose so
	 * the consuming page can render generically without us re-typing every
	 * potential extra field.
	 */
	[key: string]: unknown;
	orphan_resource_allocations: number;
	pages_total: number;
	pages_with_missing_template: number;
	resources_total: number;
}

export const dashboardApi = {
	summary: () => api.get<DashboardSummary>("/dashboard/summary"),
	analytics: () => api.get<AnalyticsResponse>("/dashboard/analytics"),
	contentAlerts: () => api.get<ContentAlert[]>("/dashboard/content-alerts"),
	integrity: () => api.get<IntegrityStats>("/dashboard/integrity"),
};

export interface PendingChange {
	date: string;
	id: number;
	item_id: number | null;
	module: string | null;
	pending_page_parent: number;
	table: string;
	title: string;
	type: string;
	user: number;
}

/**
 * Full pending-change row returned by GET /pending-changes/{id}. The four
 * `*_changes` fields are JSON-decoded server-side; everything else is a
 * straight DB column passthrough.
 */
export interface PendingChangeDetail extends PendingChange {
	changes: Record<string, unknown>;
	item_id: number | null;
	mtm_changes: unknown[] | Record<string, unknown>;
	open_graph_changes: Record<string, unknown>;
	publish_hook?: string | null;
	tags_changes: number[] | Record<string, unknown>;
}

export const pendingChangesApi = {
	list: (params: { mine?: boolean; page?: number; per_page?: number } = {}) =>
		api.get<PendingChange[]>("/pending-changes", { query: params }),

	get: (id: number) => api.get<PendingChangeDetail>(`/pending-changes/${id}`),

	approve: (id: number) => api.post<void>(`/pending-changes/${id}/approve`),

	reject: (id: number) => api.post<void>(`/pending-changes/${id}/reject`),
};

export interface Message {
	date: string;
	id: number;
	message: string;
	read_by: number[];
	/** Resolved names aligned with `recipients` (null = deleted account). */
	recipient_names: Array<{ id: number; name: string | null }>;
	recipients: number[];
	response_to: number;
	sender: number;
	/** Sender's display name; null when the account was deleted. */
	sender_name: string | null;
	subject: string;
}

export interface CreateMessagePayload {
	in_response_to?: number;
	message: string;
	recipients: number[];
	subject: string;
}

export const messagesApi = {
	list: (
		params: {
			folder?: "in" | "sent";
			page?: number;
			per_page?: number;
		} = {}
	) => api.getWithMeta<Message[]>("/messages", { query: params }),

	get: (id: number) => api.get<Message>(`/messages/${id}`),

	create: (body: CreateMessagePayload) => api.post<Message>("/messages", body),

	markRead: (id: number) => api.post<void>(`/messages/${id}/read`),

	unreadCount: () => api.get<{ unread: number }>("/messages/unread-count"),
};
