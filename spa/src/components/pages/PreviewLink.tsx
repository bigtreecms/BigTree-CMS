import type { MouseEvent } from "react";
import { ExternalLink } from "lucide-react";

import { openPreviewWindow, showPreview } from "@/lib/preview";

interface PreviewLinkProps {
	url: string;
}

/**
 * A draft's front-end preview URL. A plain link would usually work, but the
 * preview route needs the admin PHP session, so a normal click goes through
 * showPreview (which waits for the session bridge first). Modified clicks and
 * "copy link" still get the real href.
 */
export const PreviewLink = ({ url }: PreviewLinkProps) => {
	const handleClick = (event: MouseEvent<HTMLAnchorElement>) => {
		if (
			event.button !== 0 ||
			event.metaKey ||
			event.ctrlKey ||
			event.shiftKey ||
			event.altKey
		) {
			return;
		}

		event.preventDefault();
		void showPreview(openPreviewWindow(), url);
	};

	return (
		<a
			className="inline-flex min-w-0 items-center gap-1 truncate text-[14px] font-medium text-accent hover:underline"
			href={url}
			rel="noopener noreferrer"
			target="_blank"
			title={url}
			onClick={handleClick}
		>
			<span className="truncate">{url}</span>
			<ExternalLink className="shrink-0" size={11} />
		</a>
	);
};
