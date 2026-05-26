import type { ModuleView } from "@/api/endpoints/modules";

import { SearchableView } from "./SearchableView";
import { UnsupportedView } from "./UnsupportedView";

/**
 * View dispatcher. Picks the right runtime subcomponent based on the view's
 * `type` field. Phase 7 ships the SearchableView; the other types render an
 * UnsupportedView panel so the dispatcher contract is complete and follow-up
 * commits can swap in real implementations one at a time.
 *
 * Adding a new view type only touches this dispatch table — the wrapping
 * ModuleView page does not need to know which type it has.
 */

export interface ViewRendererProps {
	moduleId: number;
	view: ModuleView;
}

export const ViewRenderer = ({ moduleId, view }: ViewRendererProps) => {
	switch (view.type) {
		case "searchable":
			return <SearchableView moduleId={moduleId} view={view} />;

		case "nested":
		case "draggable":
		case "grouped":
		case "images":
		case "images-grouped":
			return <UnsupportedView view={view} />;

		default:
			return <UnsupportedView view={view} />;
	}
};
