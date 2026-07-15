/**
 * Thin REST helpers for Playwright setup/teardown (not the system under test UI).
 */

import { e2eApiBase, e2eEmail, e2ePassword } from "./env";

export interface ApiSession {
	accessToken: string;
	level: number;
	userId: number;
}

async function request(
	method: string,
	path: string,
	opts: { token?: string; body?: unknown } = {}
): Promise<{ status: number; json: any }> {
	const headers: Record<string, string> = {
		Accept: "application/json",
	};

	if (opts.token) {
		headers.Authorization = `Bearer ${opts.token}`;
	}

	if (opts.body !== undefined) {
		headers["Content-Type"] = "application/json";
	}

	const res = await fetch(`${e2eApiBase()}${path}`, {
		method,
		headers,
		body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined,
	});

	let json: any = null;
	const text = await res.text();

	if (text) {
		try {
			json = JSON.parse(text);
		} catch {
			json = { raw: text };
		}
	}

	return { status: res.status, json };
}

export async function apiLogin(email = e2eEmail(), password = e2ePassword()): Promise<ApiSession> {
	// Retry once after a short wait if rate-limited (serial suite can burst).
	for (let attempt = 0; attempt < 3; attempt++) {
		const { status, json } = await request("POST", "/auth/login", {
			body: { email, password },
		});

		if (status === 200 && json?.data?.access_token) {
			return {
				accessToken: json.data.access_token as string,
				userId: Number(json.data.user?.id ?? 0),
				level: Number(json.data.user?.level ?? 0),
			};
		}

		if (status === 429) {
			const retry = Number(json?.errors?.[0]?.retry_after ?? 5);
			await new Promise((r) => setTimeout(r, (retry + 1) * 1000));
			continue;
		}

		throw new Error(`apiLogin failed (${status}): ${JSON.stringify(json)}`);
	}

	throw new Error("apiLogin failed after retries (rate limited)");
}

export async function apiJson<T = any>(
	session: ApiSession,
	method: string,
	path: string,
	body?: unknown
): Promise<{ status: number; data: T }> {
	const { status, json } = await request(method, path, {
		token: session.accessToken,
		body,
	});

	return { status, data: (json?.data ?? json) as T };
}

export async function apiCreateUser(
	session: ApiSession,
	opts: { email: string; password: string; name: string; level?: number }
) {
	const { status, data } = await apiJson<{ id: number }>(session, "POST", "/users", {
		email: opts.email,
		password: opts.password,
		name: opts.name,
		level: opts.level ?? 0,
	});

	if (status !== 201) {
		throw new Error(`apiCreateUser failed (${status}): ${JSON.stringify(data)}`);
	}

	return data;
}

export async function apiDeleteUser(session: ApiSession, id: number) {
	await apiJson(session, "DELETE", `/users/${id}`);
}

export async function apiCreateSetting(session: ApiSession, id: string, name: string) {
	const { status, data } = await apiJson(session, "POST", "/settings", {
		id,
		name,
		type: "text",
		locked: false,
		system: false,
	});

	if (status !== 201 && status !== 409) {
		throw new Error(`apiCreateSetting failed (${status}): ${JSON.stringify(data)}`);
	}
}

export async function apiDeleteSetting(session: ApiSession, id: string) {
	await apiJson(session, "DELETE", `/settings/${id}`);
}

export async function apiScaffoldModule(
	session: ApiSession,
	opts: { name: string; table: string; route: string }
) {
	const { status, data } = await apiJson<{ id: string; route: string }>(
		session,
		"POST",
		"/modules/scaffold",
		{
			name: opts.name,
			table: opts.table,
			route: opts.route,
			fields: [
				{ title: "Title", type: "text" },
				{ title: "Content", type: "html" },
			],
			actions: { edit: true, delete: true },
		}
	);

	if (status !== 201) {
		throw new Error(`apiScaffoldModule failed (${status}): ${JSON.stringify(data)}`);
	}

	return data;
}

export async function apiDeleteModule(session: ApiSession, id: string) {
	await apiJson(session, "DELETE", `/modules/${id}`);
}
