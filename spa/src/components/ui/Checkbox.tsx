import { type ReactNode } from "react";

interface CheckboxProps {
	/** Visible label rendered beside the box. */
	label: ReactNode;
	checked: boolean;
	onChange: (checked: boolean) => void;
	disabled?: boolean;
	/**
	 * Box + text scale. `md` (default) is the standard form-toggle size; `sm` is
	 * the compact box/text for dense editor rows (size-row tables, inline option
	 * toggles).
	 */
	size?: "sm" | "md";
	/** Top-align the box for labels that wrap to multiple lines. Default `center`. */
	align?: "center" | "start";
	/** Layout-only classes appended to the wrapping `<label>` (e.g. grid spans). */
	className?: string;
	/** Layout-only classes for the label text span (e.g. `min-w-0 truncate`). */
	labelClassName?: string;
}

/**
 * Labeled checkbox — the single source of truth for the
 * `<label><input type="checkbox" />text</label>` pattern repeated across the
 * admin (form toggles, option lists, settings). Token-driven so light/dark both
 * work. For richer needs build on top: PageEdit's `Check` adds a pending badge;
 * a routed/segmented toggle is a different control.
 */
export const Checkbox = ({
	label,
	checked,
	onChange,
	disabled,
	size = "md",
	align = "center",
	className,
	labelClassName,
}: CheckboxProps) => (
	<label
		className={`flex cursor-pointer text-text-2 has-[input:disabled]:cursor-not-allowed has-[input:disabled]:opacity-60 ${
			size === "sm" ? "gap-1.5 text-[11.5px]" : "gap-2 text-[12.5px]"
		} ${align === "start" ? "items-start" : "items-center"}${className ? ` ${className}` : ""}`}
	>
		<input
			type="checkbox"
			className={`${size === "sm" ? "size-3.5" : "size-4"} accent-accent${
				align === "start" ? " mt-0.5" : ""
			}`}
			checked={checked}
			onChange={(e) => onChange(e.target.checked)}
			disabled={disabled}
		/>
		<span className={labelClassName}>{label}</span>
	</label>
);
