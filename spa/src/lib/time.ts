/**
 * Relative time formatter — outputs strings like the prototype's "2 hrs ago",
 * "Yesterday", "Apr 11". Mirrors the style used in the design fixtures.
 */
export function relativeTime(input: string | Date | null | undefined): string {
	if (!input) return "";
	const d =
		typeof input === "string" ? new Date(input.replace(" ", "T")) : input;
	if (Number.isNaN(d.getTime())) return "";

	const now = new Date();
	const diffMs = now.getTime() - d.getTime();
	const seconds = Math.max(0, Math.floor(diffMs / 1000));
	const minutes = Math.floor(seconds / 60);
	const hours = Math.floor(minutes / 60);
	const days = Math.floor(hours / 24);

	if (seconds < 60) return "Just now";
	if (minutes < 60) return `${minutes} min${minutes === 1 ? "" : "s"} ago`;
	if (hours < 24) return `${hours} hr${hours === 1 ? "" : "s"} ago`;
	if (days === 1) return "Yesterday";
	if (days < 7) return `${days} days ago`;

	// Older than a week: just show month + day. Older than a year: include year.
	const sameYear = now.getFullYear() === d.getFullYear();
	const opts: Intl.DateTimeFormatOptions = sameYear
		? { month: "short", day: "numeric" }
		: { month: "short", day: "numeric", year: "numeric" };
	return d.toLocaleDateString(undefined, opts);
}
