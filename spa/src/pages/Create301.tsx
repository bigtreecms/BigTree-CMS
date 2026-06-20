import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft, Save } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { Button } from "@/components/ui/Button";

import { fourOhFoursApi } from "@/api/endpoints/four-oh-fours";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { validateRequired } from "@/lib/formValidation";

import { SelectField, TextField } from "./developer/TemplateEdit";

/**
 * /dashboard/404s/301/add — manually create a single 301 redirect. Mirrors the
 * legacy `create-301` form: a From/To pair plus a site picker in a multi-site
 * install (the server infers the site from the URL when a full URL is given).
 */
export const Create301 = () => {
	const navigate = useNavigate();
	const queryClient = useQueryClient();

	const [from, setFrom] = useState("");
	const [to, setTo] = useState("");
	const [siteKey, setSiteKey] = useState("");
	const [error, setError] = useState<string | null>(null);
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

	useScrollToFirstError(fieldErrors);

	const sitesQ = useQuery({
		queryKey: ["404s", "sites"],
		queryFn: () => fourOhFoursApi.sites(),
	});

	const sites = sitesQ.data ?? [];
	const multisite = sites.length > 1;

	const createMutation = useMutation({
		mutationFn: () =>
			fourOhFoursApi.create({
				from: from.trim(),
				to: to.trim(),
				site_key: multisite && siteKey ? siteKey : undefined,
			}),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["404s"] });
			queryClient.invalidateQueries({ queryKey: ["dashboard"] });
			toast.success("Redirect created");
			navigate("/dashboard/404s/301");
		},
		onError: (err) => {
			setError(
				err instanceof ApiError && err.message ? err.message : "Could not create redirect"
			);
		},
	});

	const isDirty = useDirtyTracker({ from, to, siteKey }) && !createMutation.isPending;

	return (
		<div className="mx-auto max-w-screen-md px-6 py-4">
			<Breadcrumb
				items={[
					{ label: "Dashboard", to: "/dashboard" },
					{ label: "404 Report", to: "/dashboard/404s" },
					{ label: "301 Redirects", to: "/dashboard/404s/301" },
					{ label: "Add" },
				]}
			/>

			<PageHead
				title="Add 301 redirect"
				sub="Send an old URL to a new destination."
				actions={
					<Button icon={<ChevronLeft size={13} />} to="/dashboard/404s/301">
						Back
					</Button>
				}
			/>

			{error && (
				<div className="mb-3 rounded-md border border-danger/40 bg-danger/5 px-3 py-2 text-[12.5px] text-danger">
					{error}
				</div>
			)}

			<form
				onSubmit={(e) => {
					e.preventDefault();

					const errors = validateRequired([
						{ field: "from", label: "From", value: from },
						{ field: "to", label: "To", value: to },
					]);

					if (Object.keys(errors).length > 0) {
						setFieldErrors(errors);
						setError("Please fill in the required fields.");

						return;
					}

					setFieldErrors({});
					setError(null);
					createMutation.mutate();
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

				<TextField
					label="From"
					value={from}
					onChange={setFrom}
					hint="A full URL or just the path after your domain (e.g. /old-page/)."
					error={fieldErrors.from}
					required
				/>

				<TextField
					label="To"
					value={to}
					onChange={setTo}
					hint="The destination — a full URL including http:// or an internal page."
					error={fieldErrors.to}
					required
				/>

				<div className="flex justify-end gap-2 border-t border-border pt-3">
					<Button to="/dashboard/404s/301">Cancel</Button>
					<Button
						variant="primary"
						type="submit"
						icon={<Save size={13} />}
						disabled={createMutation.isPending}
					>
						{createMutation.isPending ? "Creating…" : "Create redirect"}
					</Button>
				</div>
			</form>

			<UnsavedChangesGuard isDirty={isDirty} />
		</div>
	);
};
