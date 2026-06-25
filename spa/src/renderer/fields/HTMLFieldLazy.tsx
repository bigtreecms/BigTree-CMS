import { lazy, Suspense } from "react";

import { LoadingText } from "@/components/ui/LoadingText";
import type { FieldComponentProps } from "@/renderer/fields/types";

/**
 * TinyMCE is ~1.5MB. Loading it eagerly through the field registry pulls it into
 * the main bundle for every admin page, even ones with no rich-text field. This
 * adapter defers the import until an "html" field actually renders, keeping
 * TinyMCE in its own async chunk.
 */
const HTMLField = lazy(() => import("@/renderer/fields/HTMLField"));

export const HTMLFieldLazy = (props: FieldComponentProps) => {
	return (
		<Suspense fallback={<LoadingText label="Loading editor…" />}>
			<HTMLField {...props} />
		</Suspense>
	);
};
