import type { ReactNode } from "react";

interface AuthCardProps {
	children: ReactNode;
	subtitle: string;
	title: string;
	/** Wider card for multi-step flows (e.g. 2FA enrollment). Default is 360px. */
	wide?: boolean;
}

/**
 * The centered card layout shared by the unauthenticated screens (login,
 * forgot / reset password). One surface for the whole auth flow.
 */
export const AuthCard = ({ title, subtitle, wide = false, children }: AuthCardProps) => (
	<div className="grid min-h-screen place-items-center bg-bg px-4">
		<div
			className={`w-full rounded-lg border border-border bg-surface p-6 shadow-md ${
				wide ? "max-w-[520px]" : "max-w-[360px]"
			}`}
		>
			<div className="mb-5 flex items-center gap-2.5">
				<div className="grid size-8 place-items-center rounded-md bg-accent text-accent-fg">
					<svg fill="currentColor" height="16" viewBox="0 0 24 24" width="16">
						<path d="M12 2 4 12h4v8h8v-8h4L12 2Z" />
					</svg>
				</div>
				<div>
					<h1 className="text-[15px] font-semibold tracking-[-0.01em]">{title}</h1>
					<p className="text-[12px] text-text-3">{subtitle}</p>
				</div>
			</div>

			{children}
		</div>
	</div>
);
