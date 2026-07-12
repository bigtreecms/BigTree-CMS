import { describe, expect, it, vi } from "vitest";

import { act, renderHook } from "@testing-library/react";

import { useSeededState } from "@/hooks/useSeededState";

describe("useSeededState", () => {
	it("does not seed when data is undefined", () => {
		const seed = vi.fn();
		const { result } = renderHook(() => useSeededState(undefined, seed));

		expect(result.current).toBe(false);
		expect(seed).not.toHaveBeenCalled();
	});

	it("seeds once when data first arrives", () => {
		const seed = vi.fn();
		const { result, rerender } = renderHook(
			({ data }: { data: { n: number } | undefined }) => useSeededState(data, seed),
			{ initialProps: { data: undefined as { n: number } | undefined } }
		);

		expect(result.current).toBe(false);

		rerender({ data: { n: 1 } });

		expect(result.current).toBe(true);
		expect(seed).toHaveBeenCalledTimes(1);
		expect(seed).toHaveBeenCalledWith({ n: 1 });
	});

	it("does not re-seed when data changes after the first seed", () => {
		const seed = vi.fn();
		const { result, rerender } = renderHook(
			({ data }: { data: { n: number } | undefined }) => useSeededState(data, seed),
			{ initialProps: { data: { n: 1 } as { n: number } | undefined } }
		);

		expect(result.current).toBe(true);
		expect(seed).toHaveBeenCalledTimes(1);

		rerender({ data: { n: 2 } });

		expect(result.current).toBe(true);
		expect(seed).toHaveBeenCalledTimes(1);
		expect(seed).toHaveBeenCalledWith({ n: 1 });
	});

	it("uses the latest seed callback without re-running", () => {
		const first = vi.fn();
		const second = vi.fn();
		const { rerender } = renderHook(
			({ data, seed }: { data: number | undefined; seed: (d: number) => void }) =>
				useSeededState(data, seed),
			{ initialProps: { data: undefined as number | undefined, seed: first } }
		);

		rerender({ data: undefined, seed: second });
		rerender({ data: 7, seed: second });

		expect(first).not.toHaveBeenCalled();
		expect(second).toHaveBeenCalledTimes(1);
		expect(second).toHaveBeenCalledWith(7);
	});

	it("stays seeded across a refetch that replaces the data reference", () => {
		const values: number[] = [];
		const seed = (n: number) => {
			values.push(n);
		};

		const { rerender } = renderHook(
			({ data }: { data: number | undefined }) => useSeededState(data, seed),
			{ initialProps: { data: 1 as number | undefined } }
		);

		act(() => {
			rerender({ data: 99 });
		});

		expect(values).toEqual([1]);
	});
});
