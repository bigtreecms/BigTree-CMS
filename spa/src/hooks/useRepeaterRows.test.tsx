import { describe, expect, it, vi } from "vitest";

import { act, renderHook } from "@testing-library/react";
import { useState } from "react";

import { useRepeaterRows } from "@/hooks/useRepeaterRows";

interface Row extends Record<string, unknown> {
	label: string;
}

/**
 * Drive the hook the way a field does: `value` lives in state and `onChange`
 * writes back to it, so our own edits echo as structurally-equal values (the
 * re-seed guard keeps them) rather than reverting. Tests that want to simulate
 * an external change call `setValue` directly.
 */
const useControlledRepeater = (initial: Row[], max?: number) => {
	const [value, setValue] = useState<unknown>(initial);
	const repeater = useRepeaterRows<Row>({
		value,
		onChange: setValue,
		uidPrefix: "t",
		max,
	});

	return { repeater, value, setValue };
};

describe("useRepeaterRows", () => {
	it("seeds rows from the initial value with distinct uids", () => {
		const { result } = renderHook(() =>
			useControlledRepeater([{ label: "a" }, { label: "b" }])
		);

		expect(result.current.repeater.rows.map((r) => r.data.label)).toEqual(["a", "b"]);
		expect(result.current.repeater.rows[0]?.uid).not.toEqual(
			result.current.repeater.rows[1]?.uid
		);
	});

	it("ignores non-array values", () => {
		const { result } = renderHook(() =>
			useRepeaterRows<Row>({ value: null, onChange: vi.fn(), uidPrefix: "t" })
		);

		expect(result.current.rows).toEqual([]);
	});

	it("adds, auto-expands, and echoes the stripped payload back into value", () => {
		const { result } = renderHook(() => useControlledRepeater([]));

		act(() => result.current.repeater.add({ label: "new" }));

		expect(result.current.repeater.rows).toHaveLength(1);
		expect(result.current.repeater.isExpanded(result.current.repeater.rows[0]!.uid)).toBe(true);
		expect(result.current.value).toEqual([{ label: "new" }]);
	});

	it("honours the max limit", () => {
		const { result } = renderHook(() => useControlledRepeater([{ label: "a" }], 1));

		expect(result.current.repeater.atLimit).toBe(true);

		act(() => result.current.repeater.add({ label: "b" }));

		expect(result.current.repeater.rows).toHaveLength(1);
		expect(result.current.value).toEqual([{ label: "a" }]);
	});

	it("updates a row by shallow merge without touching others", () => {
		const { result } = renderHook(() =>
			useControlledRepeater([{ label: "a" }, { label: "b" }])
		);

		const secondUid = result.current.repeater.rows[1]!.uid;

		act(() => result.current.repeater.update(secondUid, { label: "B" }));

		expect(result.current.repeater.rows.map((r) => r.data.label)).toEqual(["a", "B"]);
	});

	it("removes a row and drops its expand state", () => {
		const { result } = renderHook(() =>
			useControlledRepeater([{ label: "a" }, { label: "b" }])
		);

		const firstUid = result.current.repeater.rows[0]!.uid;

		act(() => result.current.repeater.remove(firstUid));

		expect(result.current.repeater.rows.map((r) => r.data.label)).toEqual(["b"]);
		expect(result.current.repeater.isExpanded(firstUid)).toBe(false);
	});

	it("reorders rows with move, preserving identity", () => {
		const { result } = renderHook(() =>
			useControlledRepeater([{ label: "a" }, { label: "b" }])
		);

		const firstUid = result.current.repeater.rows[0]!.uid;

		act(() => result.current.repeater.move(0, "down"));

		expect(result.current.repeater.rows.map((r) => r.data.label)).toEqual(["b", "a"]);
		expect(result.current.repeater.rows[1]?.uid).toBe(firstUid);
	});

	it("keeps row identity when re-seeded with structurally-equal data", () => {
		const { result, rerender } = renderHook(
			({ value }: { value: unknown }) =>
				useRepeaterRows<Row>({ value, onChange: vi.fn(), uidPrefix: "t" }),
			{ initialProps: { value: [{ label: "a" }] as unknown } }
		);

		const originalUid = result.current.rows[0]!.uid;

		// The echo of our own onChange — a fresh array with identical data.
		rerender({ value: [{ label: "a" }] });

		expect(result.current.rows[0]?.uid).toBe(originalUid);
	});

	it("replaces rows when re-seeded with genuinely different data", () => {
		const { result, rerender } = renderHook(
			({ value }: { value: unknown }) =>
				useRepeaterRows<Row>({ value, onChange: vi.fn(), uidPrefix: "t" }),
			{ initialProps: { value: [{ label: "a" }] as unknown } }
		);

		const originalUid = result.current.rows[0]!.uid;

		rerender({ value: [{ label: "changed" }] });

		expect(result.current.rows.map((r) => r.data.label)).toEqual(["changed"]);
		expect(result.current.rows[0]?.uid).not.toBe(originalUid);
	});
});
