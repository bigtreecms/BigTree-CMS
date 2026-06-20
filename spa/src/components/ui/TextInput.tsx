import { forwardRef, type InputHTMLAttributes } from "react";

/**
 * Canonical class string for text-style form controls (`<input>`, `<select>`,
 * `<textarea>`) across the whole admin — token-driven so light/dark both work.
 * This is the single source of truth: the renderer's field engine re-exports it
 * as `INPUT_CLASS` and the labeled {@link TextField}/{@link SelectField}
 * wrappers compose on top of it. Append layout-only tweaks via `className`;
 * Tailwind source order means appended classes win for non-conflicting
 * properties only (don't try to override padding/size through `className`).
 */
export const inputClass =
	"w-full rounded-md border border-border bg-surface px-3 py-2 text-[13px] placeholder:text-text-3 focus:outline-none focus:ring-1 focus:ring-accent-ring disabled:cursor-not-allowed disabled:opacity-60";

type TextInputProps = InputHTMLAttributes<HTMLInputElement>;

/**
 * Bare single-line text input — just the canonical {@link inputClass} applied to
 * a native `<input>`, with every native prop forwarded. Use this when you need a
 * standalone control without the {@link Field} label/error chrome; use
 * {@link TextField} when you want the label wrapper too.
 */
export const TextInput = forwardRef<HTMLInputElement, TextInputProps>(
	({ type = "text", className, ...rest }, ref) => (
		<input
			ref={ref}
			type={type}
			className={className ? `${inputClass} ${className}` : inputClass}
			{...rest}
		/>
	)
);

TextInput.displayName = "TextInput";
