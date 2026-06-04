import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Archive, ArchiveRestore, Check, Star, StarOff, X } from "lucide-react";

import {
	autoModulesApi,
	type ModuleEntryFlagToggleResponse,
	type ModuleEntryRow,
} from "@/api/endpoints/auto-modules";
import { toast } from "@/lib/toast";

import type { BuiltinViewActionFlags } from "./viewHelpers";

/**
 * The three legacy "publisher-only toggle" actions — archive, approve, feature —
 * stored as `"on"` flags on the source table. Each button mirrors the legacy
 * admin: icon and title flip based on the current row state; click POSTs to
 * the matching API route and invalidates the entries query so the cached row
 * column (which feeds the next render) refreshes.
 *
 * Server enforces publisher permission; the toast surfaces 403s.
 */
interface BuiltinToggleButtonsProps {
	moduleId: string;
	viewId: string;
	row: ModuleEntryRow;
	builtins: BuiltinViewActionFlags;
}

const isOn = (raw: unknown): boolean => raw === "on" || raw === true || raw === 1 || raw === "1";

/**
 * Success messages mirror the legacy admin growls fired by
 * ajax/auto-modules/views/{archive,approve,feature}.php — keyed by the source
 * column and whether the flag is now set ("on") or cleared ("").
 */
const FLAG_TOAST_MESSAGES: Record<
	ModuleEntryFlagToggleResponse["column"],
	{ on: string; off: string }
> = {
	archived: { on: "Item is now archived.", off: "Item is now unarchived." },
	approved: { on: "Item is now approved.", off: "Item is now unapproved." },
	featured: { on: "Item is now featured.", off: "Item is now unfeatured." },
};

export const BuiltinToggleButtons = ({
	moduleId,
	viewId,
	row,
	builtins,
}: BuiltinToggleButtonsProps) => {
	const queryClient = useQueryClient();
	const entryId = Number(row.id);
	const canMutate = Number.isFinite(entryId) && entryId > 0;

	const onSuccess = (data: ModuleEntryFlagToggleResponse) => {
		queryClient.invalidateQueries({ queryKey: ["module-entries", moduleId, viewId] });

		const messages = FLAG_TOAST_MESSAGES[data.column];

		if (messages) {
			toast.success(data.value === "on" ? messages.on : messages.off);
		}
	};

	const archiveMutation = useMutation({
		mutationFn: () => autoModulesApi.archive(moduleId, entryId, viewId),
		onSuccess,
		onError: () => toast.error("Couldn't update archive state"),
	});

	const approveMutation = useMutation({
		mutationFn: () => autoModulesApi.approve(moduleId, entryId, viewId),
		onSuccess,
		onError: () => toast.error("Couldn't update approval state"),
	});

	const featureMutation = useMutation({
		mutationFn: () => autoModulesApi.feature(moduleId, entryId, viewId),
		onSuccess,
		onError: () => toast.error("Couldn't update feature state"),
	});

	const archived = isOn(row.archived);
	const approved = isOn(row.approved);
	const featured = isOn(row.featured);

	return (
		<>
			{builtins.archive && (
				<button
					type="button"
					className="rounded p-1 text-text-3 hover:bg-hover hover:text-text disabled:opacity-40"
					title={archived ? "Restore" : "Archive"}
					aria-label={archived ? "Restore" : "Archive"}
					disabled={!canMutate || archiveMutation.isPending}
					onClick={(e) => {
						e.stopPropagation();
						archiveMutation.mutate();
					}}
				>
					{archived ? <ArchiveRestore size={15} /> : <Archive size={15} />}
				</button>
			)}

			{builtins.feature && (
				<button
					type="button"
					className="rounded p-1 text-text-3 hover:bg-hover hover:text-text disabled:opacity-40"
					title={featured ? "Unfeature" : "Feature"}
					aria-label={featured ? "Unfeature" : "Feature"}
					disabled={!canMutate || featureMutation.isPending}
					onClick={(e) => {
						e.stopPropagation();
						featureMutation.mutate();
					}}
				>
					{featured ? <StarOff size={15} /> : <Star size={15} />}
				</button>
			)}

			{builtins.approve && (
				<button
					type="button"
					className="rounded p-1 text-text-3 hover:bg-hover hover:text-text disabled:opacity-40"
					title={approved ? "Unapprove" : "Approve"}
					aria-label={approved ? "Unapprove" : "Approve"}
					disabled={!canMutate || approveMutation.isPending}
					onClick={(e) => {
						e.stopPropagation();
						approveMutation.mutate();
					}}
				>
					{approved ? <X size={15} /> : <Check size={15} />}
				</button>
			)}
		</>
	);
};
