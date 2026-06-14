import { describe, expect, it, vi } from "vitest";

import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import { Chip } from "@/components/ui/Chip";

describe("Chip", () => {
	it("renders its children", () => {
		render(
			<Chip active={false} onClick={vi.fn()}>
				Hello
			</Chip>
		);

		expect(screen.getByText("Hello")).toBeInTheDocument();
	});

	it("reflects active=false via data-active attribute", () => {
		render(
			<Chip active={false} onClick={vi.fn()}>
				Inactive
			</Chip>
		);

		expect(screen.getByRole("button")).toHaveAttribute("data-active", "false");
	});

	it("reflects active=true via data-active attribute", () => {
		render(
			<Chip active={true} onClick={vi.fn()}>
				Active
			</Chip>
		);

		expect(screen.getByRole("button")).toHaveAttribute("data-active", "true");
	});

	it("calls onClick once when clicked", async () => {
		const onClick = vi.fn();
		const user = userEvent.setup();

		render(
			<Chip active={false} onClick={onClick}>
				Click me
			</Chip>
		);

		await user.click(screen.getByRole("button"));

		expect(onClick).toHaveBeenCalledTimes(1);
	});
});
