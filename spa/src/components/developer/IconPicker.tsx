import { iconFor, MODULE_ICON_SLUGS } from "@/lib/legacyIcons";

import { IconGridButton } from "./IconGridButton";

interface IconPickerProps {
	/** Selected icon slug (empty string when none). */
	value: string;
	onChange: (slug: string) => void;
	label?: string;
	hint?: string;
}

/**
 * Visual module-icon picker — the SPA equivalent of the legacy module
 * designer's `developer_icon_list` glyph grid (modules/edit.php). Offers the
 * full `BigTreeAdmin::$IconClasses` vocabulary as a grid of buttons and stores
 * the chosen slug. Glyphs are rendered through `iconFor`'s Lucide map; the
 * stored value is the legacy slug so the public admin renders its own sprite.
 */
export const IconPicker = ({ value, onChange, label = "Icon", hint }: IconPickerProps) => (
	<div>
		<span className="mb-1 block text-[12px] font-medium text-text-2">{label}</span>
		<div className="flex flex-wrap gap-1.5 rounded-md border border-border bg-surface-2 p-2">
			{MODULE_ICON_SLUGS.map((slug) => {
				const isActive = slug === value;

				return (
					<IconGridButton
						key={slug}
						icon={iconFor(slug)}
						selected={isActive}
						onSelect={() => onChange(isActive ? "" : slug)}
						label={slug}
					/>
				);
			})}
		</div>
		{hint && <span className="mt-1 block text-[11px] text-text-3">{hint}</span>}
	</div>
);
