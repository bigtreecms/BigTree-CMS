import type { ControlProps } from "./types";

/** Section divider used to group settings (e.g. media-gallery's three blocks). */
export const HeadingControl = ({ descriptor }: ControlProps) => (
	<div className="border-t border-border pt-3 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
		{descriptor.heading ?? descriptor.label}
	</div>
);
