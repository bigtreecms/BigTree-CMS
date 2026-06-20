import { forwardRef, type SelectHTMLAttributes } from "react";

import { inputClass } from "./TextInput";

type SelectProps = SelectHTMLAttributes<HTMLSelectElement>;

/**
 * Bare `<select>` sharing the canonical {@link inputClass} so dropdowns line up
 * with text inputs. Render `<option>`s as children. Use {@link SelectField} when
 * you also want the label/error chrome.
 */
export const Select = forwardRef<HTMLSelectElement, SelectProps>(
	({ className, children, ...rest }, ref) => (
		<select
			ref={ref}
			className={className ? `${inputClass} ${className}` : inputClass}
			{...rest}
		>
			{children}
		</select>
	)
);

Select.displayName = "Select";
