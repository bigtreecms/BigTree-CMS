import type { ReactNode } from "react";

export type IconTileTone = "accent" | "neutral" | "info" | "warn" | "brand";
export type IconTileSize = "xs" | "sm" | "md" | "lg";
export type IconTileRadius = "md" | "lg" | "full";

const TONE_CLASS: Record<IconTileTone, string> = {
	accent: "bg-accent-soft text-accent",
	neutral: "bg-surface-2 text-text-3",
	info: "bg-info-bg text-info",
	warn: "bg-warn-bg text-warn",
	brand: "bg-accent text-accent-fg",
};

const SIZE_CLASS: Record<IconTileSize, string> = {
	xs: "size-[26px]",
	sm: "size-7",
	md: "size-9",
	lg: "size-10",
};

const RADIUS_CLASS: Record<IconTileRadius, string> = {
	md: "rounded-md",
	lg: "rounded-lg",
	full: "rounded-full",
};

interface IconTileProps {
	/** The icon node — the caller sizes it (e.g. `<Database size={18} />`). */
	children: ReactNode;
	/** Token-mapped tint. Defaults to `accent` (the soft-accent list/step chip). */
	tone?: IconTileTone;
	/** Box size: `xs` (26px), `sm` (size-7), `md` (size-9, default), `lg` (size-10). */
	size?: IconTileSize;
	/** Corner radius: `md` (default), `lg` (wizard step tiles), `full` (round). */
	radius?: IconTileRadius;
	/** Add a `ring-1 ring-border` outline (the file/thumbnail treatment). */
	ringed?: boolean;
	/** Layout-only classes the caller still owns (`shrink-0`, `mx-auto mb-3`, …). */
	className?: string;
}

/**
 * The small square "icon chip" — a centered grid box tinting a leading icon in
 * empty states, wizard steps, list-row slots and section headers. The single
 * source of truth for `grid place-items-center` + a token tint, which used to be
 * hand-rolled (with size/radius/tone drift) across ~9 files. Token-driven so the
 * tint reads correctly in light + dark mode.
 */
export const IconTile = ({
	children,
	tone = "accent",
	size = "md",
	radius = "md",
	ringed = false,
	className,
}: IconTileProps) => {
	const ring = ringed ? " ring-1 ring-border" : "";
	const extra = className ? ` ${className}` : "";

	return (
		<span
			className={`grid place-items-center ${SIZE_CLASS[size]} ${RADIUS_CLASS[radius]} ${TONE_CLASS[tone]}${ring}${extra}`}
		>
			{children}
		</span>
	);
};
