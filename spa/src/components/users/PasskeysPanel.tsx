import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Fingerprint, Key, Plus, Trash } from "lucide-react";

import { passkeysApi, type PasskeyRecord } from "@/auth/endpoints";
import { isWebAuthnSupported } from "@/lib/webauthn";

import { Button } from "@/components/ui/Button";
import { Loading } from "@/components/ui/Loading";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { Card } from "@/components/ui/Card";

import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { InlineEmpty } from "@/components/ui/InlineEmpty";

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

const PASSKEYS_QUERY_KEY = ["auth", "passkeys"] as const;

export const PasskeysPanel = () => {
	const queryClient = useQueryClient();
	const supported = isWebAuthnSupported();
	const [pendingDelete, setPendingDelete] = useState<PasskeyRecord | null>(null);
	const [showAddPrompt, setShowAddPrompt] = useState(false);
	const [draftName, setDraftName] = useState("");

	const passkeysQuery = useQuery({
		queryKey: PASSKEYS_QUERY_KEY,
		queryFn: () => passkeysApi.list(),
	});

	const registerMutation = useMutation({
		mutationFn: (name: string) => passkeysApi.register(name),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: PASSKEYS_QUERY_KEY });
			toast.success("Passkey registered");
			setShowAddPrompt(false);
			setDraftName("");
		},
		onError: (err: unknown) => {
			toast.error(describePasskeyError(err, "Could not register passkey"));
		},
	});

	const deleteMutation = useMutation({
		mutationFn: (passkey: PasskeyRecord) => passkeysApi.delete(passkey.id),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: PASSKEYS_QUERY_KEY });
			setPendingDelete(null);
			toast.success("Passkey removed");
		},
		onError: () => {
			toast.error("Could not remove passkey");
		},
	});

	const handleRegister = () => {
		const name = draftName.trim() || guessDefaultName();
		registerMutation.mutate(name);
	};

	return (
		<Card>
			<header className="flex items-center justify-between border-b border-border bg-surface-2 px-4 py-3">
				<div className="flex items-center gap-2">
					<Key size={14} className="text-text-3" />
					<h3 className="text-[12.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
						Passkeys
					</h3>
				</div>
				{supported && !showAddPrompt && (
					<Button
						variant="secondary"
						icon={<Plus size={13} />}
						onClick={() => {
							setShowAddPrompt(true);
							setDraftName(guessDefaultName());
						}}
						disabled={registerMutation.isPending}
					>
						Add passkey
					</Button>
				)}
			</header>

			<div className="p-4">
				{!supported && (
					<InlineEmpty pad="md">
						Your browser doesn't expose the WebAuthn API. Passkeys won't work here.
					</InlineEmpty>
				)}

				{supported && showAddPrompt && (
					<div className="mb-4 rounded-md border border-border bg-surface-2 p-3">
						<label className="block">
							<span className="mb-1 block text-[12px] font-medium text-text-2">
								Passkey name
							</span>
							<input
								type="text"
								className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
								value={draftName}
								onChange={(e) => setDraftName(e.target.value)}
								placeholder="e.g. MacBook Touch ID, YubiKey 5"
								autoFocus
								disabled={registerMutation.isPending}
							/>
							<span className="mt-1 block text-[11.5px] text-text-3">
								Just for your reference — pick a name you'll recognise later.
							</span>
						</label>
						<div className="mt-3 flex justify-end gap-2">
							<Button
								variant="secondary"
								onClick={() => {
									setShowAddPrompt(false);
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
								<div className="min-w-0">
									<div className="truncate text-text-2">{p.name}</div>
									<div className="truncate text-[11px] text-text-3">
										Added {p.created_at}
									</div>
								</div>
								<div className="truncate text-[11.5px] text-text-3">
									{p.last_used ? `Last used ${p.last_used}` : "Never used"}
								</div>
								<button
									type="button"
									className="rounded p-1 text-text-3 hover:bg-hover hover:text-danger"
									onClick={() => setPendingDelete(p)}
									aria-label="Remove passkey"
									title="Remove passkey"
								>
									<Trash size={13} />
								</button>
							</li>
						))}
					</ul>
				)}
			</div>

			{pendingDelete && (
				<ConfirmDialog
					open={true}
					onOpenChange={(open) => {
						if (!open) {
							setPendingDelete(null);
						}
					}}
					title={`Remove “${pendingDelete.name}”?`}
					description="You won't be able to sign in with this passkey anymore. Other sign-in methods continue to work."
					confirmLabel="Remove passkey"
					variant="danger"
					onConfirm={() => deleteMutation.mutate(pendingDelete)}
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

const describePasskeyError = (err: unknown, fallback: string): string => {
	if (err instanceof DOMException) {
		if (err.name === "NotAllowedError") {
			return "Cancelled — no passkey was registered.";
		}

		if (err.name === "InvalidStateError") {
			return "This authenticator already has a passkey for this account.";
		}

		if (err.name === "NotSupportedError") {
			return "Your authenticator doesn't support the required passkey settings.";
		}
	}

	if (err instanceof ApiError) {
		return err.message || fallback;
	}

	if (err instanceof Error) {
		return err.message || fallback;
	}

	return fallback;
};
