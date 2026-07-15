import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";

import { e2eApiBase, e2eEmail, e2ePassword } from "./env";

/**
 * Seed SPA auth via a single API login + localStorage write, then hard-nav to
 * /dashboard. Avoids repeated UI /auth/login hits that trip the 10/min limit.
 */
export async function uiLogin(
	page: Page,
	email = e2eEmail(),
	password = e2ePassword()
): Promise<void> {
	const res = await fetch(`${e2eApiBase()}/auth/login`, {
		method: "POST",
		headers: { "Content-Type": "application/json", Accept: "application/json" },
		body: JSON.stringify({ email, password }),
	});
	const json = (await res.json()) as {
		data?: {
			access_token: string;
			refresh_token: string;
			expires_in?: number;
			user: {
				id: number;
				email: string;
				name: string;
				level: number;
				timezone?: string;
				migrations_pending?: boolean;
			};
		};
		errors?: unknown;
	};

	if (res.status === 429) {
		// Wait out the fixed 60s window partially based on retry_after.
		const retry = Number(
			(json as { errors?: { retry_after?: number }[] }).errors?.[0]?.retry_after ?? 20
		);
		await new Promise((r) => setTimeout(r, (retry + 1) * 1000));
		return uiLogin(page, email, password);
	}

	if (res.status !== 200 || !json.data?.access_token) {
		throw new Error(`uiLogin API failed (${res.status}): ${JSON.stringify(json)}`);
	}

	const expiresIn = Number(json.data.expires_in ?? 900);
	const payload = {
		accessToken: json.data.access_token,
		refreshToken: json.data.refresh_token,
		expiresAt: Date.now() + expiresIn * 1000,
		user: json.data.user,
	};

	// Ensure we are on the app origin before writing localStorage.
	await page.goto("/login");
	await page.evaluate((auth) => {
		localStorage.setItem("bigtree:auth", JSON.stringify(auth));
	}, payload);
	await page.goto("/dashboard");

	const dashboard = page
		.getByTestId("dashboard-page")
		.or(page.getByRole("heading", { name: "Dashboard" }));
	await expect(dashboard.first()).toBeVisible({ timeout: 20_000 });
}

export async function uiLogout(page: Page): Promise<void> {
	await page.evaluate(() => {
		localStorage.removeItem("bigtree:auth");
		localStorage.removeItem("bigtree:auth:origin");
		sessionStorage.clear();
	});
	await page.goto("/login");
	await expect(
		page.getByTestId("login-email").or(page.getByLabel(/^Email$/i)).first()
	).toBeVisible();
}
