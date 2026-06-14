import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { api } from "@/api/client";
import { authStore, type AuthUser } from "@/auth/store";
import { ApiError } from "@/types/api";

/**
 * Test seam for the API layer (template for future API-layer tests):
 *   - global `fetch` is stubbed per-test via vi.stubGlobal and torn down after.
 *   - the REAL authStore is driven (setSession/clear) rather than mocked — it is
 *     a plain in-memory store; localStorage persistence silently no-ops in the
 *     node test env, which is fine for these assertions.
 *   - the refresh single-flight (module-level `refreshPromise` in client.ts)
 *     resets itself in a finally(), and every test lets its refresh settle, so
 *     there is no cross-test bleed without resetModules().
 */

const user: AuthUser = { id: 1, email: "a@b.com", name: "A", level: 1 };

const jsonResponse = (status: number, body: unknown): Response =>
	new Response(JSON.stringify(body), {
		status,
		headers: { "Content-Type": "application/json" },
	});

beforeEach(() => {
	authStore.getState().clear();
});

afterEach(() => {
	vi.unstubAllGlobals();
});

describe("api request", () => {
	it("unwraps the envelope data and sends the Bearer header to the prefixed URL", async () => {
		authStore.getState().setSession("acc", "ref", 900, user);
		const fetchMock = vi.fn().mockResolvedValue(jsonResponse(200, { data: { id: 1 } }));
		vi.stubGlobal("fetch", fetchMock);

		const result = await api.get<{ id: number }>("/x");

		expect(result).toEqual({ id: 1 });
		const [url, init] = fetchMock.mock.calls[0]!;
		expect(url).toBe("/admin/api/v1/x");
		expect((init.headers as Record<string, string>).Authorization).toBe("Bearer acc");
	});

	it("builds the query string and omits undefined/null values", async () => {
		const fetchMock = vi.fn().mockResolvedValue(jsonResponse(200, { data: null }));
		vi.stubGlobal("fetch", fetchMock);

		await api.get("/x", { query: { a: 1, b: undefined, c: "z", d: null } });

		expect(fetchMock.mock.calls[0]![0]).toBe("/admin/api/v1/x?a=1&c=z");
	});

	it("getWithMeta resolves to { data, meta }", async () => {
		const fetchMock = vi
			.fn()
			.mockResolvedValue(jsonResponse(200, { data: [1, 2], meta: { total: 2 } }));
		vi.stubGlobal("fetch", fetchMock);

		const res = await api.getWithMeta<number[]>("/x");

		expect(res.data).toEqual([1, 2]);
		expect(res.meta).toEqual({ total: 2 });
	});

	it("resolves a 204 response to undefined", async () => {
		const fetchMock = vi.fn().mockResolvedValue(new Response(null, { status: 204 }));
		vi.stubGlobal("fetch", fetchMock);

		const res = await api.delete("/x");

		expect(res).toBeUndefined();
	});
});

describe("refresh-on-401", () => {
	it("refreshes exactly once under concurrent 401s and retries both callers", async () => {
		authStore.getState().setSession("old", "ref", 900, user);

		// /x 401s until the refresh completes, then 200s. The single-flight should
		// collapse both 401s into one /auth/refresh call.
		let refreshed = false;
		const fetchMock = vi.fn((url: string) => {
			if (url.includes("/auth/refresh")) {
				refreshed = true;

				return Promise.resolve(
					jsonResponse(200, {
						data: {
							access_token: "new",
							refresh_token: "ref2",
							expires_in: 900,
							user,
						},
					})
				);
			}

			if (!refreshed) {
				return Promise.resolve(
					jsonResponse(401, { errors: [{ code: "unauthorized", message: "no" }] })
				);
			}

			return Promise.resolve(jsonResponse(200, { data: { id: 1 } }));
		});
		vi.stubGlobal("fetch", fetchMock);

		const [r1, r2] = await Promise.all([api.get("/x"), api.get("/x")]);

		expect(r1).toEqual({ id: 1 });
		expect(r2).toEqual({ id: 1 });

		const refreshCalls = fetchMock.mock.calls.filter((c) =>
			String(c[0]).includes("/auth/refresh")
		);
		expect(refreshCalls.length).toBe(1);
		expect(authStore.getState().accessToken).toBe("new");
	});

	it("clears the session when the refresh itself fails", async () => {
		authStore.getState().setSession("old", "ref", 900, user);

		const fetchMock = vi.fn((url: string) => {
			if (url.includes("/auth/refresh")) {
				return Promise.resolve(jsonResponse(401, { errors: [] }));
			}

			return Promise.resolve(
				jsonResponse(401, { errors: [{ code: "unauthorized", message: "no" }] })
			);
		});
		vi.stubGlobal("fetch", fetchMock);

		await expect(api.get("/x")).rejects.toBeInstanceOf(ApiError);
		expect(authStore.getState().accessToken).toBeNull();
	});
});
