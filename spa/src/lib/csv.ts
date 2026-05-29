/**
 * Serialize tabular data to an RFC 4180 CSV string and trigger a browser
 * download. The legacy admin streams CSV from PHP; the SPA serializes the rows
 * client-side because it already has them in hand.
 *
 * A UTF-8 BOM is prepended so Excel opens accented characters correctly, and
 * every cell is quoted with embedded `"` doubled.
 */

export const downloadCsv = (filename: string, header: string[], rows: (string | number)[][]) => {
	const lines = [header, ...rows].map((cells) => cells.map(csvCell).join(","));
	const csv = lines.join("\r\n");
	const blob = new Blob(["﻿" + csv], { type: "text/csv;charset=utf-8" });
	const url = URL.createObjectURL(blob);
	const a = document.createElement("a");

	a.href = url;
	a.download = filename;
	document.body.appendChild(a);
	a.click();
	document.body.removeChild(a);
	URL.revokeObjectURL(url);
};

const csvCell = (value: string | number): string => {
	return `"${String(value).replace(/"/g, '""')}"`;
};
