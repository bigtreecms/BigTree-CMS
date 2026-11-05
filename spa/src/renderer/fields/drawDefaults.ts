/**
 * Draw-time field defaults.
 *
 * Legacy BigTree applied these settings only by pre-filling the editor control:
 * `default_today` on a date, `default_now` on a datetime, `default_checked` on a
 * checkbox. The field settings editor still offers all three, so the add screens
 * seed them here, in the same shape each control emits. The server seeds the same
 * settings for any create that omits the field (FieldDefaultDomain::implicitDefault),
 * which is the backstop for every writer that never draws a form.
 *
 * A list with `allow-empty: No` is the fourth such default. Its options load
 * asynchronously, so SelectField seeds it once they arrive.
 */

const pad = (n: number): string => String(n).padStart(2, "0");

const localDate = (now: Date): string =>
	`${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;

/**
 * The value a field's draw-time default puts in its control, or undefined when it
 * has none. An explicit `default` setting is the caller's to check first; it wins.
 */
export const drawTimeDefault = (
	type: string,
	settings: Record<string, unknown> | null | undefined,
	now: Date = new Date()
): unknown => {
	if (!settings) {
		return undefined;
	}

	if (type === "date" && settings.default_today) {
		return localDate(now);
	}

	if (type === "datetime" && settings.default_now) {
		return `${localDate(now)}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
	}

	if (type === "checkbox" && settings.default_checked) {
		return true;
	}

	return undefined;
};
