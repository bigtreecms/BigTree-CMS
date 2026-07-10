import { describe, expect, it } from "vitest";

import { act, renderHook } from "@testing-library/react";

import { useToggleSet } from "@/hooks/useToggleSet";

describe("useToggleSet", () => {
	it("seeds from an initial iterable", () => {
		const { result } = renderHook(() => useToggleSet<string>(["a", "b"]));

		expect(result.current.has("a")).toBe(true);
		expect(result.current.has("c")).toBe(false);
	});

	it("toggle adds then removes a member", () => {
		const { result } = renderHook(() => useToggleSet<number>());

		act(() => result.current.toggle(1));
		expect(result.current.set.has(1)).toBe(true);

		act(() => result.current.toggle(1));
		expect(result.current.set.has(1)).toBe(false);
	});

	it("clear empties the set", () => {
		const { result } = renderHook(() => useToggleSet<string>(["a", "b"]));

		act(() => result.current.clear());

		expect(result.current.set.size).toBe(0);
	});

	it("setSet replaces the whole set (select-all)", () => {
		const { result } = renderHook(() => useToggleSet<number>());

		act(() => result.current.setSet(new Set([1, 2, 3])));

		expect(result.current.set.size).toBe(3);
	});
});
