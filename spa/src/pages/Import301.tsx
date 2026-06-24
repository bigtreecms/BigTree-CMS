import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft, Upload } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";

import { fourOhFoursApi } from "@/api/endpoints/four-oh-fours";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

import { SelectField } from "@/components/ui/SelectField";
import { Field } from "@/components/ui/Field";

/**
 * /dashboard/404s/301/import — guided CSV import for 301 redirects. Mirrors the
 * legacy `upload-csv` screen: explains the two-column format, offers a site
 * picker in a multi-site install, and lets the user flag a header row. Each row
 * runs through the server's create301 (parse + dedupe + IPL conversion).
 */
export const Import301 = () => {
	const navigate = useNavigate();
	const queryClient = useQueryClient();

	const [file, setFile] = useState<File | null>(null);
	const [siteKey, setSiteKey] = useState("");
	const [firstRowTitles, setFirstRowTitles] = useState(false);
	const [error, setError] = useState<string | null>(null);

	const sitesQ = useQuery({
		queryKey: ["404s", "sites"],
		queryFn: () => fourOhFoursApi.sites(),
	});

	const sites = sitesQ.data ?? [];
	const multisite = sites.length > 1;

	const importMutation = useMutation({
		mutationFn: () =>
			fourOhFoursApi.importCsv(file as File, {
				siteKey: multisite && siteKey ? siteKey : undefined,
				firstRowTitles,
			}),
		onSuccess: (result) => {
			queryClient.invalidateQueries({ queryKey: ["404s"] });
			queryClient.invalidateQueries({ queryKey: ["dashboard"] });
			toast.success(
				`Imported ${result.imported} redirect${result.imported === 1 ? "" : "s"}` +
					(result.skipped ? ` (${result.skipped} skipped)` : "")
			);
			navigate("/dashboard/404s/301");
		},
		onError: (err) => {
			setError(err instanceof ApiError && err.message ? err.message : "CSV import failed");
		},
	});

	return (
		<div className="mx-auto max-w-3xl px-6 py-4">
			<Breadcrumb
				items={[
					{ label: "Dashboard", to: "/dashboard" },
					{ label: "404 Report", to: "/dashboard/404s" },
					{ label: "301 Redirects", to: "/dashboard/404s/301" },
					{ label: "Import CSV" },
				]}
			/>

			<PageHead
				title="Import 301 redirects"
				sub="Bulk-create redirects from a CSV file."
				actions={
					<Button icon={<ChevronLeft size={13} />} to="/dashboard/404s/301">
						Back
					</Button>
				}
			/>

			<div className="mb-4 rounded-xl border border-border bg-surface-2 p-4 text-[12.5px] text-text-2">
				<p>
					Upload a comma-delimited, quote-escaped CSV file (not XLS, XLSX or TSV) with
					exactly two columns:
				</p>
				<ol className="mt-2 list-decimal space-y-1 pl-5">
					<li>
						<strong>Source URL</strong> — a full URL or just the path fragment after
						your domain.
					</li>
					<li>
						<strong>Destination URL</strong> — a full URL including <code>http://</code>
						.
					</li>
				</ol>
				<p className="mt-2 text-text-3">
					Each row is matched against existing redirects, so re-importing updates rather
					than duplicates.
				</p>
			</div>

			{error && (
				<div className="mb-3 rounded-md border border-danger/40 bg-danger/5 px-3 py-2 text-[12.5px] text-danger">
					{error}
				</div>
			)}

			<form
				onSubmit={(e) => {
					e.preventDefault();
					setError(null);
					importMutation.mutate();
				}}
				className="space-y-4 rounded-xl border border-border bg-surface p-4"
			>
				{multisite && (
					<SelectField
						label="Site"
						value={siteKey || sites[0]?.key || ""}
						onChange={setSiteKey}
						options={sites.map((site) => ({
							value: site.key,
							label: site.domain || site.key,
						}))}
					/>
				)}

				<Field label="CSV file" required>
					<input
						type="file"
						accept=".csv,text/csv"
						onChange={(e) => setFile(e.target.files?.[0] ?? null)}
						className="block w-full text-[12.5px] text-text-2 file:mr-3 file:rounded-md file:border file:border-border file:bg-surface-2 file:px-3 file:py-1.5 file:text-[12.5px] file:text-text hover:file:bg-hover"
					/>
				</Field>

				<Checkbox
					label="First row contains column titles"
					checked={firstRowTitles}
					onChange={setFirstRowTitles}
				/>

				<div className="flex justify-end gap-2 border-t border-border pt-3">
					<Button to="/dashboard/404s/301">Cancel</Button>
					<Button
						variant="primary"
						type="submit"
						icon={<Upload size={13} />}
						disabled={importMutation.isPending || !file}
					>
						{importMutation.isPending ? "Importing…" : "Import"}
					</Button>
				</div>
			</form>
		</div>
	);
};
