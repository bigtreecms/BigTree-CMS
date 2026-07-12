import { useState } from "react";
import { useConfirmDialog } from "@/hooks/useConfirmDialog";
import { useInlineForm } from "@/hooks/useInlineForm";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useToastMutation } from "@/hooks/useToastMutation";
import { Fingerprint, Key, Plus, Trash } from "lucide-react";

import { passkeysApi, type PasskeyRecord } from "@/auth/endpoints";
import { isWebAuthnSupported } from "@/lib/webauthn";
import { queryKeys } from "@/lib/queryKeys";

import { Button } from "@/components/ui/Button";
import { Loading } from "@/components/ui/Loading";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { Card, CardHeader } from "@/components/ui/Card";
import { NameIdCell } from "@/components/ui/NameIdCell";

import { describeWebAuthnError } from "@/lib/errorHandling";
import { toast } from "@/lib/toast";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { IconButton } from "@/components/ui/IconButton";
import { Field } from "@/components/ui/Field";
import { SectionLabel } from "@/components/ui/SectionLabel";
import { TextInput } from "@/components/ui/TextInput";

/**
 * Profile → Security passkey manager.
 *
 *   - Lists the current user's registered passkeys (name, created, last used)
 *   - "Add passkey" button: prompts for a friendly name, then runs the full
 *     WebAuthn register flow via passkeysApi.register()
 *   - Delete button per row, gated behind a ConfirmDialog
 *
 * Error states (user cancels, no authenticator, server rejects, browser
 * doesn't support WebAuthn) are routed to toasts with specific copy so the
 * user doesn't get a generic "something went wrong".
 */

export const PasskeysPanel = () => {
	const queryClient = useQueryClient();
	const supported = isWebAuthnSupported();
	const deleteDialog = useConfirmDialog<PasskeyRecord>();
	const addPrompt = useInlineForm();
	const [draftName, setDraftName] = useState("");

	const passkeysQuery = useQuery({
		queryKey: queryKeys.auth.passkeys(),
		queryFn: () => passkeysApi.list(),
	});

	const registerMutation = useMutation({
		mutationFn: (name: string) => passkeysApi.register(name),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: queryKeys.auth.passkeys() });
			toast.success("Passkey registered");
			addPrompt.hide();
			setDraftName("");
		},
		onError: (err: unknown) => {
			toast.error(describeWebAuthnError(err, "Could not register passkey"));
		},
	});

	const deleteMutation = useToastMutation({
		mutationFn: (passkey: PasskeyRecord) => passkeysApi.delete(passkey.id),
		invalidate: [queryKeys.auth.passkeys()],
		successMessage: "Passkey removed",
		errorMessage: "Could not remove passkey",
		onSuccess: () => deleteDialog.close(),
	});

	const handleRegister = () => {
		const name = draftName.trim() || guessDefaultName();
		registerMutation.mutate(name);
	};

	return (
		<Card>
			<CardHeader className="flex items-center justify-between">
				<div className="flex items-center gap-2">
					<Key size={14} className="text-text-3" />
					<SectionLabel as="h3">Passkeys</SectionLabel>
				</div>
				{supported && !addPrompt.open && (
					<Button
						variant="secondary"
						icon={<Plus size={13} />}
						onClick={() => {
							addPrompt.show();
							setDraftName(guessDefaultName());
						}}
						disabled={registerMutation.isPending}
					>
						Add passkey
					</Button>
				)}
			</CardHeader>

			<div className="p-4">
				{!supported && (
					<InlineEmpty pad="md">
						Your browser doesn't expose the WebAuthn API. Passkeys won't work here.
					</InlineEmpty>
				)}

				{supported && addPrompt.open && (
					<div className="mb-4 rounded-md border border-border bg-surface-2 p-3">
						<Field label="Passkey name">
							<TextInput
								value={draftName}
								onChange={(e) => setDraftName(e.target.value)}
								placeholder="e.g. MacBook Touch ID, YubiKey 5"
								autoFocus
								disabled={registerMutation.isPending}
							/>
							<span className="mt-1 block text-[11.5px] text-text-3">
								Just for your reference — pick a name you'll recognise later.
							</span>
						</Field>
						<div className="mt-3 flex justify-end gap-2">
							<Button
								variant="secondary"
								onClick={() => {
									addPrompt.hide();
									setDraftName("");
								}}
								disabled={registerMutation.isPending}
							>
								Cancel
							</Button>
							<Button
								variant="primary"
								onClick={handleRegister}
								disabled={registerMutation.isPending}
							>
								{registerMutation.isPending
									? "Waiting for authenticator…"
									: "Register passkey"}
							</Button>
						</div>
					</div>
				)}

				{passkeysQuery.isLoading ? (
					<Loading />
				) : (passkeysQuery.data?.length ?? 0) === 0 ? (
					<InlineEmpty align="center" pad="xl">
						No passkeys registered yet. Adding one lets you sign in without a password.
					</InlineEmpty>
				) : (
					<ul className="divide-y divide-border overflow-hidden rounded-md border border-border">
						{passkeysQuery.data!.map((p) => (
							<li
								key={p.id}
								className="grid grid-cols-[auto_minmax(0,1fr)_minmax(0,1fr)_auto] items-center gap-3 px-3 py-2 text-[12.5px]"
							>
								<Fingerprint size={16} className="text-accent" />
								<NameIdCell
									name={p.name}
									primaryClassName="truncate text-text-2"
									subtitle={`Added ${p.created_at}`}
								/>
								<div className="truncate text-[11.5px] text-text-3">
									{p.last_used ? `Last used ${p.last_used}` : "Never used"}
								</div>
								<IconButton
									tone="danger"
									onClick={() => deleteDialog.open(p)}
									label="Remove passkey"
									title="Remove passkey"
								>
									<Trash size={13} />
								</IconButton>
							</li>
						))}
					</ul>
				)}
			</div>

			{deleteDialog.item && (
				<ConfirmDialog
					open={deleteDialog.isOpen}
					onOpenChange={(v) => {
						if (!v) deleteDialog.close();
					}}
					title={`Remove “${deleteDialog.item.name}”?`}
					description="You won't be able to sign in with this passkey anymore. Other sign-in methods continue to work."
					confirmLabel="Remove passkey"
					variant="danger"
					onConfirm={() => deleteMutation.mutate(deleteDialog.item!)}
				/>
			)}
		</Card>
	);
};

const guessDefaultName = (): string => {
	if (typeof navigator === "undefined" || !navigator.userAgent) {
		return "New passkey";
	}

	const ua = navigator.userAgent;

	if (/iPhone|iPad|iPod/.test(ua)) {
		return "iPhone / iPad";
	}

	if (/Android/.test(ua)) {
		return "Android device";
	}

	if (/Mac/.test(ua)) {
		return "Mac";
	}

	if (/Windows/.test(ua)) {
		return "Windows PC";
	}

	if (/Linux/.test(ua)) {
		return "Linux PC";
	}

	return "New passkey";
};
