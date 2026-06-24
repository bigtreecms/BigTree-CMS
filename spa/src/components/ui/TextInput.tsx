import { forwardRef, type InputHTMLAttributes } from "react";

/**
 * Density / style variants for text-style controls. Padding and text size are
 * baked into the class string here (rather than appended via `className`)
 * because Tailwind can't reliably override a conflicting `py-*`/`text-*`
 * utility through source order — see the note on {@link inputClass}.
 */
export interface InputClassOptions {
	/** Compact vertical padding (`py-1.5`) for space-constrained sections. */
	dense?: boolean;
	/**
	 * The tightest tier — `px-2 py-1 text-[12.5px]`, and **no width** (callers
	 * supply `w-44` / `flex-1` / auto via `className`). For the dense grid/toolbar
	 * controls in the module designer and report-filter editors, where inputs and
	 * selects sit inline at a smaller size than a normal form field.
	 */
	compact?: boolean;
	/** Monospace + slightly smaller text for code/identifier entry. */
	mono?: boolean;
}

const INPUT_BASE =
	"rounded-md border border-border bg-surface placeholder:text-text-3 focus:outline-none focus:ring-1 focus:ring-accent-ring disabled:cursor-not-allowed disabled:opacity-60";

/**
 * Builds the canonical class string for a text-style control at the requested
 * density/style. Prefer the {@link dense}/{@link compact}/{@link mono} props on
 * the primitives over calling this directly.
 */
export const inputClassFor = ({ dense, compact, mono }: InputClassOptions = {}): string => {
	if (compact) {
		return `${INPUT_BASE} px-2 py-1 text-[12.5px]`;
	}

	return `w-full ${INPUT_BASE} px-3 ${dense ? "py-1.5" : "py-2"} ${
		mono ? "font-mono text-[12px]" : "text-[13px]"
	}`;
};

/**
 * Canonical class string for text-style form controls (`<input>`, `<select>`,
 * `<textarea>`) across the whole admin — token-driven so light/dark both work.
 * This is the single source of truth: the renderer's field engine re-exports it
 * as `INPUT_CLASS` and the labeled {@link TextField}/{@link SelectField}
 * wrappers compose on top of it. Append layout-only tweaks via `className`;
 * Tailwind source order means appended classes win for non-conflicting
 * properties only (don't try to override padding/size through `className` — use
 * the `dense`/`mono` props instead).
 */
export const inputClass = inputClassFor();

type TextInputProps = InputHTMLAttributes<HTMLInputElement> & InputClassOptions;

/**
 * Bare single-line text input — just the canonical {@link inputClass} applied to
 * a native `<input>`, with every native prop forwarded. Use this when you need a
 * standalone control without the {@link Field} label/error chrome; use
 * {@link TextField} when you want the label wrapper too.
 */
export const TextInput = forwardRef<HTMLInputElement, TextInputProps>(
	({ type = "text", dense, compact, mono, className, ...rest }, ref) => {
		const base = inputClassFor({ dense, compact, mono });

		return (
			<input
				ref={ref}
				type={type}
				className={className ? `${base} ${className}` : base}
				{...rest}
			/>
		);
	}
);

TextInput.displayName = "TextInput";
