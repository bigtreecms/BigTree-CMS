import { describe, expect, it } from "vitest";

import { render, screen } from "@testing-library/react";

import { CardFooter } from "@/components/ui/Card";

describe("CardFooter", () => {
	it("pins a sticky footer above TinyMCE toolbar stacking", () => {
		render(<CardFooter sticky>Save</CardFooter>);

		const footer = screen.getByText("Save");

		expect(footer.className).toContain("sticky");
		expect(footer.className).toContain("bottom-0");
		// TinyMCE's `.tox-editor-header` is z-index: 2; without this the toolbar
		// paints over Cancel / Save when the field scrolls under the bar.
		expect(footer.className).toContain("z-10");
	});

	it("does not pin or raise stacking when sticky is omitted", () => {
		render(<CardFooter>Save</CardFooter>);

		const footer = screen.getByText("Save");

		expect(footer.className).not.toContain("sticky");
		expect(footer.className).not.toContain("z-10");
	});
});
