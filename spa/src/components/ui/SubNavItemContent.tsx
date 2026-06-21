import type { ReactNode } from "react";

/**
 * Shared visual layer for the two sub-nav variants. `ui/SubNav` (the controlled
 * segmented pill) and `shell/SubNav` (the route-driven underline tab bar with
 * overflow menu) are mechanically different and keep separate APIs by design
 * (finding F4 in the style-consistency plan) — but both lay an item out the same
 * way: an optional leading icon, the label in a `<span>`, and an optional
 * trailing adornment (the external-link glyph). Centralising that ordering here
 * is the single shared visual atom that stops the two from drifting; it appears
 * 5× across both files.
 *
 * The wrapping element (button / anchor / NavLink) and its variant-specific
 * classes stay with each SubNav — this owns only the inner content and ordering.
 * Each variant pre-renders its own icon node so it controls the size (ui: 13,
 * shell: 14) and any layout class (e.g. `shrink-0` inside the "More" menu).
 */
export interface SubNavItemContentProps {
	icon?: ReactNode;
	label: string;
	trailing?: ReactNode;
}

export const SubNavItemContent = ({ icon, label, trailing }: SubNavItemContentProps) => (
	<>
		{icon}
		<span>{label}</span>
		{trailing}
	</>
);
