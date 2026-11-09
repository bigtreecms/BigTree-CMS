import { renderHook } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { useDirtyTracker } from "@/hooks/useDirtyTracker";

interface Props {
	ready: boolean;
	resetKey?: number;
	value: unknown;
}

describe("useDirtyTracker", () => {
	it("baselines on the first ready render and compares structurally", () => {
		const { result, rerender } = renderHook(
			(props: Props) => useDirtyTracker(props.value, props.ready, props.resetKey),
			{ initialProps: { value: null, ready: false } as Props }
		);

		expect(result.current).toBe(false);
		rerender({ value: { a: 1 }, ready: true });
		expect(result.current).toBe(false);
		rerender({ value: { a: 2 }, ready: true });
		expect(result.current).toBe(true);
		rerender({ value: { a: 1 }, ready: true });
		expect(result.current).toBe(false);
	});

	it("treats CRLF and LF line endings as the same text", () => {
		const { result, rerender } = renderHook(
			(props: Props) => useDirtyTracker(props.value, props.ready, props.resetKey),
			{ initialProps: { value: { html: "<p>a</p>\r\n<p>b</p>" }, ready: true } as Props }
		);

		rerender({ value: { html: "<p>a</p>\n<p>b</p>" }, ready: true });
		expect(result.current).toBe(false);
		rerender({ value: { html: "<p>a</p>\n<p>c</p>" }, ready: true });
		expect(result.current).toBe(true);
	});

	it("re-baselines when the reset key changes", () => {
		const { result, rerender } = renderHook(
			(props: Props) => useDirtyTracker(props.value, props.ready, props.resetKey),
			{ initialProps: { value: { a: 1 }, ready: true, resetKey: 1 } as Props }
		);

		rerender({ value: { a: 2 }, ready: true, resetKey: 1 });
		expect(result.current).toBe(true);
		rerender({ value: { a: 2 }, ready: true, resetKey: 2 });
		expect(result.current).toBe(false);
		rerender({ value: { a: 3 }, ready: true, resetKey: 2 });
		expect(result.current).toBe(true);
	});
});
