import { describe, expect, it } from "vitest";
import { ArrowRight, Calendar, CalendarRange, Download, Eye, ListChecks } from "lucide-react";

import { iconForCustomAction } from "./viewHelpers";

describe("iconForCustomAction", () => {
	it("maps the built-in view-action classes", () => {
		expect(iconForCustomAction("icon_view")).toBe(Eye);
		expect(iconForCustomAction("icon_export")).toBe(Download);
	});

	it("maps Events Rules / Recurrences to distinct glyphs", () => {
		expect(iconForCustomAction("icon_settings")).toBe(ListChecks);
		expect(iconForCustomAction("icon_trail")).toBe(CalendarRange);
		expect(iconForCustomAction("icon_settings")).not.toBe(iconForCustomAction("icon_trail"));
	});

	it("falls through icon_* tokens to the module-icon vocabulary", () => {
		expect(iconForCustomAction("icon_calendar")).toBe(Calendar);
	});

	it("uses the first matching token when the class has several", () => {
		expect(iconForCustomAction("icon_preview icon_view")).toBe(Eye);
	});

	it("falls back to ArrowRight when nothing matches", () => {
		expect(iconForCustomAction(undefined)).toBe(ArrowRight);
		expect(iconForCustomAction("")).toBe(ArrowRight);
		expect(iconForCustomAction("icon_not_a_real_glyph")).toBe(ArrowRight);
	});
});
