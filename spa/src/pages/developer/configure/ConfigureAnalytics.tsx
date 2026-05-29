import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CheckCircle2, ExternalLink, Unplug } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import { configureApi } from "@/api/endpoints/configure";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { useState } from "react";

export const ConfigureAnalytics = () => {
	const queryClient = useQueryClient();
	const detailQ = useQuery({
		queryKey: ["configure", "analytics"],
		queryFn: () => configureApi.analytics.get(),
	});

	const [confirmDisconnect, setConfirmDisconnect] = useState(false);

	const disconnectMutation = useMutation({
		mutationFn: () => configureApi.analytics.disconnect(),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["configure", "analytics"] });
			toast.success("Disconnected from Google Analytics");
			setConfirmDisconnect(false);
		},
		onError: (err) => {
			toast.error(err instanceof ApiError && err.message ? err.message : "Disconnect failed");
		},
	});

	return (
		<ConfigureLayout
			title="Analytics"
			sub="Google Analytics 4 service-account hookup that powers the dashboard's traffic chart."
		>
			{detailQ.isLoading && <p className="text-[12.5px] text-text-3">Loading…</p>}

			{detailQ.error && <ErrorPanel error={detailQ.error} />}

			{detailQ.data && (
				<div className="rounded-xl border border-border bg-surface p-4">
					{detailQ.data.verified ? (
						<>
							<div className="mb-3 flex items-center gap-2 text-[13px] font-semibold text-accent">
								<CheckCircle2 size={14} />
								Connected
							</div>

							<dl className="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-2 text-[12.5px]">
								<dt className="text-text-3">Service account</dt>
								<dd className="font-mono text-text">{detailQ.data.service_account || "—"}</dd>
								<dt className="text-text-3">Property ID</dt>
								<dd className="font-mono text-text">{detailQ.data.property_id || "—"}</dd>
							</dl>

							<button
								type="button"
								onClick={() => setConfirmDisconnect(true)}
								className="mt-4 inline-flex items-center gap-1.5 rounded-md border border-danger/40 px-3 py-1.5 text-[12.5px] font-medium text-danger hover:bg-danger/10"
							>
								<Unplug size={13} />
								Disconnect
							</button>
						</>
					) : (
						<>
							<p className="text-[13px] text-text-2">
								Analytics isn't connected yet. The activation flow uploads a Google
								service-account JSON file — which still lives on the legacy admin.
							</p>

							<a
								className="mt-3 inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover"
								href={detailQ.data.setup_url}
								target="_blank"
								rel="noreferrer"
							>
								Open activation flow
								<ExternalLink size={12} />
							</a>

							<p className="mt-3 text-[11.5px] text-text-3">
								After the legacy admin verifies the credentials, refresh this page to see
								the connected state.
							</p>
						</>
					)}
				</div>
			)}

			{confirmDisconnect && (
				<ConfirmDialog
					open
					onOpenChange={(open) => {
						if (!open) {
							setConfirmDisconnect(false);
						}
					}}
					title="Disconnect Google Analytics?"
					description="The dashboard's traffic widget will stop showing data until you reconnect."
					confirmLabel="Disconnect"
					variant="danger"
					onConfirm={() => disconnectMutation.mutate()}
				/>
			)}
		</ConfigureLayout>
	);
};
