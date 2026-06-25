import type { ReactNode } from "react";

export type FieldSize = "sm" | "md";

/** The danger-colored required-field asterisk, shared by every label that marks a field required. */
export const RequiredMarker = () => <span className="text-danger"> *</span>;

interface FieldProps {
	/** Visible label. Optional — omit for an unlabeled wrapper (e.g. a control that only needs a hint). */
	label?: ReactNode;
	error?: string;
	/** Helper text shown under the control (above the error, if any). */
	hint?: ReactNode;
	/**
	 * Subtle helper rendered inline after the label (`font-normal text-text-3`) —
	 * the resource-designer treatment, where a short hint sits on the label line
	 * rather than below the control.
	 */
	inlineHint?: ReactNode;
	/** Appends a danger-colored asterisk to the label. */
	required?: boolean;
	/** Label text size: `md` (default, `text-[12px]`) or `sm` (`text-[11.5px]`, the denser designer controls). */
	size?: FieldSize;
	/**
	 * Wrapper element. `label` (default) wraps the control in a `<label>` so it is
	 * associated automatically; `div` renders a `<div>` + a `FieldLabel as="span"`
	 * for custom controls (Combobox / icon pickers / schema rows) that must not be
	 * nested inside a `<label>`.
	 */
	as?: "label" | "div";
	children: ReactNode;
	className?: string;
}

/**
 * Label + optional hint/error wrapper shared by the form-control primitives
 * (`TextField`, `SelectField`, …) and any one-off field that needs the same
 * label/error chrome. Renders a `<label>`, so the control inside is associated
 * automatically. The single source of truth for the label/hint/error layout —
 * `ControlShell` (the resource designer's wrapper) composes this with
 * `size="sm"` + an `inlineHint`.
 */
const labelSizeClass: Record<FieldSize, string> = {
	sm: "text-[11.5px]",
	md: "text-[12px]",
};

const labelToneClass = {
	default: "text-text-2",
	muted: "text-text-3",
} as const;

interface FieldLabelProps {
	children: ReactNode;
	/** Label text size: `md` (default, `text-[12px]`) or `sm` (`text-[11.5px]`). */
	size?: FieldSize;
	/** Color tier: `default` (`text-text-2`) or `muted` (`text-text-3`, de-emphasized filter labels). */
	tone?: keyof typeof labelToneClass;
	/** Appends a danger-colored asterisk. */
	required?: boolean;
	/** Subtle helper rendered inline after the label (`font-normal text-text-3`). */
	inlineHint?: ReactNode;
	/** Render as a `<span>` (default, for a label above a custom control) or a `<label htmlFor>`. */
	as?: "label" | "span";
	htmlFor?: string;
	className?: string;
}

/**
 * The standalone field-label span/`<label>` — the same typography `Field` bakes
 * internally, reachable on its own for the many places that need a label above a
 * custom control where `Field`'s wrapping `<label>` is wrong (icon pickers,
 * schema builders, the resource designer). Default `as="span"`; pass
 * `as="label"` + `htmlFor` when it labels a real input. The single source of
 * truth for label typography — `Field` renders its own label through this.
 */
export const FieldLabel = ({
	children,
	size = "md",
	tone = "default",
	required,
	inlineHint,
	as = "span",
	htmlFor,
	className,
}: FieldLabelProps) => {
	const classes = `mb-1 block font-medium ${labelSizeClass[size]} ${labelToneClass[tone]}${
		className ? ` ${className}` : ""
	}`;

	const content = (
		<>
			{children}
			{required && <RequiredMarker />}
			{inlineHint && <span className="ml-1 font-normal text-text-3">{inlineHint}</span>}
		</>
	);

	if (as === "label") {
		return (
			<label htmlFor={htmlFor} className={classes}>
				{content}
			</label>
		);
	}

	return <span className={classes}>{content}</span>;
};

export const Field = ({
	label,
	error,
	hint,
	inlineHint,
	required,
	size = "md",
	as = "label",
	children,
	className,
}: FieldProps) => {
	const wrapperClassName = className ? `block ${className}` : "block";

	const body = (
		<>
			{label && (
				<FieldLabel size={size} required={required} inlineHint={inlineHint}>
					{label}
				</FieldLabel>
			)}
			{children}

			{hint && (
				<span className="mt-1 block text-[11px] leading-relaxed text-text-3">{hint}</span>
			)}

			{error && (
				<span data-field-error className="mt-1 block text-[11.5px] text-danger">
					{error}
				</span>
			)}
		</>
	);

	if (as === "div") {
		return <div className={wrapperClassName}>{body}</div>;
	}

	return <label className={wrapperClassName}>{body}</label>;
};
