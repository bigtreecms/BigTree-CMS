import { forwardRef, type TextareaHTMLAttributes } from "react";

import { inputClass } from "./TextInput";

type TextAreaProps = TextareaHTMLAttributes<HTMLTextAreaElement>;

/**
 * Bare multi-line text input sharing the canonical {@link inputClass}. Defaults
 * to a resizable vertical handle; pass `rows` to size it. Append `font-mono` via
 * `className` for code/markup entry.
 */
export const TextArea = forwardRef<HTMLTextAreaElement, TextAreaProps>(
	({ className, rows = 4, ...rest }, ref) => (
		<textarea
			ref={ref}
			rows={rows}
			className={className ? `${inputClass} resize-y ${className}` : `${inputClass} resize-y`}
			{...rest}
		/>
	)
);

TextArea.displayName = "TextArea";
