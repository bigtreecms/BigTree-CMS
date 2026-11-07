import { describe, expect, it } from "vitest";

import { modulePreviewUrl, withPreviewReturn } from "@/lib/preview";
import { relatedViewForForm } from "@/renderer/forms/useFormPreviewUrl";
import type { ModuleView } from "@/api/endpoints/modules";

const view = (id: string, table: string, extra: Partial<ModuleView> = {}): ModuleView => ({
	id,
	table,
	title: id,
	type: "searchable",
	...extra,
});

describe("modulePreviewUrl", () => {
	it("appends the entry id as a path segment", () => {
		expect(modulePreviewUrl("https://site.test/news/preview/", 12)).toBe(
			"https://site.test/news/preview/12/"
		);
		expect(modulePreviewUrl("https://site.test/news/preview", "p4")).toBe(
			"https://site.test/news/preview/p4/"
		);
	});
});

describe("withPreviewReturn", () => {
	it("adds the return link as a query parameter", () => {
		expect(
			withPreviewReturn("https://site.test/_preview/about/", "https://site.test/admin/x")
		).toBe(
			"https://site.test/_preview/about/?bigtree_preview_return=https%3A%2F%2Fsite.test%2Fadmin%2Fx"
		);
		expect(withPreviewReturn("https://site.test/p/?a=1", "r")).toBe(
			"https://site.test/p/?a=1&bigtree_preview_return=r"
		);
	});
});

describe("relatedViewForForm", () => {
	it("prefers a view naming the form, then one on the same table", () => {
		const views = [
			view("v1", "news", { preview_url: "a" }),
			view("v2", "news", { related_form: "f1", preview_url: "b" }),
		];

		expect(relatedViewForForm(views, { id: "f1", table: "news" })?.id).toBe("v2");
		expect(relatedViewForForm(views, { id: "f9", table: "news" })?.id).toBe("v1");
		expect(relatedViewForForm(views, { id: "f9", table: "events" })).toBeUndefined();
	});
});
