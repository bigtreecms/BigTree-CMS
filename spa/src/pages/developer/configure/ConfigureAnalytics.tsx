import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useToastMutation } from "@/hooks/useToastMutation";
import { CheckCircle2, Unplug } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { Button } from "@/components/ui/Button";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { TextInput } from "@/components/ui/TextInput";
import { Field } from "@/components/ui/Field";
import { UploadButton } from "@/components/ui/UploadButton";
import { Card } from "@/components/ui/Card";

import { type AnalyticsStatus, configureApi } from "@/api/endpoints/configure";

import { queryKeys } from "@/lib/queryKeys";
import { useState } from "react";

export const ConfigureAnalytics = () => {
	const queryClient = useQueryClient();
	const detailQ = useQuery({
		queryKey: queryKeys.configure.analytics(),
		queryFn: () => configureApi.analytics.get(),
	});

	const [confirmDisconnect, setConfirmDisconnect] = useState(false);
	const [propertyId, setPropertyId] = useState("");

	const onStatus = (fresh: AnalyticsStatus) => {
		queryClient.setQueryData(queryKeys.configure.analytics(), fresh);
	};

	const disconnectMutation = useToastMutation({
		mutationFn: () => configureApi.analytics.disconnect(),
		invalidate: [queryKeys.configure.analytics()],
		successMessage: "Disconnected from Google Analytics",
		errorMessage: "Disconnect failed",
		onSuccess: () => {
			setConfirmDisconnect(false);
		},
	});

	const uploadMutation = useToastMutation({
		mutationFn: (file: File) => configureApi.analytics.uploadCredentials(file),
		successMessage: "Service-account key uploaded",
		errorMessage: "Could not read that key file",
		onSuccess: (fresh) => {
			onStatus(fresh);
			setPropertyId(fresh.property_id || "");
		},
	});

	const verifyMutation = useToastMutation({
		mutationFn: () => configureApi.analytics.setProperty(propertyId.trim()),
		successMessage: "Property ID verified",
		errorMessage: "Could not verify that property ID",
		onSuccess: (fresh) => {
			onStatus(fresh);
		},
	});

	// Credentials are uploaded once the service-account email comes back, even
	// though `verified` stays false until the property ID is confirmed.
	const hasCredentials = !!detailQ.data?.service_account;

	return (
		<ConfigureLayout
			title="Analytics"
			sub="Google Analytics 4 service-account hookup that powers the dashboard's traffic chart."
			query={detailQ}
		>
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

							<Button
								variant="dangerGhost"
								className="mt-4"
								onClick={() => setConfirmDisconnect(true)}
								icon={<Unplug size={13} />}
							>
								Disconnect
							</Button>
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

										<Button
											variant="primary"
											className="mb-px"
											disabled={!hasCredentials || !propertyId.trim()}
											onClick={() => verifyMutation.mutate()}
											loading={verifyMutation.isPending}
											loadingLabel="Verifying…"
										>
											Verify
										</Button>
									</div>
								</li>
							</ol>

							{hasCredentials && (
								<Button
									variant="dangerGhost"
									className="mt-5"
									onClick={() => setConfirmDisconnect(true)}
									icon={<Unplug size={13} />}
								>
									Start over
								</Button>
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
