import type { ModuleView } from "@/api/endpoints/modules";

import { DraggableView } from "./DraggableView";
import { GroupedView } from "./GroupedView";
import { ImagesGroupedView } from "./ImagesGroupedView";
import { ImagesView } from "./ImagesView";
import { NestedView } from "./NestedView";
import { SearchableView } from "./SearchableView";
import { UnsupportedView } from "./UnsupportedView";

/**
 * View dispatcher. Picks the right runtime subcomponent based on the view's
 * `type` field. All five legacy view types are now covered; the
 * UnsupportedView fallback remains for forward-compatibility with new view
 * types added server-side without an SPA companion.
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
			return <NestedView moduleId={moduleId} view={view} />;

		case "draggable":
			return <DraggableView moduleId={moduleId} view={view} />;

		case "grouped":
			return <GroupedView moduleId={moduleId} view={view} />;

		case "images":
			return <ImagesView moduleId={moduleId} view={view} />;

		case "images-grouped":
			return <ImagesGroupedView moduleId={moduleId} view={view} />;

		default:
			return <UnsupportedView view={view} />;
	}
};
