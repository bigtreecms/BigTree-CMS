import type { ControlProps } from "./types";
import { SectionLabel } from "@/components/ui/SectionLabel";

/** Section divider used to group settings (e.g. media-gallery's three blocks). */
export const HeadingControl = ({ descriptor }: ControlProps) => (
	<SectionLabel className="border-t border-border pt-3">
		{descriptor.heading ?? descriptor.label}
	</SectionLabel>
);
