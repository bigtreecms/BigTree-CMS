import { describe, expect, it } from "vitest";

import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import { FieldComparison } from "@/components/pending-changes/FieldComparison";

describe("FieldComparison", () => {
	it("diffs a text field instead of showing two columns", () => {
		render(
			<FieldComparison
				fieldType="textarea"
				pending="The quick red fox"
				pendingLabel="Your draft"
				published="The quick brown fox"
			/>
		);

		// No <pre> column blocks — the two-column fallback renders those.
		expect(document.querySelector("pre")).toBeNull();
		expect(document.querySelector("ins")).toHaveTextContent("red");
		expect(document.querySelector("del")).toHaveTextContent("brown");
	});

	it("keeps the side-by-side columns for a type with its own renderer", () => {
		render(
			<FieldComparison
				fieldType="image"
				pending="{wwwroot}files/b.jpg"
				published="{wwwroot}files/a.jpg"
			/>
		);

		expect(screen.getByText("Published")).toBeInTheDocument();
		expect(screen.getByText("Pending draft")).toBeInTheDocument();
	});

	it("keeps the columns for a never-published draft, where every word is new", () => {
		render(
			<FieldComparison isNew fieldType="text" pending="Brand new copy" published={null} />
		);

		expect(screen.getByText("No published version yet")).toBeInTheDocument();
	});

	it("offers a markup view for an HTML field and finds the change there", async () => {
		const user = userEvent.setup();

		render(
			<FieldComparison
				fieldType="html"
				pending='<p>Read <a href="/two">this</a></p>'
				published='<p>Read <a href="/one">this</a></p>'
			/>
		);

		// Only the href moved, so the text view has nothing to report.
		expect(screen.getByText(/switch to Markup/i)).toBeInTheDocument();

		await user.click(screen.getByRole("button", { name: "markup" }));

		expect(screen.queryByText(/switch to Markup/i)).not.toBeInTheDocument();
		expect(document.body.textContent).toContain("/two");
	});

	it("shows the draft owner in the diff heading", () => {
		render(
			<FieldComparison
				fieldType="text"
				pending="after"
				pendingLabel="Draft by Sam"
				published="before"
			/>
		);

		expect(screen.getByText("Draft by Sam")).toBeInTheDocument();
	});
});
