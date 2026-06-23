import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CheckCircle2, Unplug } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { TextInput } from "@/components/ui/TextInput";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Field } from "@/components/ui/Field";
import { UploadButton } from "@/components/ui/UploadButton";
import { Card } from "@/components/ui/Card";

import { type AnalyticsStatus, configureApi } from "@/api/endpoints/configure";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { useState } from "react";

const failed = (fallback: string) => (err: unknown) =>
	toast.error(err instanceof ApiError && err.message ? err.message : fallback);

export const ConfigureAnalytics = () => {
	const queryClient = useQueryClient();
	const detailQ = useQuery({
		queryKey: ["configure", "analytics"],
		queryFn: () => configureApi.analytics.get(),
	});

	const [confirmDisconnect, setConfirmDisconnect] = useState(false);
	const [propertyId, setPropertyId] = useState("");

	const onStatus = (fresh: AnalyticsStatus) => {
		queryClient.setQueryData(["configure", "analytics"], fresh);
	};

	const disconnectMutation = useMutation({
		mutationFn: () => configureApi.analytics.disconnect(),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["configure", "analytics"] });
			toast.success("Disconnected from Google Analytics");
			setConfirmDisconnect(false);
		},
		onError: failed("Disconnect failed"),
	});

	const uploadMutation = useMutation({
		mutationFn: (file: File) => configureApi.analytics.uploadCredentials(file),
		onSuccess: (fresh) => {
			onStatus(fresh);
			setPropertyId(fresh.property_id || "");
			toast.success("Service-account key uploaded");
		},
		onError: failed("Could not read that key file"),
	});

	const verifyMutation = useMutation({
		mutationFn: () => configureApi.analytics.setProperty(propertyId.trim()),
		onSuccess: (fresh) => {
			onStatus(fresh);
			toast.success("Property ID verified");
		},
		onError: failed("Could not verify that property ID"),
	});

	// Credentials are uploaded once the service-account email comes back, even
	// though `verified` stays false until the property ID is confirmed.
	const hasCredentials = !!detailQ.data?.service_account;

	return (
		<ConfigureLayout
			title="Analytics"
			sub="Google Analytics 4 service-account hookup that powers the dashboard's traffic chart."
		>
			{detailQ.isLoading && <p className="text-[12.5px] text-text-3">Loading…</p>}

			{detailQ.error && <ErrorPanel error={detailQ.error} />}

			{detailQ.data && (
				<Card className="p-4">
					{detailQ.data.verified ? (
						<>
							<div className="mb-3 flex items-center gap-2 text-[13px] font-semibold text-accent">
								<CheckCircle2 size={14} />
								Connected
							</div>

							<dl className="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-2 text-[12.5px]">
								<dt className="text-text-3">Service account</dt>
								<dd className="font-mono text-text">
									{detailQ.data.service_account || "—"}
								</dd>
								<dt className="text-text-3">Property ID</dt>
								<dd className="font-mono text-text">
									{detailQ.data.property_id || "—"}
								</dd>
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
							<ol className="space-y-5">
								<li>
									<div className="mb-2 flex items-center gap-2 text-[12.5px] font-semibold text-text">
										<span className="flex size-5 items-center justify-center rounded-full bg-accent text-[11px] text-accent-fg">
											1
										</span>
										Upload service-account key
									</div>

									<p className="mb-2 text-[12.5px] text-text-2">
										Upload the Google service-account JSON key with access to
										your GA4 property.
									</p>

									{hasCredentials ? (
										<div className="flex items-center gap-2 text-[12.5px] text-accent">
											<CheckCircle2 size={13} />
											<span className="font-mono text-text">
												{detailQ.data.service_account}
											</span>
										</div>
									) : (
										<UploadButton
											accept=".json,application/json"
											disabled={uploadMutation.isPending}
											onSelect={(file) => uploadMutation.mutate(file)}
											label={
												uploadMutation.isPending
													? "Uploading…"
													: "Upload key"
											}
										/>
									)}
								</li>

								<li className={hasCredentials ? "" : "opacity-50"}>
									<div className="mb-2 flex items-center gap-2 text-[12.5px] font-semibold text-text">
										<span className="flex size-5 items-center justify-center rounded-full bg-accent text-[11px] text-accent-fg">
											2
										</span>
										Set + verify property ID
									</div>

									<div className="flex items-end gap-2">
										<Field label="GA4 property ID" className="flex-1">
											<TextInput
												value={propertyId}
												disabled={!hasCredentials}
												placeholder="e.g. 123456789"
												onChange={(e) => setPropertyId(e.target.value)}
											/>
										</Field>

										<button
											type="button"
											disabled={
												!hasCredentials ||
												!propertyId.trim() ||
												verifyMutation.isPending
											}
											onClick={() => verifyMutation.mutate()}
											className="mb-px inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-2 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover disabled:opacity-60"
										>
											{verifyMutation.isPending ? "Verifying…" : "Verify"}
										</button>
									</div>
								</li>
							</ol>

							{hasCredentials && (
								<button
									type="button"
									onClick={() => setConfirmDisconnect(true)}
									className="mt-5 inline-flex items-center gap-1.5 rounded-md border border-danger/40 px-3 py-1.5 text-[12.5px] font-medium text-danger hover:bg-danger/10"
								>
									<Unplug size={13} />
									Start over
								</button>
							)}
						</>
					)}
				</Card>
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
