import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { useOAuthRedirectResult } from "@/hooks/useOAuthRedirectResult";
import { useToastMutation } from "@/hooks/useToastMutation";
import { CheckCircle2, Unplug } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { Button } from "@/components/ui/Button";
import { Checkbox } from "@/components/ui/Checkbox";
import { DisclosureToggle } from "@/components/ui/DisclosureToggle";
import { TextInput } from "@/components/ui/TextInput";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { Field } from "@/components/ui/Field";
import { Card } from "@/components/ui/Card";

import { configureApi, type ServiceCredentials } from "@/api/endpoints/configure";

import { toast } from "@/lib/toast";
import { queryKeys } from "@/lib/queryKeys";

interface ServiceMeta {
	id: string;
	keyLabel: string;
	label: string;
	secretLabel: string;
	testEnv: boolean;
}

const SERVICES: ServiceMeta[] = [
	{
		id: "twitter",
		label: "Twitter / X",
		keyLabel: "API Key",
		secretLabel: "API Secret",
		testEnv: false,
	},
	{
		id: "instagram",
		label: "Instagram",
		keyLabel: "Client ID",
		secretLabel: "Client Secret",
		testEnv: false,
	},
	{
		id: "youtube",
		label: "YouTube",
		keyLabel: "Client ID",
		secretLabel: "Client Secret",
		testEnv: false,
	},
	{
		id: "flickr",
		label: "Flickr",
		keyLabel: "API Key",
		secretLabel: "API Secret",
		testEnv: false,
	},
	{
		id: "salesforce",
		label: "Salesforce",
		keyLabel: "Consumer Key",
		secretLabel: "Consumer Secret",
		testEnv: true,
	},
	{
		id: "disqus",
		label: "Disqus",
		keyLabel: "API Key",
		secretLabel: "API Secret",
		testEnv: false,
	},
	{
		id: "facebook",
		label: "Facebook",
		keyLabel: "App ID",
		secretLabel: "App Secret",
		testEnv: false,
	},
];

type Draft = ServiceCredentials;

export const ConfigureServices = () => {
	const detailQ = useQuery({
		queryKey: queryKeys.configure.services(),
		queryFn: () => configureApi.services.list(),
	});

	const disconnectDialog = useConfirmDialog<string>();
	const [expanded, setExpanded] = useState<string | null>(null);
	const [drafts, setDrafts] = useState<Record<string, Draft>>({});

	useOAuthRedirectResult({
		onConnected: (service) => toast.success(`Connected ${service}`),
		onError: (code) =>
			toast.error(
				code === "oauth_failed"
					? "The provider rejected the connection."
					: "Connection failed."
			),
		invalidate: queryKeys.configure.services(),
	});

	const disconnectMutation = useToastMutation({
		mutationFn: (service: string) => configureApi.services.disconnect(service),
		invalidate: [queryKeys.configure.services()],
		successMessage: "Service disconnected",
		errorMessage: "Disconnect failed",
		onSuccess: () => {
			disconnectDialog.close();
		},
	});

	const connectMutation = useToastMutation({
		mutationFn: ({ service, body }: { service: string; body: Draft }) =>
			configureApi.services.startOAuth(service, body),
		errorMessage: "Could not start the connection",
		onSuccess: ({ launch_url }) => {
			// Full-page navigation into the provider handshake; it returns to this screen.
			window.location.href = launch_url;
		},
	});

	// Current draft for a service: edits in local state, falling back to the
	// stored key/scope/test-env (secret always starts blank).
	const draftFor = (id: string): Draft => {
		const stored = detailQ.data?.[id];

		return (
			drafts[id] ?? {
				key: stored?.key ?? "",
				secret: "",
				scope: stored?.scope ?? "",
				test_environment: stored?.test_environment ?? false,
			}
		);
	};

	const setDraft = (id: string, patch: Partial<Draft>) =>
		setDrafts((prev) => ({ ...prev, [id]: { ...draftFor(id), ...patch } }));

	return (
		<ConfigureLayout
			query={detailQ}
			sub="Third-party social / business integrations. Enter each provider's credentials and connect — the OAuth handshake returns you here."
			title="Services"
		>
			{detailQ.data && (
				<div className="space-y-3">
					{SERVICES.map((s) => {
						const entry = detailQ.data[s.id];

						if (!entry) {
							return null;
						}

						const isOpen = expanded === s.id;
						const draft = draftFor(s.id);

						return (
							<Card key={s.id}>
								<div className="flex items-center justify-between p-4">
									<div className="min-w-0">
										<div className="text-[13px] font-semibold text-text">
											{s.label}
										</div>

										{entry.connected ? (
											<div className="mt-1 flex items-center gap-2 text-[12px] text-accent">
												<CheckCircle2 size={12} />
												<span className="truncate">
													Connected
													{entry.identity ? ` — ${entry.identity}` : ""}
												</span>
											</div>
										) : (
											<div className="mt-1 text-[12px] text-text-3">
												Not connected
											</div>
										)}
									</div>

									<div className="flex items-center gap-2">
										<DisclosureToggle
											className="gap-1.5 rounded-md border border-border bg-surface-2 px-3 py-1.5 text-[12.5px] font-medium text-text hover:bg-hover"
											label={entry.connected ? "Reconnect" : "Connect"}
											open={isOpen}
											onToggle={() => setExpanded(isOpen ? null : s.id)}
										/>

										{entry.connected && (
											<Button
												icon={<Unplug size={13} />}
												variant="dangerGhost"
												onClick={() => disconnectDialog.open(s.id)}
											>
												Disconnect
											</Button>
										)}
									</div>
								</div>

								{isOpen && (
									<div className="space-y-3 border-t border-border p-4">
										<div className="grid grid-cols-1 gap-3 md:grid-cols-2">
											<Field label={s.keyLabel}>
												<TextInput
													autoComplete="off"
													value={draft.key}
													onChange={(e) =>
														setDraft(s.id, { key: e.target.value })
													}
												/>
											</Field>
											<Field label={s.secretLabel}>
												<TextInput
													autoComplete="off"
													placeholder={
														entry.has_secret
															? "•••••••• (stored, leave blank to keep)"
															: ""
													}
													type="password"
													value={draft.secret}
													onChange={(e) =>
														setDraft(s.id, { secret: e.target.value })
													}
												/>
											</Field>
										</div>

										{entry.uses_scope && (
											<Field label="Scope">
												<TextInput
													value={draft.scope ?? ""}
													onChange={(e) =>
														setDraft(s.id, { scope: e.target.value })
													}
												/>
											</Field>
										)}

										{s.testEnv && (
											<Checkbox
												checked={!!draft.test_environment}
												label="Use the test / sandbox environment"
												onChange={(test_environment) =>
													setDraft(s.id, { test_environment })
												}
											/>
										)}

										<Button
											disabled={
												connectMutation.isPending ||
												!draft.key.trim() ||
												(!draft.secret.trim() && !entry.has_secret)
											}
											variant="primary"
											onClick={() =>
												connectMutation.mutate({
													service: s.id,
													body: draft,
												})
											}
										>
											{connectMutation.isPending
												? "Starting…"
												: "Save & connect"}
										</Button>
									</div>
								)}
							</Card>
						);
					})}
				</div>
			)}

			{disconnectDialog.item && (
				<ConfirmDialog
					{...disconnectDialog.dialogProps}
					confirmLabel="Disconnect"
					description="Any module fields that pull from this service will stop working until reconnected."
					title="Disconnect this service?"
					variant="danger"
					onConfirm={() => disconnectMutation.mutate(disconnectDialog.item!)}
				/>
			)}
		</ConfigureLayout>
	);
};
