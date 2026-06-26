import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronLeft } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { FormFooter } from "@/components/ui/FormFooter";
import { FormShell } from "@/components/ui/FormShell";

import { fourOhFoursApi } from "@/api/endpoints/four-oh-fours";

import { toast } from "@/lib/toast";
import { queryKeys } from "@/lib/queryKeys";
import { useFormSubmit } from "@/hooks/useFormSubmit";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { validateRequired } from "@/lib/formValidation";

import { SelectField } from "@/components/ui/SelectField";
import { TextField } from "@/components/ui/TextField";

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
	const { error, fieldErrors, handleSubmit, onMutationError } = useFormSubmit();

	const sitesQ = useQuery({
		queryKey: queryKeys.redirects.sites(),
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
			queryClient.invalidateQueries({ queryKey: queryKeys.redirects.root() });
			queryClient.invalidateQueries({ queryKey: queryKeys.dashboard.root() });
			toast.success("Redirect created");
			navigate("/dashboard/404s/301");
		},
		onError: (err) => onMutationError(err, "Could not create redirect"),
	});

	const isDirty = useDirtyTracker({ from, to, siteKey }) && !createMutation.isPending;

	return (
		<PageContainer width="narrow">
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
				<Alert tone="danger" className="mb-3">
					{error}
				</Alert>
			)}

			<FormShell
				onSubmit={(e) =>
					handleSubmit(
						e,
						() =>
							validateRequired([
								{ field: "from", label: "From", value: from },
								{ field: "to", label: "To", value: to },
							]),
						() => createMutation.mutate()
					)
				}
				footer={
					<FormFooter
						cancelTo="/dashboard/404s/301"
						submitLabel="Create redirect"
						loading={createMutation.isPending}
						loadingLabel="Creating…"
					/>
				}
			>
				<div className="space-y-4">
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
				</div>
			</FormShell>

			<UnsavedChangesGuard isDirty={isDirty} />
		</PageContainer>
	);
};
