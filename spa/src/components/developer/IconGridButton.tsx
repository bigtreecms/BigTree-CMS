import type { LucideIcon } from "lucide-react";

interface IconGridButtonProps {
	/** Glyph to render (from `iconFor`). */
	icon: LucideIcon;
	/** Whether this cell is the selected icon. */
	selected: boolean;
	onSelect: () => void;
	/** Slug used for the title + accessible name. */
	label: string;
}

/**
 * One selectable glyph cell in the module-icon grid — shared by the always-on
 * `IconPicker` and the popover `IconSelect` so the active/hover treatment lives
 * in one place.
 */
export const IconGridButton = ({ icon: Icon, selected, onSelect, label }: IconGridButtonProps) => (
	<button
		type="button"
		onClick={onSelect}
		title={label}
		aria-label={label}
		aria-pressed={selected}
		className={`grid size-8 place-items-center rounded-md border transition-colors ${
			selected
				? "border-accent bg-accent-soft text-accent"
				: "border-transparent text-text-2 hover:bg-hover hover:text-text"
		}`}
	>
		<Icon size={15} />
	</button>
);
