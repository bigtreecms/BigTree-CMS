import type { DbOption } from "@/api/endpoints/db";
import { Checkbox } from "@/components/ui/Checkbox";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { Loading } from "@/components/ui/Loading";
import { SectionLabel } from "@/components/ui/SectionLabel";

interface ViewActionsControlProps {
	/** Columns of the view's table; drives which toggles are offered. */
	columns: DbOption[];
	/** True while the column list is still loading. */
	loading?: boolean;
	onChange: (actions: Record<string, unknown>) => void;
	/** True when no table has been selected yet. */
	tableSelected: boolean;
	/** Action map keyed by action route; built-ins store the value `"on"`. */
	value: Record<string, unknown>;
}

/**
 * One of BigTree's standard row actions. `key` is the table column whose
 * presence enables the action (the legacy admin gates each toggle on
 * `in_array($action["key"], $tblfields)`); `id` columns are always present, so
 * Edit/Delete always offer.
 */
interface StandardAction {
	column: string;
	description: string;
	label: string;
	route: string;
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
			<SectionLabel className="mb-2">Row actions</SectionLabel>

			{!tableSelected ? (
				<InlineEmpty align="center">Select a table to choose row actions.</InlineEmpty>
			) : loading ? (
				<Loading className="px-1 py-2" label="Loading columns…" />
			) : (
				<ul className="space-y-1.5">
					{available.map((action) => {
						const on = action.route in value;

						return (
							<li
								className="rounded-md border border-border bg-surface px-3 py-2"
								key={action.route}
							>
								<Checkbox
									checked={on}
									className="w-full"
									label={
										<>
											<span className="text-[13px] text-text">
												{action.label}
											</span>
											<span className="text-[11.5px] text-text-3">
												{action.description}
											</span>
										</>
									}
									labelClassName="flex min-w-0 flex-1 flex-col"
									onChange={(checked) => toggle(action.route, checked)}
								/>
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
