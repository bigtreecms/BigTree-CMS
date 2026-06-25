import { useEffect, useRef, useState } from "react";
import { ChevronsUpDown, X } from "lucide-react";

import { iconFor, MODULE_ICON_SLUGS } from "@/lib/legacyIcons";

import { IconGridButton } from "./IconGridButton";

interface IconSelectProps {
	/** Selected icon slug (empty string when none). */
	value: string;
	onChange: (slug: string) => void;
	label?: string;
	hint?: string;
	placeholder?: string;
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
	const containerRef = useRef<HTMLDivElement>(null);

	// Close on outside click (mirrors Combobox).
	useEffect(() => {
		if (!open) {
			return;
		}

		const handler = (event: MouseEvent) => {
			if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
				setOpen(false);
			}
		};

		window.addEventListener("mousedown", handler);

		return () => window.removeEventListener("mousedown", handler);
	}, [open]);

	const Selected = value ? iconFor(value) : null;

	const clear = (event: React.MouseEvent) => {
		event.stopPropagation();
		onChange("");
	};

	return (
		<div>
			<span className="mb-1 block text-[12px] font-medium text-text-2">{label}</span>
			<div ref={containerRef} className="relative">
				<button
					type="button"
					aria-haspopup="listbox"
					aria-expanded={open}
					onClick={() => setOpen((prev) => !prev)}
					className="flex w-full items-center gap-2 rounded-md border border-border bg-surface px-3 py-1.5 text-left text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
				>
					{Selected ? <Selected size={15} className="shrink-0 text-text-2" /> : null}
					<span
						className={`min-w-0 flex-1 truncate ${value ? "text-text" : "text-text-3"}`}
					>
						{value || placeholder}
					</span>
					{value ? (
						<span
							role="button"
							tabIndex={-1}
							onClick={clear}
							aria-label="Clear icon"
							className="rounded p-0.5 text-text-3 hover:bg-hover hover:text-text"
						>
							<X size={13} />
						</span>
					) : (
						<ChevronsUpDown size={13} className="shrink-0 text-text-3" />
					)}
				</button>

				{open && (
					<div className="absolute z-20 mt-1 w-full overflow-hidden rounded-md border border-border bg-surface shadow-lg">
						<div className="flex max-h-56 flex-wrap gap-1.5 overflow-y-auto p-2">
							{MODULE_ICON_SLUGS.map((slug) => {
								const isActive = slug === value;

								return (
									<IconGridButton
										key={slug}
										icon={iconFor(slug)}
										selected={isActive}
										onSelect={() => {
											onChange(isActive ? "" : slug);
											setOpen(false);
										}}
										label={slug}
									/>
								);
							})}
						</div>
					</div>
				)}
			</div>
			{hint && <span className="mt-1 block text-[11px] text-text-3">{hint}</span>}
		</div>
	);
};
