import { describe, expect, it } from "vitest";

import {
	isRunnableAction,
	moduleActionPath,
	resolveActionByRoute,
	visibleModuleActions,
} from "@/lib/moduleActions";
import type { ModuleAction, ModuleSummary } from "@/api/endpoints/modules";

const module: ModuleSummary = {
	id: "news",
	name: "News",
	route: "news",
	group: null,
	group_name: "",
	icon: "",
	class: "",
	table: "btx_news",
	position: 0,
	gbp: { enabled: false },
};

const action = (
	partial: Partial<ModuleAction> & Pick<ModuleAction, "id" | "name">
): ModuleAction => {
	const base: ModuleAction = {
		id: partial.id,
		name: partial.name,
		route: partial.route ?? "",
		class: partial.class ?? "",
		level: partial.level ?? 0,
		in_nav: partial.in_nav ?? true,
		position: partial.position ?? 0,
		form: partial.form ?? null,
		view: partial.view ?? null,
		report: partial.report ?? null,
		render: partial.render ?? "server",
	};

	return { ...base, ...partial };
};

describe("isRunnableAction", () => {
	it("accepts module / form / view / report actions", () => {
		expect(isRunnableAction(action({ id: "1", name: "A", render: "module" }))).toBe(true);
		expect(isRunnableAction(action({ id: "2", name: "B", form: "f1" }))).toBe(true);
		expect(isRunnableAction(action({ id: "3", name: "C", view: "v1" }))).toBe(true);
		expect(isRunnableAction(action({ id: "4", name: "D", report: "r1" }))).toBe(true);
	});

	it("rejects bare server-rendered PHP actions", () => {
		expect(isRunnableAction(action({ id: "5", name: "PHP", render: "server" }))).toBe(false);
	});
});

describe("resolveActionByRoute", () => {
	const actions = [
		action({ id: "edit", name: "Edit", route: "edit", form: "f" }),
		action({ id: "land", name: "List", route: "", view: "v" }),
	];

	it("matches the landing action on empty segments", () => {
		const resolved = resolveActionByRoute(actions, []);

		expect(resolved?.action.id).toBe("land");
		expect(resolved?.commands).toEqual([]);
	});

	it("greedy-matches edit with a command id", () => {
		const resolved = resolveActionByRoute(actions, ["edit", "12"]);

		expect(resolved?.action.id).toBe("edit");
		expect(resolved?.commands).toEqual(["12"]);
	});
});

describe("visibleModuleActions", () => {
	it("includes only runnable in-nav actions and omits server PHP actions", () => {
		const actions = [
			action({ id: "1", name: "List", route: "", view: "v", in_nav: true }),
			action({
				id: "2",
				name: "Legacy PHP",
				route: "legacy",
				render: "server",
				in_nav: true,
			}),
			action({ id: "3", name: "Add", route: "add", form: "f", in_nav: true }),
			action({
				id: "4",
				name: "Hidden",
				route: "hidden",
				form: "f2",
				in_nav: false,
			}),
		];

		const items = visibleModuleActions(module, actions, 2);

		expect(items.map((i) => i.label)).toEqual(["List", "Add"]);
		expect(items.every((i) => !i.external)).toBe(true);
		expect(items[0]?.to).toBe(moduleActionPath(module, { route: "" }));
		// Landing route is a prefix of every deeper path — must exact-match.
		expect(items[0]?.end).toBe(true);
		expect(items[1]?.end).toBe(false);
	});

	it("respects user level", () => {
		const actions = [
			action({ id: "1", name: "Normal", route: "a", form: "f", level: 0, in_nav: true }),
			action({ id: "2", name: "Dev", route: "b", form: "f", level: 2, in_nav: true }),
		];

		expect(visibleModuleActions(module, actions, 0).map((i) => i.label)).toEqual(["Normal"]);
	});
});
