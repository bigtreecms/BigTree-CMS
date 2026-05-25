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

export const dashboardApi = {
	summary: () => api.get<DashboardSummary>("/dashboard/summary"),
	analytics: () => api.get<AnalyticsResponse>("/dashboard/analytics"),
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

export const pendingChangesApi = {
	list: (params: { mine?: boolean; page?: number; per_page?: number } = {}) =>
		api.get<PendingChange[]>("/pending-changes", { query: params }),
};

export interface Message {
	id: number;
	sender: number;
	recipients: number[];
	subject: string;
	message: string;
	response_to: number;
	date: string;
	read_by: number[];
}

export const messagesApi = {
	list: (
		params: {
			folder?: "in" | "sent";
			page?: number;
			per_page?: number;
		} = {},
	) => api.get<Message[]>("/messages", { query: params }),
	unreadCount: () => api.get<{ unread: number }>("/messages/unread-count"),
};
