import { describe, expect, it, vi } from "vitest";

import { renderHook } from "@testing-library/react";

import { useLatestUpload } from "@/hooks/useLatestUpload";
import type { UploadItem } from "@/hooks/useUploads";

const item = (
	id: number,
	status: UploadItem["status"],
	extra: Partial<UploadItem> = {}
): UploadItem => ({
	id,
	file: new File([], `f${id}`),
	progress: status === "done" ? 100 : 0,
	status,
	...extra,
});

describe("useLatestUpload", () => {
	it("hands a completed upload to onDone exactly once", () => {
		const onDone = vi.fn();
		const { rerender } = renderHook(({ items }) => useLatestUpload(items, { onDone }), {
			initialProps: { items: [item(1, "uploading")] },
		});

		expect(onDone).not.toHaveBeenCalled();

		const done = [item(1, "done", { result: { file: "a.jpg" } })];
		rerender({ items: done });

		expect(onDone).toHaveBeenCalledTimes(1);
		expect(onDone.mock.calls[0]![0].id).toBe(1);

		// A later, unrelated re-render with the same queue must not re-fire.
		rerender({ items: [...done] });

		expect(onDone).toHaveBeenCalledTimes(1);
	});

	it("drains items in id order, one per completion", () => {
		const seen: number[] = [];
		const onDone = vi.fn((it: UploadItem) => seen.push(it.id));
		const { rerender } = renderHook(({ items }) => useLatestUpload(items, { onDone }), {
			initialProps: { items: [item(1, "done")] },
		});

		rerender({ items: [item(1, "done"), item(2, "done")] });
		rerender({ items: [item(1, "done"), item(2, "done"), item(3, "done")] });

		expect(seen).toEqual([1, 2, 3]);
	});

	it("routes failures to onError and marks them handled", () => {
		const onDone = vi.fn();
		const onError = vi.fn();
		const { rerender } = renderHook(
			({ items }) => useLatestUpload(items, { onDone, onError }),
			{
				initialProps: { items: [item(1, "uploading")] },
			}
		);

		rerender({ items: [item(1, "error", { error: "boom" })] });

		expect(onError).toHaveBeenCalledTimes(1);
		expect(onDone).not.toHaveBeenCalled();

		rerender({ items: [item(1, "error", { error: "boom" })] });

		expect(onError).toHaveBeenCalledTimes(1);
	});

	it("leaves errors untouched when no onError handler is supplied", () => {
		const onDone = vi.fn();
		const { rerender, result } = renderHook(({ items }) => useLatestUpload(items, { onDone }), {
			initialProps: { items: [item(1, "uploading")] },
		});

		rerender({ items: [item(1, "error", { error: "boom" })] });

		expect(onDone).not.toHaveBeenCalled();

		// A later success still fires: the errored item never advanced the cursor.
		rerender({ items: [item(1, "error"), item(2, "done")] });

		expect(onDone).toHaveBeenCalledTimes(1);
		expect(onDone.mock.calls[0]![0].id).toBe(2);
		expect(result.current.inFlight).toBeUndefined();
	});

	it("reports the in-flight upload until it finishes", () => {
		const { rerender, result } = renderHook(
			({ items }) => useLatestUpload(items, { onDone: vi.fn() }),
			{
				initialProps: { items: [item(1, "uploading", { progress: 40 })] },
			}
		);

		expect(result.current.inFlight?.id).toBe(1);

		rerender({ items: [item(1, "done")] });

		expect(result.current.inFlight).toBeUndefined();
	});
});
