import { Eye, Pencil, Send, Trash } from "lucide-react";

import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { SectionLabel } from "@/components/ui/SectionLabel";
import type { PageDetail } from "@/api/endpoints/pages";
import { useAuthStore } from "@/auth/store";
import { draftOwnerLabel } from "@/lib/fieldComparison";
import { canPublishPage } from "@/lib/permissions";
import { openPreviewWindow, showPreview } from "@/lib/preview";
import { pageEditPath } from "@/lib/routes";

interface CurrentDraftCardProps {
	discarding: boolean;
	onDiscard: () => void;
	onPublish: () => void;
	/** The page as loaded, with its queued draft overlaid. */
	page: PageDetail;
	publishing: boolean;
}

/**
 * The page's queued draft, at the top of its revisions: who it belongs to and
 * when it was last saved, with edit, preview, publish and discard. A revision
 * loaded with "Restore" lands here, to be reviewed before it goes live.
 */
export const CurrentDraftCard = ({
	discarding,
	onDiscard,
	onPublish,
	page,
	publishing,
}: CurrentDraftCardProps) => {
	const currentUserId = useAuthStore((s) => s.user?.id);
	const canPublish = canPublishPage(page.access);
	const previewUrl = page.preview_url;

	return (
		<Card className="mb-4 p-4">
			<SectionLabel as="h2" className="mb-2" size="md">
				Current draft
			</SectionLabel>
			<div className="flex flex-wrap items-center justify-between gap-3">
				<div className="text-[12.5px] text-text-2">
					{draftOwnerLabel(page.pending_owner, page.pending_owner_name, currentUserId)}
					<span className="text-text-3">, last saved {page.updated_at}</span>
				</div>
				<div className="flex flex-wrap gap-2">
					<Button icon={<Pencil size={13} />} to={pageEditPath(page.id)}>
						Edit draft
					</Button>
					{previewUrl && (
						<Button
							icon={<Eye size={13} />}
							onClick={() => void showPreview(openPreviewWindow(), previewUrl)}
						>
							Preview
						</Button>
					)}
					{canPublish && (
						<Button
							icon={<Send size={13} />}
							loading={publishing}
							loadingLabel="Publishing…"
							variant="primary"
							onClick={onPublish}
						>
							Publish draft
						</Button>
					)}
					{canPublish && (
						<Button
							icon={<Trash size={13} />}
							loading={discarding}
							loadingLabel="Discarding…"
							variant="dangerGhost"
							onClick={onDiscard}
						>
							Discard draft
						</Button>
					)}
				</div>
			</div>
		</Card>
	);
};
