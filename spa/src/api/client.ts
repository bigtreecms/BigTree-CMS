import { ApiError, type ApiErrorPayload, type ApiMeta, type ApiSuccess } from "@/types/api";
import { authStore, type AuthUser } from "@/auth/store";
import { apiBase } from "@/lib/adminBoot";

/**
 * Centralized fetch wrapper for the BigTree REST API.
 *
 * Responsibilities:
 *   - Prepend the API base from {@link apiBase} ("/users" → "{apiBase}/users")
 *   - Inject Authorization: Bearer <access_token> from the auth store
 *   - JSON-encode body, set Content-Type
 *   - Parse the response envelope; throw a typed ApiError on non-2xx
 *   - On 401: attempt one /auth/refresh and retry the original request
 *
 * The refresh-on-401 dance is implemented with a single in-flight promise so
 * a burst of parallel 401s doesn't trigger N refresh requests.
 *
 * Refresh tokens live in localStorage (via authStore) and travel in the
 * request body — there is no cookie involvement here.
 */

interface RefreshResponse {
	access_token: string;
	refresh_token: string;
	expires_in: number;
	user: AuthUser;
}

/** Tracks the in-flight refresh, if any. Concurrent 401s await this. */
let refreshPromise: Promise<boolean> | null = null;

export interface ApiCallOptions {
	method?: "GET" | "POST" | "PATCH" | "DELETE" | "PUT";
	body?: unknown;
	headers?: Record<string, string>;
	query?: Record<string, string | number | boolean | undefined | null>;
	/** Skip the access-token header (used by /auth/login itself). */
	skipAuth?: boolean;
	/** Skip the refresh-on-401 retry (used by /auth/refresh to avoid loops). */
	skipRefresh?: boolean;
	/** AbortSignal for cancellation (TanStack Query passes this). */
	signal?: AbortSignal;
	/**
	 * When true, the promise resolves with the full envelope { data, meta }
	 * instead of just the data. Useful for paginated endpoints.
	 */
	withMeta?: boolean;
}

function buildQuery(query: ApiCallOptions["query"]): string {
	if (!query) return "";
	const params = new URLSearchParams();
	for (const [key, value] of Object.entries(query)) {
		if (value === undefined || value === null) continue;
		params.set(key, String(value));
	}
	const s = params.toString();
	return s ? `?${s}` : "";
}

/**
 * Attempt to refresh the access token. Reads the current refresh token from
 * the auth store, sends it to /auth/refresh, and on success stores the new
 * pair. On failure clears the session — the next render will land on /login.
 */
async function refreshAccessToken(): Promise<boolean> {
	const refresh = authStore.getState().refreshToken;
	if (!refresh) return false;

	try {
		const response = await fetch(`${apiBase()}/auth/refresh`, {
			method: "POST",
			headers: { "Content-Type": "application/json" },
			body: JSON.stringify({ refresh_token: refresh }),
		});

		if (!response.ok) return false;

		const payload = (await response.json()) as ApiSuccess<RefreshResponse>;
		const data = payload.data;
		authStore
			.getState()
			.setSession(data.access_token, data.refresh_token, data.expires_in, data.user);
		return true;
	} catch {
		return false;
	}
}

/** Single-flight refresh — subsequent concurrent callers reuse the same promise. */
function refreshOnce(): Promise<boolean> {
	if (!refreshPromise) {
		refreshPromise = refreshAccessToken().finally(() => {
			refreshPromise = null;
		});
	}
	return refreshPromise;
}

async function request<T>(path: string, opts: ApiCallOptions = {}): Promise<T> {
	const url = `${apiBase()}${path}${buildQuery(opts.query)}`;
	const headers: Record<string, string> = {
		Accept: "application/json",
		...(opts.headers ?? {}),
	};

	const token = authStore.getState().accessToken;
	if (!opts.skipAuth && token) headers.Authorization = `Bearer ${token}`;

	const method = opts.method ?? "GET";
	const hasBody = opts.body !== undefined && method !== "GET";
	if (hasBody && !headers["Content-Type"]) headers["Content-Type"] = "application/json";

	const init: RequestInit = { method, headers, signal: opts.signal };
	if (hasBody) {
		init.body =
			opts.body instanceof FormData ||
			opts.body instanceof Blob ||
			typeof opts.body === "string"
				? (opts.body as BodyInit)
				: JSON.stringify(opts.body);
		// FormData sets its own multipart boundary header — don't override.
		if (init.body instanceof FormData) delete headers["Content-Type"];
	}

	const response = await fetch(url, init);
	return handleResponse<T>(response, path, opts);
}

async function handleResponse<T>(
	response: Response,
	path: string,
	opts: ApiCallOptions
): Promise<T> {
	if (response.status === 204) return undefined as T;

	const text = await response.text();
	let payload: unknown = null;
	if (text) {
		try {
			payload = JSON.parse(text);
		} catch {
			if (!response.ok) {
				throw new ApiError(response.status, null, text.slice(0, 200));
			}
		}
	}

	if (response.ok) {
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		const success = payload as ApiSuccess<any> | null;

		if (opts.withMeta) {
			return {
				data: success?.data ?? undefined,
				meta: success?.meta,
			} as unknown as T;
		}

		return (success?.data as T) ?? (undefined as T);
	}

	// 401 → try one refresh + retry. The refresh call itself sets skipRefresh
	// to avoid an infinite loop.
	if (response.status === 401 && !opts.skipRefresh && !opts.skipAuth) {
		const refreshed = await refreshOnce();
		if (refreshed) {
			return request<T>(path, { ...opts, skipRefresh: true });
		}
		authStore.getState().clear();
	}

	const error = new ApiError(response.status, payload as ApiErrorPayload | null);

	// Developer mode: the admin is in maintenance and limited to developers.
	// Flag it globally so the Shell can swap to the lockout screen instead of
	// every page surfacing its own 403.
	if (error.status === 403 && error.code === "developer_mode") {
		authStore.getState().setDeveloperLockout(true);
	}

	throw error;
}

export const api = {
	get: <T>(path: string, opts?: Omit<ApiCallOptions, "method" | "body">) =>
		request<T>(path, { ...opts, method: "GET" }),

	post: <T>(path: string, body?: unknown, opts?: Omit<ApiCallOptions, "method" | "body">) =>
		request<T>(path, { ...opts, method: "POST", body }),

	patch: <T>(path: string, body?: unknown, opts?: Omit<ApiCallOptions, "method" | "body">) =>
		request<T>(path, { ...opts, method: "PATCH", body }),

	put: <T>(path: string, body?: unknown, opts?: Omit<ApiCallOptions, "method" | "body">) =>
		request<T>(path, { ...opts, method: "PUT", body }),

	delete: <T>(path: string, body?: unknown, opts?: Omit<ApiCallOptions, "method" | "body">) =>
		request<T>(path, { ...opts, method: "DELETE", body }),

	/**
	 * Like `get`, but returns `{ data, meta }` so callers can access pagination info.
	 */
	getWithMeta: <T>(path: string, opts?: Omit<ApiCallOptions, "method" | "body">) =>
		request<{ data: T; meta?: ApiMeta }>(path, { ...opts, method: "GET", withMeta: true }),

	/**
	 * Like `getWithMeta` for list endpoints: reshapes the paginated envelope into
	 * the `{ items, meta }` shape the tag / user / audit list callers expose,
	 * defaulting `items` to `[]` and `meta` to `{}` when the server omits them.
	 */
	listWithMeta: <T>(path: string, opts?: Omit<ApiCallOptions, "method" | "body">) =>
		request<{ data: T[]; meta?: ApiMeta }>(path, {
			...opts,
			method: "GET",
			withMeta: true,
		}).then((res) => ({ items: res.data ?? [], meta: res.meta ?? {} })),

	/**
	 * Boot-time hydration. If we have a persisted access token, we assume it's
	 * still good — if it isn't, the first API call will 401 and the refresh
	 * dance will kick in. If we have only a refresh token (rare; happens if
	 * the access token expired while the tab was closed), we proactively
	 * refresh so the first protected route render isn't slowed by a 401 retry.
	 */
	bootstrapSession: async (): Promise<boolean> => {
		const state = authStore.getState();
		if (state.accessToken && state.user) {
			// Refresh user payload so flags like migrations_pending / features
			// aren't stale from localStorage after a code deploy.
			try {
				const me = await request<AuthUser>("/auth/me", { method: "GET" });
				authStore.getState().setUser(me);
			} catch {
				// Leave cached user; the next API call will 401/refresh if needed.
			}
			authStore.getState().setHydrated();
			return true;
		}
		if (state.refreshToken) {
			const ok = await refreshOnce();
			authStore.getState().setHydrated();
			return ok;
		}
		authStore.getState().setHydrated();
		return false;
	},
};

/**
 * The string-id CRUD quintet that most Developer-section catalogs expose
 * verbatim — `GET /base`, `GET /base/{id}`, `POST /base`, `PATCH /base/{id}`,
 * `DELETE /base/{id}`. Spread it into an endpoint object and add the extras:
 *
 *     export const feedsApi = { ...crudEndpoints<FeedSummary, FeedEditBody>("/feeds") };
 *
 *     export const templatesApi = {
 *         ...crudEndpoints<TemplateSummary, TemplateEditBody>("/templates"),
 *         reorder: (ids: string[]) => api.post<void>("/templates/reorder", { ids }),
 *     };
 *
 * Adopting it also pins the `encodeURIComponent` convention structurally, so an
 * id with a slash or a space can't slip through unencoded.
 */
export const crudEndpoints = <TSummary, TCreate = Partial<TSummary>, TUpdate = TCreate>(
	base: string
) => ({
	list: () => api.get<TSummary[]>(base),

	get: (id: string) => api.get<TSummary>(`${base}/${encodeURIComponent(id)}`),

	create: (body: TCreate) => api.post<TSummary>(base, body),

	update: (id: string, body: TUpdate) =>
		api.patch<TSummary>(`${base}/${encodeURIComponent(id)}`, body),

	delete: (id: string) => api.delete<void>(`${base}/${encodeURIComponent(id)}`),
});
