/**
 * Locale-aware number formatter — adds thousands separators ("1,234,567") the
 * way `Number#toLocaleString` does, but as one shared helper so count rendering
 * doesn't drift across the app. Returns "—" for null/undefined/NaN so it drops
 * straight into a table cell; `0` formats as "0". Pass `opts` to override the
 * Intl options for a one-off.
 */
export const formatNumber = (n?: number | null, opts?: Intl.NumberFormatOptions): string => {
	if (n === null || n === undefined || Number.isNaN(n)) {
		return "—";
	}

	return n.toLocaleString(undefined, opts);
};
