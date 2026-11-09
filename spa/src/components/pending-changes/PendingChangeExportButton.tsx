import { Download } from "lucide-react";

import { Button } from "@/components/ui/Button";
import { pendingChangesApi } from "@/api/endpoints/dashboard";
import { useToastMutation } from "@/hooks/useToastMutation";
import { downloadCsv } from "@/lib/csv";
import { toast } from "@/lib/toast";

interface PendingChangeExportButtonProps {
	/** "pages", or the module id the section's changes belong to. */
	group: string;
}

/**
 * Export one section of the Pending Changes screen to CSV, as the legacy
 * screen's per-section "Export CSV" did. The server builds the table (author
 * names, page titles, the module view's columns) from the changes the caller
 * can publish; the browser saves it.
 */
export const PendingChangeExportButton = ({ group }: PendingChangeExportButtonProps) => {
	const exportMutation = useToastMutation({
		mutationFn: () => pendingChangesApi.export(group),
		errorMessage: "CSV export failed",
		onSuccess: ({ filename, headers, rows }) => {
			if (rows.length === 0) {
				toast.error("Nothing you can publish here to export.");

				return;
			}

			downloadCsv(filename, headers, rows);
		},
	});

	return (
		<Button
			icon={<Download size={12} />}
			loading={exportMutation.isPending}
			loadingLabel="Exporting…"
			size="sm"
			onClick={() => exportMutation.mutate()}
		>
			Export CSV
		</Button>
	);
};
