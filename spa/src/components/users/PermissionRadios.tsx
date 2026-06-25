import { Radio } from "@/components/ui/Radio";

import type { PermissionCode } from "@/api/endpoints/users";
import type { PermissionOption } from "./permissionOptions";

interface PermissionRadiosProps {
	name: string;
	value: PermissionCode | undefined;
	onChange: (next: PermissionCode) => void;
	options: PermissionOption[];
	disabled?: boolean;
}

/**
 * Single row of permission radios. Used by the Page / Module / Resource trees.
 *
 * The PHP admin renders each radio in its own column inside a grid so the
 * radios stack vertically across rows. We do the same with a parent grid
 * declared in the tree component — here we just emit the inputs and let the
 * grid position them.
 */
export const PermissionRadios = ({
	name,
	value,
	onChange,
	options,
	disabled,
}: PermissionRadiosProps) => {
	const current = value ?? "";

	return (
		<>
			{options.map((opt) => {
				const checked = current === opt.value || (opt.value === "i" && current === "");

				return (
					<Radio
						key={opt.value}
						name={name}
						value={opt.value}
						size="sm"
						className="justify-center"
						title={opt.label}
						ariaLabel={opt.label}
						checked={checked}
						disabled={disabled}
						onChange={() => onChange(opt.value)}
					/>
				);
			})}
		</>
	);
};
