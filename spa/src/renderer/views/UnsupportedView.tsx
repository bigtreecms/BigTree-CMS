import type { ModuleView } from "@/api/endpoints/modules";

interface UnsupportedViewProps {
	view: ModuleView;
}

/**
 * Fallback for genuinely-unknown view types. All six built-in view types
 * (searchable, nested, draggable, grouped, images, images-grouped) now have
 * runtimes, so this is only reached by a custom/extension view type this build
 * doesn't recognize. Renders an explicit notice instead of an empty page, and
 * shows the view's configured columns so the data is at least inspectable.
 */
export const UnsupportedView = ({ view }: UnsupportedViewProps) => {
	const columns = Object.entries(view.fields ?? {});

	return (
		<div className="rounded-xl border border-border bg-surface p-6">
			<h3 className="text-[14px] font-semibold text-text">
				“{view.type}” views aren't recognized
			</h3>
			<p className="mt-1 text-[13px] text-text-3">
				This module's “{view.title}” view uses a view type this version of the admin doesn't
				recognize. Its configured columns are shown below.
			</p>
			{columns.length > 0 && (
				<div className="mt-4">
					<h4 className="text-[11px] font-semibold uppercase tracking-[0.06em] text-text-3">
						Configured columns
					</h4>
					<ul className="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-[12.5px] text-text-2 sm:grid-cols-3">
						{columns.map(([key, field]) => (
							<li key={key} className="truncate">
								<span className="font-medium">{field.title}</span>
								<span className="ml-1 font-mono text-[11px] text-text-3">
									{key}
								</span>
							</li>
						))}
					</ul>
				</div>
			)}
		</div>
	);
};
