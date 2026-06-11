import { api } from "@/api/client";

/** Wire shapes — mirror what the PHP services return after their JSON envelope. */

export interface DashboardSummary {
	user: { id: number; name: string; email: string; level: number };
	messages: { unread: number; total_in: number };
	pending_changes: { mine: number; publishable: number | null };
	recent_activity: Array<{
		id: number;
		table: string;
		entry: string;
		type: string;
		date: string;
	}>;
	"404s"?: { unresolved: number; redirects: number; ignored: number };
	integrity?: {
		pages_with_missing_template: number;
		pages_total: number;
		resources_total: number;
		orphan_resource_allocations: number;
	};
}

export interface AnalyticsCachePeriod {
	views: number;
	visits: number;
	bounces: number;
	bounce_rate: number;
	average_time: string;
	average_time_seconds: number;
	total_duration: number;
}

export interface AnalyticsCache {
	/** Sessions per day, keyed by YYYYMMDD. Used for the 2-week bar chart. */
	two_week?: Record<string, number>;
	year?: AnalyticsCachePeriod;
	month?: AnalyticsCachePeriod;
	quarter?: AnalyticsCachePeriod;
	year_ago_year?: AnalyticsCachePeriod;
	year_ago_month?: AnalyticsCachePeriod;
	year_ago_quarter?: AnalyticsCachePeriod;
	referrers?: Record<string, { sessions: number; screenPageViews: number }>;
	browsers?: Record<string, { sessions: number; screenPageViews: number }>;
}

export interface AnalyticsResponse {
	configured: boolean;
	verified: boolean;
	property_id: string | null;
	has_cache: boolean;
	cache_at: string | null;
	cache_age_seconds: number | null;
	cache: AnalyticsCache | null;
}

export interface ContentAlert {
	page_id: number;
	nav_title: string;
	path: string;
	updated_at: string;
	age_days: number;
	threshold_days: number;
}

export interface IntegrityStats {
	pages_with_missing_template: number;
	pages_total: number;
	resources_total: number;
	orphan_resource_allocations: number;
	/**
	 * Verbose mode (the /dashboard/integrity endpoint) also returns row lists
	 * (e.g. arrays of pages that hit each condition). We keep them loose so
	 * the consuming page can render generically without us re-typing every
	 * potential extra field.
	 */
	[key: string]: unknown;
}

export const dashboardApi = {
	summary: () => api.get<DashboardSummary>("/dashboard/summary"),
	analytics: () => api.get<AnalyticsResponse>("/dashboard/analytics"),
	contentAlerts: () => api.get<ContentAlert[]>("/dashboard/content-alerts"),
	integrity: () => api.get<IntegrityStats>("/dashboard/integrity"),
};

export interface PendingChange {
	id: number;
	user: number;
	date: string;
	title: string;
	table: string;
	item_id: number | null;
	type: string;
	module: string | null;
	pending_page_parent: number;
}

/**
 * Full pending-change row returned by GET /pending-changes/{id}. The four
 * `*_changes` fields are JSON-decoded server-side; everything else is a
 * straight DB column passthrough.
 */
export interface PendingChangeDetail extends PendingChange {
	item_id: number | null;
	changes: Record<string, unknown>;
	mtm_changes: unknown[] | Record<string, unknown>;
	tags_changes: number[] | Record<string, unknown>;
	open_graph_changes: Record<string, unknown>;
	publish_hook?: string | null;
}

export const pendingChangesApi = {
	list: (params: { mine?: boolean; page?: number; per_page?: number } = {}) =>
		api.get<PendingChange[]>("/pending-changes", { query: params }),

	get: (id: number) => api.get<PendingChangeDetail>(`/pending-changes/${id}`),

	approve: (id: number) => api.post<void>(`/pending-changes/${id}/approve`),

	reject: (id: number) => api.post<void>(`/pending-changes/${id}/reject`),
};

export interface Message {
	id: number;
	sender: number;
	/** Sender's display name; null when the account was deleted. */
	sender_name: string | null;
	recipients: number[];
	/** Resolved names aligned with `recipients` (null = deleted account). */
	recipient_names: Array<{ id: number; name: string | null }>;
	subject: string;
	message: string;
	response_to: number;
	date: string;
	read_by: number[];
}

export interface CreateMessagePayload {
	subject: string;
	message: string;
	recipients: number[];
	in_response_to?: number;
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
