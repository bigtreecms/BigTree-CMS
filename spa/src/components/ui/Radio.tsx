import { type ReactNode } from "react";

interface RadioProps {
	/** Top-align the dot for labels that wrap to multiple lines. Default `center`. */
	align?: "center" | "start";
	/** Accessible name when there is no visible `label`. */
	ariaLabel?: string;
	checked: boolean;
	/** Layout-only classes appended to the wrapping `<label>` (e.g. grid centering). */
	className?: string;
	disabled?: boolean;
	/**
	 * Visible label rendered beside the dot. Omit for a standalone radio that is
	 * positioned by its parent (e.g. a permission grid cell) — pass `ariaLabel`
	 * for the accessible name in that case.
	 */
	label?: ReactNode;
	/** Layout-only classes for the label text span. */
	labelClassName?: string;
	/** Radio-group name — radios sharing a `name` are mutually exclusive. */
	name: string;
	/** Fires when this radio becomes the selected option in its group. */
	onChange: () => void;
	/**
	 * Dot + text scale. `md` (default) is the standard form size; `sm` is the
	 * compact dot for dense rows (e.g. permission grids).
	 */
	size?: "sm" | "md";
	/** Native tooltip on the wrapper. */
	title?: string;
	/** The value this option reports for the group. */
	value?: string;
}

/**
 * Labeled radio button — the single source of truth for the
 * `<label><input type="radio" />text</label>` pattern repeated across the admin
 * (option lists, rendering-mode pickers, permission grids). Mirrors `Checkbox`'s
 * API; groups (`RadioField`, `PermissionRadios`) own their own layout and render
 * one `Radio` per option.
 */
export const Radio = ({
	label,
	checked,
	onChange,
	name,
	value,
	disabled,
	size = "md",
	align = "center",
	className,
	labelClassName,
	ariaLabel,
	title,
}: RadioProps) => (
	<label
		className={`flex cursor-pointer text-text-2 has-[input:disabled]:cursor-not-allowed has-[input:disabled]:opacity-60 ${
			size === "sm" ? "gap-1.5 text-[11.5px]" : "gap-2 text-[12.5px]"
		} ${align === "start" ? "items-start" : "items-center"}${className ? ` ${className}` : ""}`}
		title={title}
	>
		<input
			aria-label={ariaLabel}
			checked={checked}
			className={`${size === "sm" ? "size-3.5" : "size-4"} accent-accent${
				align === "start" ? " mt-0.5" : ""
			}`}
			disabled={disabled}
			name={name}
			type="radio"
			value={value}
			onChange={onChange}
		/>
		{label != null && <span className={labelClassName}>{label}</span>}
	</label>
);
