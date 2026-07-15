import { Component, type ErrorInfo, type ReactNode } from "react";
import { RotateCcw } from "lucide-react";

import { Button } from "./Button";

/**
 * Catches render-time crashes in the active route so a thrown component
 * doesn't blank the whole app — the shell (TopBar/TabNav) stays mounted and
 * the user can navigate away. `resetKey` is fed the current pathname so a
 * navigation clears a stuck error without a full reload.
 */
interface ErrorBoundaryProps {
	children: ReactNode;
	resetKey?: string;
}

interface ErrorBoundaryState {
	error: Error | null;
}

export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
	state: ErrorBoundaryState = { error: null };

	static getDerivedStateFromError(error: Error): ErrorBoundaryState {
		return { error };
	}

	componentDidCatch(error: Error, info: ErrorInfo) {
		console.error("Route render error:", error, info.componentStack);
	}

	componentDidUpdate(prev: ErrorBoundaryProps) {
		if (this.state.error && prev.resetKey !== this.props.resetKey) {
			this.setState({ error: null });
		}
	}

	render() {
		const { error } = this.state;

		if (error) {
			return (
				<div className="mx-auto max-w-screen-2xl px-6 py-8">
					<div className="rounded-md border border-danger/30 bg-danger-bg p-4 text-[13px]">
						<div className="mb-1 font-semibold text-danger">Something went wrong</div>
						<div className="text-text-2">
							This screen hit an unexpected error and couldn't render.
						</div>
						<pre className="mt-3 max-h-40 overflow-auto rounded bg-surface-2 p-2 font-mono text-[11.5px] text-text-3">
							{error.message}
						</pre>
						<Button
							className="mt-3"
							icon={<RotateCcw size={13} />}
							variant="secondary"
							onClick={() => this.setState({ error: null })}
						>
							Try again
						</Button>
					</div>
				</div>
			);
		}

		return this.props.children;
	}
}
