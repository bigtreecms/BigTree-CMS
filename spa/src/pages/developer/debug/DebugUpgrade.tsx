import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { AlertTriangle, CheckCircle2, Download, Loader2 } from "lucide-react";

import { DebugLayout } from "@/components/developer/DebugLayout";
import { Field } from "@/components/ui/Field";
import { ErrorPanel } from "@/components/ui/ErrorPanel";

import {
	systemApi,
	type UpgradeAvailable,
	type UpgradeCheck,
	type UpgradeMethod,
} from "@/api/endpoints/system";
import { ApiError } from "@/types/api";

const inputClass =
	"w-full rounded-md border border-border bg-surface px-3 py-2 text-[13px] outline-none focus:border-accent focus:ring-2 focus:ring-accent-ring";

const primaryBtn =
	"inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover disabled:opacity-60";

type Stage =
	| "idle"
	| "downloading"
	| "credentials"
	| "ftp_root"
	| "installing"
	| "migrating"
	| "complete"
	| "error";

const errMessage = (err: unknown, fallback: string): string => {
	if (err instanceof ApiError && err.message) {
		return err.message;
	}

	if (err instanceof Error && err.message) {
		return err.message;
	}

	return fallback;
};

export const DebugUpgrade = () => {
	const checkQ = useQuery({
		queryKey: ["system", "upgrade", "check"],
		queryFn: () => systemApi.upgrade.check(),
	});

	const [stage, setStage] = useState<Stage>("idle");
	const [method, setMethod] = useState<UpgradeMethod | null>(null);
	const [username, setUsername] = useState("");
	const [password, setPassword] = useState("");
	const [ftpRoot, setFtpRoot] = useState("");
	const [badRoot, setBadRoot] = useState<string | null>(null);
	const [log, setLog] = useState<string[]>([]);
	const [error, setError] = useState<string | null>(null);

	const appendLog = (line: string) => {
		setLog((prev) => (line && prev[prev.length - 1] !== line ? [...prev, line] : prev));
	};

	/**
	 * Drives the migration queue exactly the way the legacy scripts.php loop did:
	 * run each script, follow its paging signal (an explicit `pages` count or an
	 * incomplete response that wants the next page), then move to the next script.
	 */
	const runMigrations = async () => {
		setStage("migrating");

		const { queue } = await systemApi.upgrade.migrations();

		if (queue.length === 0) {
			appendLog("Database is already up to date.");
			setStage("complete");

			return;
		}

		const runScript = async (script: string, page?: number, totalPages?: number) => {
			const res = await systemApi.upgrade.migrate(script, page, totalPages);

			if (res.error) {
				throw new Error(res.error);
			}

			if (res.response) {
				appendLog(res.response);
			}

			if (res.complete) {
				return;
			}

			if (res.pages) {
				await runScript(script, 1, res.pages);

				return;
			}

			await runScript(script, (page ?? 0) + 1, totalPages);
		};

		for (const script of queue) {
			await runScript(script);
		}

		appendLog("Upgrade complete.");
		setStage("complete");
	};

	const install = async (body: { ftp_username?: string; ftp_password?: string; ftp_root?: string }) => {
		setStage("installing");
		const res = await systemApi.upgrade.install(body);

		if (res.needs_credentials) {
			setStage("credentials");

			return;
		}

		if (res.needs_ftp_root) {
			setBadRoot(res.bad_root ?? null);
			setStage("ftp_root");

			return;
		}

		await runMigrations();
	};

	const start = async (update: UpgradeAvailable) => {
		if (update.type === "major") {
			return;
		}

		setError(null);
		setLog([]);
		setStage("downloading");

		try {
			const dl = await systemApi.upgrade.download(update.type);
			setMethod(dl.method);

			if (dl.needs_credentials) {
				setStage("credentials");

				return;
			}

			await install({});
		} catch (err) {
			setError(errMessage(err, "Upgrade failed"));
			setStage("error");
		}
	};

	const submitCredentials = async (e: React.FormEvent) => {
		e.preventDefault();
		setError(null);

		try {
			await install({ ftp_username: username, ftp_password: password });
		} catch (err) {
			setError(errMessage(err, "Install failed"));
			setStage("error");
		}
	};

	const submitFtpRoot = async (e: React.FormEvent) => {
		e.preventDefault();
		setError(null);

		try {
			await install({ ftp_username: username, ftp_password: password, ftp_root: ftpRoot });
		} catch (err) {
			setError(errMessage(err, "Install failed"));
			setStage("error");
		}
	};

	const data = checkQ.data;
	const busy = stage === "downloading" || stage === "installing" || stage === "migrating";

	return (
		<DebugLayout
			title="System upgrade"
			sub="Check for new BigTree releases and run the core + database upgrade."
		>
			{checkQ.isLoading && <p className="text-[12.5px] text-text-3">Checking for updates…</p>}

			{checkQ.error && <ErrorPanel error={checkQ.error} />}

			{data && (
				<>
					<section className="mb-5 overflow-hidden rounded-xl border border-border bg-surface">
						<header className="border-b border-border bg-surface-2 px-4 py-2.5 text-[11.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
							Current install
						</header>

						<dl className="divide-y divide-border">
							<div className="flex items-center justify-between px-4 py-2.5">
								<dt className="text-[12.5px] text-text-2">BigTree version</dt>
								<dd className="font-mono text-[12.5px] tabular-nums text-text">
									{data.current_version || "—"} (rev {data.current_revision})
								</dd>
							</div>
							<div className="flex items-center justify-between px-4 py-2.5">
								<dt className="text-[12.5px] text-text-2">Install method</dt>
								<dd className="font-mono text-[12.5px] text-text">
									{data.method ?? "none writable"}
								</dd>
							</div>
						</dl>
					</section>

					{data.config_ignored && (
						<div className="mb-4 flex items-start gap-2 rounded-lg border border-warn/40 bg-warn-bg/30 p-3 text-[12.5px] text-text-2">
							<AlertTriangle size={15} className="mt-0.5 shrink-0 text-warn" />
							Updates are disabled in this install's configuration
							(<code>ignore_admin_updates</code>).
						</div>
					)}

					{!data.method && (
						<div className="mb-4 flex items-start gap-2 rounded-lg border border-warn/40 bg-warn-bg/30 p-3 text-[12.5px] text-text-2">
							<AlertTriangle size={15} className="mt-0.5 shrink-0 text-warn" />
							The web server can't write to <code>/core/</code> via local, FTP, or
							SFTP. Automatic upgrades are unavailable — you'll need to upgrade
							manually.
						</div>
					)}

					{stage === "idle" && (
						<UpdateList data={data} onInstall={start} />
					)}

					{busy && (
						<div className="flex items-center gap-2 rounded-lg border border-border bg-surface p-4 text-[12.5px] text-text-2">
							<Loader2 size={15} className="animate-spin text-accent" />
							{stage === "downloading" && "Downloading the update…"}
							{stage === "installing" && "Backing up and installing the new core…"}
							{stage === "migrating" && "Running database migrations…"}
						</div>
					)}

					{(stage === "credentials" || stage === "ftp_root") && (
						<section className="overflow-hidden rounded-xl border border-border bg-surface">
							<header className="border-b border-border bg-surface-2 px-4 py-2.5 text-[11.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
								{method} credentials
							</header>

							<div className="p-4">
								{stage === "credentials" ? (
									<form className="max-w-sm space-y-3" onSubmit={submitCredentials}>
										<p className="text-[12.5px] text-text-3">
											The server can't write to <code>/core/</code> directly.
											Enter your {method} credentials so BigTree can install the
											update. Your existing core and database are backed up to
											<code> /backups/</code> first.
										</p>
										<Field label={`${method} username`}>
											<input
												className={inputClass}
												autoComplete="off"
												value={username}
												onChange={(e) => setUsername(e.target.value)}
											/>
										</Field>
										<Field label={`${method} password`}>
											<input
												type="password"
												className={inputClass}
												autoComplete="off"
												value={password}
												onChange={(e) => setPassword(e.target.value)}
											/>
										</Field>
										<button type="submit" className={primaryBtn} disabled={!username}>
											Install
										</button>
									</form>
								) : (
									<form className="max-w-sm space-y-3" onSubmit={submitFtpRoot}>
										<p className="text-[12.5px] text-text-3">
											BigTree couldn't find the install directory automatically.
											Enter the full {method} path to the directory that contains
											<code> /core/</code>.
										</p>
										{badRoot && (
											<p className="text-[12px] text-danger">
												No BigTree install found in <code>{badRoot}</code>.
											</p>
										)}
										<Field label={`${method} path`}>
											<input
												className={inputClass}
												value={ftpRoot}
												onChange={(e) => setFtpRoot(e.target.value)}
											/>
										</Field>
										<button type="submit" className={primaryBtn} disabled={!ftpRoot}>
											Set directory & install
										</button>
									</form>
								)}
							</div>
						</section>
					)}

					{(stage === "migrating" || stage === "complete") && log.length > 0 && (
						<pre className="mt-4 max-h-64 overflow-auto rounded-lg border border-border bg-surface-2 p-3 text-[12px] leading-6 text-text-2">
							{log.join("\n")}
						</pre>
					)}

					{stage === "complete" && (
						<div className="mt-4 flex items-center gap-2 rounded-lg border border-success/40 bg-surface p-4 text-[13px] font-medium text-text">
							<CheckCircle2 size={16} className="text-success" />
							Upgrade complete. Reload the admin to pick up the new version.
						</div>
					)}

					{stage === "error" && error && (
						<div className="mt-4 rounded-md border border-danger/30 bg-danger-bg p-4 text-[13px]">
							<div className="mb-1 font-semibold text-danger">Upgrade failed</div>
							<div className="text-text-2">{error}</div>
						</div>
					)}
				</>
			)}
		</DebugLayout>
	);
};

interface UpdateListProps {
	data: UpgradeCheck;
	onInstall: (update: UpgradeAvailable) => void;
}

const UpdateList = ({ data, onInstall }: UpdateListProps) => {
	if (data.updates.length === 0) {
		return (
			<div className="flex items-center gap-2 rounded-lg border border-border bg-surface p-4 text-[12.5px] text-text-3">
				<CheckCircle2 size={14} className="text-success" />
				You're running the latest release. No updates are available.
			</div>
		);
	}

	return (
		<div className="space-y-3">
			{data.updates.map((update) => (
				<div
					key={update.type + update.version}
					className="flex items-start justify-between gap-4 rounded-xl border border-border bg-surface p-4"
				>
					<div className="min-w-0">
						<div className="text-[13.5px] font-semibold text-text">
							BigTree {update.version}
							{update.release_date && (
								<span className="ml-2 text-[12px] font-normal text-text-3">
									released {update.release_date}
								</span>
							)}
						</div>
						<p className="mt-0.5 text-[12px] text-text-3">{update.note}</p>
					</div>

					{update.installable ? (
						<button
							type="button"
							onClick={() => onInstall(update)}
							className="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover"
						>
							<Download size={13} />
							Install {update.version}
						</button>
					) : (
						<span className="shrink-0 self-center rounded-md border border-border px-2.5 py-1 text-[11.5px] text-text-3">
							Manual install only
						</span>
					)}
				</div>
			))}
		</div>
	);
};
