import type { ReactNode } from "react";

interface AuthCardProps {
	title: string;
	subtitle: string;
	children: ReactNode;
}

/**
 * The centered card layout shared by the unauthenticated screens (forgot /
 * reset password). Mirrors the login screen's framing so the flows feel like
 * one surface.
 */
export const AuthCard = ({ title, subtitle, children }: AuthCardProps) => (
	<div className="grid min-h-screen place-items-center bg-bg px-4">
		<div className="w-full max-w-[360px] rounded-lg border border-border bg-surface p-6 shadow-md">
			<div className="mb-5 flex items-center gap-2.5">
				<div className="grid h-8 w-8 place-items-center rounded-md bg-accent text-accent-fg">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
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
