import { describe, expect, it } from "vitest";

import { derivePagination } from "@/lib/pagination";

describe("derivePagination", () => {
	describe("client-computed pages (perPage passed)", () => {
		it("computes totalPages from total / perPage", () => {
			const result = derivePagination({
				rows: [1, 2, 3],
				meta: { total: 45 },
				page: 2,
				perPage: 20,
			});

			expect(result.totalPages).toBe(3);
			expect(result.total).toBe(45);
			expect(result.safePage).toBe(2);
		});

		it("clamps safePage to totalPages", () => {
			const result = derivePagination({
				rows: [1, 2],
				meta: { total: 25 },
				page: 9,
				perPage: 20,
			});

			expect(result.totalPages).toBe(2);
			expect(result.safePage).toBe(2);
		});

		it("falls back to rows.length when meta.total is absent", () => {
			const result = derivePagination({
				rows: ["a", "b", "c"],
				page: 1,
				perPage: 20,
			});

			expect(result.total).toBe(3);
			expect(result.totalPages).toBe(1);
		});
	});

	describe("server-provided pages (perPage omitted)", () => {
		it("takes totalPages from meta.pages", () => {
			const result = derivePagination({
				rows: [1, 2, 3],
				meta: { total: 200, pages: 10 },
				page: 4,
			});

			expect(result.totalPages).toBe(10);
			expect(result.total).toBe(200);
			expect(result.safePage).toBe(4);
		});

		it("defaults totalPages to 1 when meta.pages is absent", () => {
			const result = derivePagination({
				rows: [1],
				meta: { total: 1 },
				page: 1,
			});

			expect(result.totalPages).toBe(1);
			expect(result.safePage).toBe(1);
		});
	});

	describe("empty data", () => {
		it("returns empty rows and a single page", () => {
			const result = derivePagination<number>({
				rows: undefined,
				page: 1,
				perPage: 20,
			});

			expect(result.rows).toEqual([]);
			expect(result.total).toBe(0);
			expect(result.totalPages).toBe(1);
			expect(result.safePage).toBe(1);
		});
	});
});
