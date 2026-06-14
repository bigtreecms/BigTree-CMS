import { describe, expect, it, vi } from "vitest";

import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import { Pager } from "@/components/ui/Pager";

describe("Pager", () => {
	it("renders nothing when totalPages is 1", () => {
		const { container } = render(<Pager page={1} totalPages={1} onChange={vi.fn()} />);

		expect(container.querySelectorAll("button")).toHaveLength(0);
	});

	it("renders nothing when totalPages is 0", () => {
		const { container } = render(<Pager page={1} totalPages={0} onChange={vi.fn()} />);

		expect(container.querySelectorAll("button")).toHaveLength(0);
	});

	it("renders one button per page when totalPages <= 7", () => {
		render(<Pager page={1} totalPages={5} onChange={vi.fn()} />);

		for (let i = 1; i <= 5; i++) {
			expect(screen.getByRole("button", { name: String(i) })).toBeInTheDocument();
		}
	});

	it("calls onChange with the correct page when a numbered button is clicked", async () => {
		const onChange = vi.fn();
		const user = userEvent.setup();

		render(<Pager page={1} totalPages={5} onChange={onChange} />);

		await user.click(screen.getByRole("button", { name: "3" }));

		expect(onChange).toHaveBeenCalledTimes(1);
		expect(onChange).toHaveBeenCalledWith(3);
	});

	it("renders ellipsis and boundary buttons in windowed mode", () => {
		const { container } = render(<Pager page={5} totalPages={20} onChange={vi.fn()} />);

		expect(screen.getByRole("button", { name: "1" })).toBeInTheDocument();
		expect(screen.getByRole("button", { name: "20" })).toBeInTheDocument();

		// The component renders "…" (U+2026) as a <span>, not a button
		const ellipses = container.querySelectorAll("span");

		expect(ellipses.length).toBeGreaterThan(0);
	});
});
