import { useState } from "react";
import { ChevronsUpDown, X } from "lucide-react";

import { FieldLabel } from "@/components/ui/Field";
import { IconButton } from "@/components/ui/IconButton";
import { Popover } from "@/components/ui/Popover";

import { iconFor, MODULE_ICON_SLUGS } from "@/lib/legacyIcons";

import { IconGridButton } from "./IconGridButton";

interface IconSelectProps {
	hint?: string;
	label?: string;
	onChange: (slug: string) => void;
	placeholder?: string;
	/** Selected icon slug (empty string when none). */
	value: string;
}

/**
 * Dropdown variant of the module IconPicker — a button trigger showing the
 * chosen glyph, opening a popover grid of the full `BigTreeAdmin::$IconClasses`
 * vocabulary. Used where a full always-on grid is too heavy (e.g. the module
 * action editor). Stores the legacy slug like IconPicker so the public admin
 * renders its own sprite.
 */
export const IconSelect = ({
	value,
	onChange,
	label = "Icon",
	hint,
	placeholder = "Choose an icon…",
}: IconSelectProps) => {
	const [open, setOpen] = useState(false);

	const Selected = value ? iconFor(value) : null;

	const clear = (event: React.MouseEvent) => {
		event.stopPropagation();
		onChange("");
	};

	return (
		<div>
			<FieldLabel>{label}</FieldLabel>
			<Popover
				open={open}
				panelClassName="w-full overflow-hidden"
				trigger={
					<>
						<button
							aria-expanded={open}
							aria-haspopup="listbox"
							className="flex w-full items-center gap-2 rounded-md border border-border bg-surface py-1.5 pl-3 pr-9 text-left text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
							type="button"
							onClick={() => setOpen((prev) => !prev)}
						>
							{Selected ? (
								<Selected className="shrink-0 text-text-2" size={15} />
							) : null}
							<span
								className={`min-w-0 flex-1 truncate ${value ? "text-text" : "text-text-3"}`}
							>
								{value || placeholder}
							</span>
						</button>

						{/* Trailing control sits as a sibling, not nested inside the trigger
						    button — interactive controls must not be nested (a11y). */}
						{value ? (
							<IconButton
								className="absolute right-1.5 top-1/2 -translate-y-1/2"
								label="Clear icon"
								size="sm"
								onClick={clear}
							>
								<X size={13} />
							</IconButton>
						) : (
							<ChevronsUpDown
								className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-text-3"
								size={13}
							/>
						)}
					</>
				}
				onOpenChange={setOpen}
			>
				<div className="flex max-h-56 flex-wrap gap-1.5 overflow-y-auto p-2">
					{MODULE_ICON_SLUGS.map((slug) => {
						const isActive = slug === value;

						return (
							<IconGridButton
								icon={iconFor(slug)}
								key={slug}
								label={slug}
								selected={isActive}
								onSelect={() => {
									onChange(isActive ? "" : slug);
									setOpen(false);
								}}
							/>
						);
					})}
				</div>
			</Popover>
			{hint && <span className="mt-1 block text-[11px] text-text-3">{hint}</span>}
		</div>
	);
};
