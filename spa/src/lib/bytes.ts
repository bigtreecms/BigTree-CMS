/**
 * Human-readable byte count. Mirrors the PHP admin's BigTree::formatBytes
 * output ("12.4 MB"). Returns "—" for null/undefined/0 so it can be dropped
 * straight into a table cell.
 */
export const formatBytes = (n?: number | null): string => {
	if (!n) {
		return "—";
	}

	if (n < 1024) {
		return `${n} B`;
	}

	if (n < 1024 * 1024) {
		return `${(n / 1024).toFixed(1)} KB`;
	}

	if (n < 1024 * 1024 * 1024) {
		return `${(n / (1024 * 1024)).toFixed(1)} MB`;
	}

	return `${(n / (1024 * 1024 * 1024)).toFixed(1)} GB`;
};
