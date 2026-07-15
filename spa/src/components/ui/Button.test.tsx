import { describe, expect, it } from "vitest";

import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";

import { Button } from "@/components/ui/Button";

describe("Button", () => {
	it("renders its children", () => {
		render(<Button>Save</Button>);

		expect(screen.getByRole("button", { name: "Save" })).toBeInTheDocument();
	});

	it("renders a link when `to` is set and not disabled", () => {
		render(
			<MemoryRouter>
				<Button to="/pages">Pages</Button>
			</MemoryRouter>
		);

		expect(screen.getByRole("link", { name: "Pages" })).toHaveAttribute("href", "/pages");
	});

	it("renders a plain button (never a link) when loading, even with `to`", () => {
		render(
			<MemoryRouter>
				<Button loading loadingLabel="Saving…" to="/pages">
					Save
				</Button>
			</MemoryRouter>
		);

		expect(screen.queryByRole("link")).not.toBeInTheDocument();
		expect(screen.getByRole("button")).toBeInTheDocument();
	});

	it("is disabled and shows the loading label + spinner while loading", () => {
		const { container } = render(
			<Button loading loadingLabel="Saving…">
				Save
			</Button>
		);

		const button = screen.getByRole("button");

		expect(button).toBeDisabled();
		expect(button).toHaveTextContent("Saving…");
		expect(button).not.toHaveTextContent("Save");
		expect(container.querySelector("svg.animate-spin")).toBeInTheDocument();
	});

	it("falls back to children when loading without a loadingLabel", () => {
		render(<Button loading>Save</Button>);

		expect(screen.getByRole("button")).toHaveTextContent("Save");
	});
});
