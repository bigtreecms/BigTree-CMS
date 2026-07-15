import { Badge, type BadgeTone } from "@/components/ui/Badge";

/**
 * Tone → label-text color, mirroring `Badge`'s tone tokens for the `text`
 * variant (an inline uppercase colored label instead of a filled pill).
 */
const TONE_TEXT_CLASS: Record<BadgeTone, string> = {
	neutral: "text-text-3",
	accent: "text-accent",
	success: "text-success",
	warn: "text-warn",
	danger: "text-danger",
	info: "text-info",
};

interface StatusBadgeProps {
	className?: string;
	/** `badge` variant only: render the leading colored dot. */
	dot?: boolean;
	/** Human-readable status label. */
	label: string;
	/**
	 * `text` variant only: read as plain value text on mobile and only take on
	 * the uppercase label styling at `md+`. Used inside responsive tables that
	 * stack each cell under its own (also uppercase) column label on mobile.
	 */
	plainOnMobile?: boolean;
	/** Token-mapped tone — the caller maps its domain status onto this. */
	tone: BadgeTone;
	/**
	 * `badge` (default) renders a filled `Badge` pill; `text` renders an inline
	 * uppercase colored label (the list-view "Status" cell treatment).
	 */
	variant?: "badge" | "text";
}

/**
 * The single home for the status → tone → color mapping. Renders a domain status
 * as either a filled pill (`badge`) or an inline colored label (`text`); callers
 * supply a thin status → { tone, label } map and pick the variant their surface
 * needs (page status, file-usage status, list-view entry status).
 */
export const StatusBadge = ({
	tone,
	label,
	variant = "badge",
	dot = false,
	plainOnMobile = false,
	className = "",
}: StatusBadgeProps) => {
	if (variant === "text") {
		const styleClasses = plainOnMobile
			? "text-[13px] md:text-[11px] md:font-semibold md:uppercase md:tracking-[0.06em]"
			: "text-[11px] font-semibold uppercase tracking-[0.06em]";

		return (
			<span
				className={`whitespace-nowrap ${styleClasses} ${TONE_TEXT_CLASS[tone]} ${className}`}
			>
				{label}
			</span>
		);
	}

	return (
		<Badge className={className} dot={dot} tone={tone}>
			{label}
		</Badge>
	);
};
