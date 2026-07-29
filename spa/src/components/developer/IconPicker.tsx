import { FieldLabel } from "@/components/ui/Field";
import { useModuleIcons } from "@/hooks/useModuleIcons";
import { iconFor } from "@/lib/legacyIcons";

import { IconGridButton } from "./IconGridButton";

interface IconPickerProps {
	hint?: string;
	label?: string;
	onChange: (slug: string) => void;
	/** Selected icon slug (empty string when none). */
	value: string;
}

/**
 * Visual module-icon picker — the SPA equivalent of the legacy module
 * designer's `developer_icon_list` glyph grid (modules/edit.php). Offers the
 * module-icon vocabulary (fetched from GET /module-icons, the one core source)
 * as a grid of buttons and stores the chosen slug. Glyphs are rendered through
 * `iconFor`'s Lucide map; the stored value is the legacy slug so the public
 * admin renders its own sprite.
 */
export const IconPicker = ({ value, onChange, label = "Icon", hint }: IconPickerProps) => {
	const slugs = useModuleIcons();

	return (
		<div>
			<FieldLabel>{label}</FieldLabel>
			<div className="flex flex-wrap gap-1.5 rounded-md border border-border bg-surface-2 p-2">
				{slugs.map((slug) => {
					const isActive = slug === value;

					return (
						<IconGridButton
							icon={iconFor(slug)}
							key={slug}
							label={slug}
							selected={isActive}
							onSelect={() => onChange(isActive ? "" : slug)}
						/>
					);
				})}
			</div>
			{hint && <span className="mt-1 block text-[11px] text-text-3">{hint}</span>}
		</div>
	);
};
