import { type ReactNode } from "react";

interface CheckboxProps {
	/** Visible label rendered beside the box. */
	label: ReactNode;
	checked: boolean;
	onChange: (checked: boolean) => void;
	disabled?: boolean;
	/** Top-align the box for labels that wrap to multiple lines. Default `center`. */
	align?: "center" | "start";
	/** Layout-only classes appended to the wrapping `<label>` (e.g. grid spans). */
	className?: string;
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
	align = "center",
	className,
}: CheckboxProps) => (
	<label
		className={`flex cursor-pointer gap-2 text-[12.5px] text-text-2 has-[input:disabled]:cursor-not-allowed has-[input:disabled]:opacity-60 ${
			align === "start" ? "items-start" : "items-center"
		}${className ? ` ${className}` : ""}`}
	>
		<input
			type="checkbox"
			className={align === "start" ? "mt-0.5 size-4  accent-accent" : "size-4  accent-accent"}
			checked={checked}
			onChange={(e) => onChange(e.target.checked)}
			disabled={disabled}
		/>
		<span>{label}</span>
	</label>
);
