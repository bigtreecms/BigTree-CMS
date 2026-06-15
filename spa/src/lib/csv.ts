/**
 * Serialize tabular data to an RFC 4180 CSV string and trigger a browser
 * download. The legacy admin streams CSV from PHP; the SPA serializes the rows
 * client-side because it already has them in hand.
 *
 * A UTF-8 BOM is prepended so Excel opens accented characters correctly, and
 * every cell is quoted with embedded `"` doubled.
 */

export const downloadCsv = (filename: string, header: string[], rows: (string | number)[][]) => {
	const lines = [header, ...rows].map((cells) =>
		cells.map((cell) => csvCell(String(cell))).join(",")
	);
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

/**
 * Encode a single already-stringified cell value as an RFC 4180 quoted field,
 * neutralizing CSV / formula injection.
 *
 * If the value starts with a formula trigger character (`=`, `+`, `-`, `@`, or
 * the control chars tab / carriage-return), a single apostrophe is prepended so
 * spreadsheet apps (Excel, LibreOffice, Google Sheets) treat the cell as text
 * rather than executing it as a formula. Embedded `"` are then doubled and the
 * field is wrapped in quotes.
 *
 * This is the single shared encoder; every CSV/TSV exporter must route through
 * it rather than re-implementing the quoting.
 */
export const csvCell = (value: string): string => {
	const neutralized = /^[=+\-@\t\r]/.test(value) ? `'${value}` : value;

	return `"${neutralized.replace(/"/g, '""')}"`;
};
