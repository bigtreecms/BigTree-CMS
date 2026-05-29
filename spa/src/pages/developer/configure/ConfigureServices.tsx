import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CheckCircle2, ExternalLink, Unplug } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import { configureApi } from "@/api/endpoints/configure";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";

const SERVICES: Array<{ id: string; label: string }> = [
	{ id: "twitter", label: "Twitter / X" },
	{ id: "instagram", label: "Instagram" },
	{ id: "youtube", label: "YouTube" },
	{ id: "flickr", label: "Flickr" },
	{ id: "salesforce", label: "Salesforce" },
	{ id: "disqus", label: "Disqus" },
	{ id: "facebook", label: "Facebook" },
];

export const ConfigureServices = () => {
	const queryClient = useQueryClient();
	const detailQ = useQuery({
		queryKey: ["configure", "services"],
		queryFn: () => configureApi.services.list(),
	});

	const [confirmDisconnect, setConfirmDisconnect] = useState<string | null>(null);

	const disconnectMutation = useMutation({
		mutationFn: (service: string) => configureApi.services.disconnect(service),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["configure", "services"] });
			toast.success("Service disconnected");
			setConfirmDisconnect(null);
		},
		onError: (err) => {
			toast.error(err instanceof ApiError && err.message ? err.message : "Disconnect failed");
		},
	});

	const data = detailQ.data;
	const baseUrl = typeof data?._setup_url_base === "string" ? data._setup_url_base : "";

	return (
		<ConfigureLayout
			title="Services"
			sub="Third-party social / business integrations. OAuth handshakes still happen on the legacy admin; this screen lists connection state and lets you disconnect."
		>
			{detailQ.isLoading && <p className="text-[12.5px] text-text-3">Loading…</p>}

			{detailQ.error && <ErrorPanel error={detailQ.error} />}

			{data && (
				<div className="space-y-3">
					{SERVICES.map((s) => {
						const entry = data[s.id];

						if (!entry || typeof entry === "string") {
							return null;
						}

						const setupUrl = `${baseUrl}${s.id}/`;

						return (
							<div
								key={s.id}
								className="flex items-center justify-between rounded-xl border border-border bg-surface p-4"
							>
								<div className="min-w-0">
									<div className="text-[13px] font-semibold text-text">{s.label}</div>

									{entry.connected ? (
										<div className="mt-1 flex items-center gap-2 text-[12px] text-accent">
											<CheckCircle2 size={12} />
											<span className="truncate">
												Connected{entry.identity ? ` — ${entry.identity}` : ""}
											</span>
										</div>
									) : (
										<div className="mt-1 text-[12px] text-text-3">Not connected</div>
									)}
								</div>

								<div className="flex items-center gap-2">
									<a
										href={setupUrl}
										target="_blank"
										rel="noreferrer"
										className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface-2 px-3 py-1.5 text-[12.5px] font-medium text-text hover:bg-hover"
									>
										{entry.connected ? "Manage" : "Connect"}
										<ExternalLink size={12} />
									</a>

									{entry.connected && (
										<button
											type="button"
											onClick={() => setConfirmDisconnect(s.id)}
											className="inline-flex items-center gap-1.5 rounded-md border border-danger/40 px-3 py-1.5 text-[12.5px] font-medium text-danger hover:bg-danger/10"
										>
											<Unplug size={13} />
											Disconnect
										</button>
									)}
								</div>
							</div>
						);
					})}
				</div>
			)}

			{confirmDisconnect && (
				<ConfirmDialog
					open
					onOpenChange={(open) => {
						if (!open) {
							setConfirmDisconnect(null);
						}
					}}
					title="Disconnect this service?"
					description="Any module fields that pull from this service will stop working until reconnected."
					confirmLabel="Disconnect"
					variant="danger"
					onConfirm={() => disconnectMutation.mutate(confirmDisconnect)}
				/>
			)}
		</ConfigureLayout>
	);
};
