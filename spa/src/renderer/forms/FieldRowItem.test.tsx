import type { ReactElement } from "react";
import { createMemoryRouter, RouterProvider } from "react-router-dom";
import { beforeAll, describe, expect, it, vi } from "vitest";

import { render as baseRender, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import type { ModuleForm } from "@/api/endpoints/modules";
import type { FieldComponentProps } from "@/renderer/fields/types";

import { FormRenderer } from "./FormRenderer";
import { registerFieldType } from "./fieldRegistry";

/**
 * FormRenderer mounts <UnsavedChangesGuard>, whose useBlocker requires a data
 * router. Wrap renders in an in-memory data router so the guard mounts cleanly.
 */
const renderWithRouter = (ui: ReactElement) => {
	const router = createMemoryRouter([{ path: "/", element: ui }], {
		initialEntries: ["/"],
	});

	return baseRender(<RouterProvider router={router} />);
};

/**
 * Per-column render counters. A spy field type records how many times each of
 * its instances renders, so the test can assert that typing in one field does
 * NOT re-render the others (the memoization win).
 */
const renderCounts: Record<string, number> = {};

const SpyField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	renderCounts[field.column] = (renderCounts[field.column] ?? 0) + 1;

	return (
		<input
			aria-label={field.column}
			value={typeof value === "string" ? value : ""}
			disabled={disabled}
			onChange={(event) => onChange(event.target.value)}
		/>
	);
};

beforeAll(() => {
	registerFieldType("spy-field", { render: "core-component", component: SpyField });
});

const makeForm = (): ModuleForm => ({
	id: "1",
	title: "Spy form",
	table: "spy",
	fields: [
		{ column: "a", title: "Field A", type: "spy-field" },
		{ column: "b", title: "Field B", type: "spy-field" },
		{ column: "c", title: "Field C", type: "spy-field" },
	],
});

describe("FormRenderer field render isolation", () => {
	it("re-renders only the typed field, not its siblings", async () => {
		for (const key of Object.keys(renderCounts)) {
			delete renderCounts[key];
		}

		const user = userEvent.setup();

		renderWithRouter(<FormRenderer form={makeForm()} onSubmit={vi.fn()} />);

		// Initial mount renders each field exactly once.
		expect(renderCounts.a).toBe(1);
		expect(renderCounts.b).toBe(1);
		expect(renderCounts.c).toBe(1);

		const before = {
			a: renderCounts.a ?? 0,
			b: renderCounts.b ?? 0,
			c: renderCounts.c ?? 0,
		};

		await user.type(screen.getByLabelText("a"), "hello");

		// Field A re-rendered for each keystroke...
		expect(renderCounts.a ?? 0).toBeGreaterThan(before.a);

		// ...but B and C did not re-render at all.
		expect(renderCounts.b).toBe(before.b);
		expect(renderCounts.c).toBe(before.c);

		expect(screen.getByLabelText("a")).toHaveValue("hello");
	});

	it("collects every field's value on submit", async () => {
		const onSubmit = vi.fn();
		const user = userEvent.setup();

		renderWithRouter(<FormRenderer form={makeForm()} onSubmit={onSubmit} submitLabel="Save" />);

		await user.type(screen.getByLabelText("a"), "one");
		await user.type(screen.getByLabelText("b"), "two");
		await user.type(screen.getByLabelText("c"), "three");

		await user.click(screen.getByRole("button", { name: "Save" }));

		expect(onSubmit).toHaveBeenCalledTimes(1);

		const submitted = onSubmit.mock.calls[0]?.[0] as Record<string, unknown>;

		expect(submitted).toMatchObject({ a: "one", b: "two", c: "three" });
	});
});
