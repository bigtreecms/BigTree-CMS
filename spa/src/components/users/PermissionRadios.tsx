import type { PermissionCode } from "@/api/endpoints/users";

interface PermissionOption {
	value: PermissionCode;
	label: string;
}

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
					<label
						key={opt.value}
						className="flex items-center justify-center"
						title={opt.label}
					>
						<input
							type="radio"
							name={name}
							value={opt.value}
							checked={checked}
							disabled={disabled}
							onChange={() => onChange(opt.value)}
							className="h-3.5 w-3.5 cursor-pointer accent-accent disabled:cursor-not-allowed disabled:opacity-50"
						/>
					</label>
				);
			})}
		</>
	);
};

export const PAGE_PERMISSION_OPTIONS: PermissionOption[] = [
	{ value: "p", label: "Publisher" },
	{ value: "e", label: "Editor" },
	{ value: "n", label: "No Access" },
	{ value: "i", label: "Inherit" },
];

export const MODULE_PERMISSION_OPTIONS: PermissionOption[] = [
	{ value: "p", label: "Publisher" },
	{ value: "e", label: "Editor" },
	{ value: "n", label: "No Access" },
];

export const RESOURCE_PERMISSION_OPTIONS: PermissionOption[] = [
	{ value: "p", label: "Creator" },
	{ value: "e", label: "Consumer" },
	{ value: "n", label: "No Access" },
	{ value: "i", label: "Inherit" },
];
