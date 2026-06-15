import { memo } from "react";

import type { FieldComponentProps } from "@/renderer/fields/types";

import { CustomField } from "./CustomField";
import { lookupFieldType } from "./fieldRegistry";

/**
 * Resolves a BigTree field-type slug to its renderer via the field registry
 * (`fieldRegistry.tsx`), which seeds every built-in type. Types with no
 * registered renderer are custom/extension types and are handed to
 * <CustomField />, which resolves their schema and dispatches on the render
 * contract (declarative today; module / server later — see
 * `spa/.custom-field-types-design.md`), falling back to a value-preserving stub.
 *
 * Adding a new built-in is a `registerFieldType(...)` call in the registry.
 */
const FieldRendererComponent = (props: FieldComponentProps) => {
	const entry = lookupFieldType(props.field.type);

	if (entry) {
		return entry.component(props);
	}

	return <CustomField {...props} />;
};

export const FieldRenderer = memo(FieldRendererComponent);
