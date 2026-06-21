import type { DbOption } from "@/api/endpoints/db";
import { InlineEmpty } from "@/components/ui/InlineEmpty";

interface ViewActionsControlProps {
	/** Action map keyed by action route; built-ins store the value `"on"`. */
	value: Record<string, unknown>;
	onChange: (actions: Record<string, unknown>) => void;
	/** Columns of the view's table; drives which toggles are offered. */
	columns: DbOption[];
	/** True while the column list is still loading. */
	loading?: boolean;
	/** True when no table has been selected yet. */
	tableSelected: boolean;
}

/**
 * One of BigTree's standard row actions. `key` is the table column whose
 * presence enables the action (the legacy admin gates each toggle on
 * `in_array($action["key"], $tblfields)`); `id` columns are always present, so
 * Edit/Delete always offer.
 */
interface StandardAction {
	route: string;
	column: string;
	label: string;
	description: string;
}

// Mirrors BigTreeAdmin::$ViewActions, in the same display order.
const STANDARD_ACTIONS: StandardAction[] = [
	{
		route: "edit",
		column: "id",
		label: "Edit",
		description: "Open the related form to edit the row.",
	},
	{
		route: "delete",
		column: "id",
		label: "Delete",
		description: "Delete the row.",
	},
	{
		route: "approve",
		column: "approved",
		label: "Approve",
		description: "Toggle the row's approved state.",
	},
	{
		route: "archive",
		column: "archived",
		label: "Archive",
		description: "Toggle the row's archived state.",
	},
	{
		route: "feature",
		column: "featured",
		label: "Feature",
		description: "Toggle the row's featured state.",
	},
];

const STANDARD_ROUTES = new Set(STANDARD_ACTIONS.map((a) => a.route));

/**
 * Renders the standard row actions as toggles, choosing which to offer from the
 * selected table's columns — Approve/Archive/Feature appear only when their
 * backing column exists, while Edit/Delete are always available. Replaces the
 * raw JSON field from the first SPA pass; custom (non-standard) action keys
 * already on the view are preserved untouched.
 */
export const ViewActionsControl = ({
	value,
	onChange,
	columns,
	loading,
	tableSelected,
}: ViewActionsControlProps) => {
	const columnNames = new Set(columns.map((c) => c.value));

	const toggle = (route: string, on: boolean) => {
		const next = { ...value };

		if (on) {
			next[route] = "on";
		} else {
			delete next[route];
		}

		onChange(next);
	};

	// Offer an action when its backing column exists, or when it's already set
	// (so a toggle for an action on a since-changed table stays editable).
	const available = STANDARD_ACTIONS.filter((a) => columnNames.has(a.column) || a.route in value);

	const customRoutes = Object.keys(value).filter((route) => !STANDARD_ROUTES.has(route));

	return (
		<div>
			<div className="mb-2 text-[12px] font-semibold uppercase tracking-[0.06em] text-text-3">
				Row actions
			</div>

			{!tableSelected ? (
				<InlineEmpty align="center">Select a table to choose row actions.</InlineEmpty>
			) : loading ? (
				<div className="px-1 py-2 text-[12.5px] text-text-3">Loading columns…</div>
			) : (
				<ul className="space-y-1.5">
					{available.map((action) => {
						const on = action.route in value;

						return (
							<li
								key={action.route}
								className="flex items-center gap-3 rounded-md border border-border bg-surface px-3 py-2"
							>
								<input
									type="checkbox"
									id={`view-action-${action.route}`}
									checked={on}
									onChange={(e) => toggle(action.route, e.target.checked)}
									className="h-4 w-4 flex-shrink-0 accent-[var(--color-accent)]"
								/>
								<label
									htmlFor={`view-action-${action.route}`}
									className="flex min-w-0 flex-1 flex-col"
								>
									<span className="text-[13px] text-text">{action.label}</span>
									<span className="text-[11.5px] text-text-3">
										{action.description}
									</span>
								</label>
							</li>
						);
					})}
				</ul>
			)}

			{customRoutes.length > 0 && (
				<p className="mt-2 text-[11.5px] text-text-3">
					Custom actions on this view are preserved: {customRoutes.join(", ")}.
				</p>
			)}
		</div>
	);
};
