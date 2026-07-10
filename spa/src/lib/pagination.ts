/**
 * Derive the pagination values a server-paginated list page feeds to `<Pager>`
 * from its list response. Callers pass their own data key explicitly (some
 * endpoints return `.items`, others `.data`); this util does not guess it.
 *
 * Two shapes are supported, selected by whether `perPage` is passed:
 *   - client-computed pages: pass `perPage` and `totalPages` is
 *     `Math.ceil(total / perPage)`;
 *   - server-provided pages: omit `perPage` and `totalPages` comes from
 *     `meta.pages`.
 *
 * `total` falls back to the row count when the server omits `meta.total`, and
 * `safePage` clamps the requested page to the available range.
 */
export function derivePagination<T>(args: {
	rows: T[] | undefined;
	meta?: { total?: number; pages?: number };
	page: number;
	perPage?: number;
}): { rows: T[]; total: number; totalPages: number; safePage: number } {
	const rows = args.rows ?? [];
	const total = args.meta?.total ?? rows.length;
	const totalPages =
		args.perPage !== undefined
			? Math.max(1, Math.ceil(total / args.perPage))
			: Math.max(1, args.meta?.pages ?? 1);
	const safePage = Math.min(args.page, totalPages);

	return { rows, total, totalPages, safePage };
}
