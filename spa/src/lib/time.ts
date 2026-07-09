import { pluralize } from "./number";

/**
 * Relative time formatter — outputs strings like the prototype's "2 hrs ago",
 * "Yesterday", "Apr 11". Mirrors the style used in the design fixtures.
 */
export function relativeTime(input: string | Date | null | undefined): string {
	if (!input) return "";
	const d = typeof input === "string" ? new Date(input.replace(" ", "T")) : input;
	if (Number.isNaN(d.getTime())) return "";

	const now = new Date();
	const diffMs = now.getTime() - d.getTime();
	const seconds = Math.max(0, Math.floor(diffMs / 1000));
	const minutes = Math.floor(seconds / 60);
	const hours = Math.floor(minutes / 60);
	const days = Math.floor(hours / 24);

	if (seconds < 60) return "Just now";
	if (minutes < 60) return `${pluralize(minutes, "min")} ago`;
	if (hours < 24) return `${pluralize(hours, "hr")} ago`;
	if (days === 1) return "Yesterday";
	if (days < 7) return `${days} days ago`;

	// Older than a week: just show month + day. Older than a year: include year.
	const sameYear = now.getFullYear() === d.getFullYear();
	const opts: Intl.DateTimeFormatOptions = sameYear
		? { month: "short", day: "numeric" }
		: { month: "short", day: "numeric", year: "numeric" };
	return d.toLocaleDateString(undefined, opts);
}

/** Parse the app's ISO-ish date strings (`"2026-06-24 15:45:00"`) into a Date. */
function parseDate(input: string | Date | null | undefined): Date | null {
	if (!input) return null;
	const d = typeof input === "string" ? new Date(input.replace(" ", "T")) : input;

	return Number.isNaN(d.getTime()) ? null : d;
}

const DATE_OPTS: Intl.DateTimeFormatOptions = {
	year: "numeric",
	month: "short",
	day: "numeric",
};

const TIME_OPTS: Intl.DateTimeFormatOptions = {
	hour: "numeric",
	minute: "2-digit",
};

/**
 * Canonical absolute date formatter — "Jun 24, 2026". Pass `opts` to override
 * the option set for a one-off; falls back to the empty string on bad input.
 */
export function formatDate(
	input: string | Date | null | undefined,
	opts: Intl.DateTimeFormatOptions = DATE_OPTS
): string {
	const d = parseDate(input);

	if (!d) return "";

	return d.toLocaleDateString(undefined, opts);
}

/** Canonical date + time formatter — "Jun 24, 2026, 3:45 PM". */
export function formatDateTime(input: string | Date | null | undefined): string {
	const d = parseDate(input);

	if (!d) return "";

	return d.toLocaleString(undefined, { ...DATE_OPTS, ...TIME_OPTS });
}

/** Compact date — "6/24/26". Intended for dense table cells. */
export function formatShortDate(input: string | Date | null | undefined): string {
	const d = parseDate(input);

	if (!d) return "";

	return `${d.getMonth() + 1}/${d.getDate()}/${String(d.getFullYear()).slice(-2)}`;
}

/** Time-only formatter — "3:45 PM". */
export function formatTime(input: string | Date | null | undefined): string {
	const d = parseDate(input);

	if (!d) return "";

	return d.toLocaleTimeString(undefined, { hour: "numeric", minute: "2-digit" });
}

/**
 * Split a datetime into separate date ("6/24/26") and time ("3:45 PM") strings
 * for tables that render them in distinct columns.
 */
export function splitDateTime(input: string | Date | null | undefined): {
	date: string;
	time: string;
} {
	const d = parseDate(input);

	if (!d) return { date: typeof input === "string" ? input : "", time: "" };

	return { date: formatShortDate(d), time: formatTime(d) };
}
