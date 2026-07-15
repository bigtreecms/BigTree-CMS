import { Radio } from "@/components/ui/Radio";

import type { PermissionCode } from "@/api/endpoints/users";
import type { PermissionOption } from "./permissionOptions";

interface PermissionRadiosProps {
	disabled?: boolean;
	name: string;
	onChange: (next: PermissionCode) => void;
	options: PermissionOption[];
	value: PermissionCode | undefined;
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
						ariaLabel={opt.label}
						checked={checked}
						className="justify-center"
						disabled={disabled}
						key={opt.value}
						name={name}
						size="sm"
						title={opt.label}
						value={opt.value}
						onChange={() => onChange(opt.value)}
					/>
				);
			})}
		</>
	);
};
