import { describe, expect, it, vi } from "vitest";

import { act, renderHook } from "@testing-library/react";
import { useState } from "react";

import { useListEditor } from "@/hooks/useListEditor";

interface Row {
	id: string;
	title: string;
}

/** Drive the hook the way an editor does: `value` in state, `onChange` writes back. */
const useControlledList = <T,>(initial: T[]) => {
	const [value, setValue] = useState<T[]>(initial);
	const editor = useListEditor(value, setValue);

	return { editor, value, setValue };
};

describe("useListEditor", () => {
	it("shallow-merges a patch into the row at index without touching others", () => {
		const { result } = renderHook(() =>
			useControlledList<Row>([
				{ id: "a", title: "A" },
				{ id: "b", title: "B" },
			])
		);

		act(() => result.current.editor.update(1, { title: "B2" }));

		expect(result.current.value).toEqual([
			{ id: "a", title: "A" },
			{ id: "b", title: "B2" },
		]);
	});

	it("replaces the whole item at index (primitive rows)", () => {
		const { result } = renderHook(() => useControlledList<string>(["x", "y", "z"]));

		act(() => result.current.editor.replace(1, "Y"));

		expect(result.current.value).toEqual(["x", "Y", "z"]);
	});

	it("removes the item at index", () => {
		const { result } = renderHook(() => useControlledList<string>(["x", "y", "z"]));

		act(() => result.current.editor.remove(0));

		expect(result.current.value).toEqual(["y", "z"]);
	});

	it("appends an item", () => {
		const { result } = renderHook(() => useControlledList<string>(["x"]));

		act(() => result.current.editor.add("y"));

		expect(result.current.value).toEqual(["x", "y"]);
	});

	it("reorders an item from one index to another", () => {
		const { result } = renderHook(() => useControlledList<string>(["a", "b", "c", "d"]));

		act(() => result.current.editor.move(0, 2));

		expect(result.current.value).toEqual(["b", "c", "a", "d"]);
	});

	it("moves adjacent items like the old swap (move up / down)", () => {
		const { result } = renderHook(() => useControlledList<string>(["a", "b", "c"]));

		act(() => result.current.editor.move(2, 1));

		expect(result.current.value).toEqual(["a", "c", "b"]);
	});

	it("no-ops when a move target is out of range", () => {
		const onChange = vi.fn();
		const { result } = renderHook(() => useListEditor(["a", "b"], onChange));

		act(() => result.current.move(0, -1));
		act(() => result.current.move(1, 2));
		act(() => result.current.move(0, 0));

		expect(onChange).not.toHaveBeenCalled();
	});
});
