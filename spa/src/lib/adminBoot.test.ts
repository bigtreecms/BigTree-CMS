import { describe, expect, it } from "vitest";

import {
	adminPath,
	apiBase,
	getAdminBoot,
	joinAdminPath,
	resolveAdminBoot,
	routerBasename,
	type BigTreeAdminBoot,
} from "@/lib/adminBoot";

describe("resolveAdminBoot", () => {
	it("returns dev defaults and ignores injection in dev", () => {
		const injected: BigTreeAdminBoot = {
			basename: "/remaster/admin",
			apiBase: "/remaster/admin/api/v1",
		};

		expect(resolveAdminBoot({ dev: true }, injected)).toEqual({
			basename: "",
			apiBase: "/admin/api/v1",
			assetBase: "/",
		});
	});

	it("uses /admin production fallback when nothing is injected", () => {
		expect(resolveAdminBoot({ dev: false }, undefined)).toEqual({
			basename: "/admin",
			apiBase: "/admin/api/v1",
			assetBase: "/admin/",
		});
	});

	it("prefers a complete injected boot in production and strips trailing slashes", () => {
		const boot = resolveAdminBoot(
			{ dev: false },
			{
				basename: "/remaster/admin/",
				apiBase: "/remaster/admin/api/v1/",
				wwwRoot: "https://example.com/remaster/",
				assetBase: "/remaster/admin/",
			}
		);

		expect(boot).toEqual({
			basename: "/remaster/admin",
			apiBase: "/remaster/admin/api/v1",
			wwwRoot: "https://example.com/remaster/",
			assetBase: "/remaster/admin/",
		});
	});

	it("falls back when injection is incomplete", () => {
		expect(
			resolveAdminBoot({ dev: false }, {
				basename: "/admin",
				apiBase: "",
			} as BigTreeAdminBoot)
		).toEqual({
			basename: "/admin",
			apiBase: "/admin/api/v1",
			assetBase: "/admin/",
		});
	});
});

describe("getAdminBoot / apiBase (dev unit env)", () => {
	it("exposes the Vite proxy API prefix in the unit test environment", () => {
		expect(getAdminBoot().apiBase).toBe("/admin/api/v1");
		expect(apiBase()).toBe("/admin/api/v1");
	});
});

describe("routerBasename", () => {
	it("normalizes an empty basename to / for React Router", () => {
		// Unit env is DEV, so basename is "".
		expect(routerBasename()).toBe("/");
	});
});

describe("joinAdminPath / adminPath", () => {
	it("joins under an empty basename", () => {
		expect(joinAdminPath("", "/dashboard")).toBe("/dashboard");
		expect(joinAdminPath("", "dashboard")).toBe("/dashboard");
	});

	it("joins under a production basename without doubling slashes", () => {
		expect(joinAdminPath("/admin", "/dashboard")).toBe("/admin/dashboard");
		expect(joinAdminPath("/remaster/admin", "pages/1/edit")).toBe(
			"/remaster/admin/pages/1/edit"
		);
	});

	it("adminPath uses the current boot basename (dev: empty)", () => {
		expect(adminPath("/dashboard")).toBe("/dashboard");
	});
});
