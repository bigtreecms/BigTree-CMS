/**
 * Default source a new local module field type starts from — a working text
 * input that looks like a native SPA field, reads a configured setting, and
 * demonstrates the contract (render → { update, destroy }, host.value /
 * host.onChange / host.field.settings). Settings themselves are built in the
 * "Settings" panel below the editor (stored in settings.js), not in code.
 */
export const MODULE_STARTER = `/**
 * Custom field type. Draw your input into host.element and call
 * host.onChange(next) whenever the value changes. Return { update, destroy }.
 *
 * Configured settings (built in the Settings panel) arrive as
 * host.field.settings — e.g. the "placeholder" setting below.
 */

// Tailwind classes for a default SPA text input — reuse so your field matches.
const INPUT_CLASS =
	"w-full rounded-md border border-border bg-surface px-3 py-2 text-[13.5px] " +
	"placeholder:text-text-3 focus:outline-none focus:ring-1 focus:ring-accent-ring " +
	"disabled:cursor-not-allowed disabled:opacity-60";

export default {
	contractVersion: 1,

	render(host) {
		const input = document.createElement("input");
		input.type = "text";
		input.className = INPUT_CLASS;
		input.value = host.value ?? "";
		input.disabled = host.disabled;
		input.placeholder = host.field.settings?.placeholder ?? "";
		input.addEventListener("input", () => host.onChange(input.value));
		host.element.appendChild(input);

		return {
			update(host) {
				if ((host.value ?? "") !== input.value) {
					input.value = host.value ?? "";
				}
				input.disabled = host.disabled;
				input.placeholder = host.field.settings?.placeholder ?? "";
			},
			destroy() {
				input.remove();
			},
		};
	},
};
`;

/** Settings a new module field type starts with — matches the starter's read of
 *  host.field.settings.placeholder. Built further in the Settings panel. */
export const MODULE_STARTER_SETTINGS = [
	{ id: "placeholder", control: "string" as const, label: "Placeholder text" },
];

/** Quick reference for the host API, shown beneath the editor. */
export const HOST_API_REFERENCE: Array<{ name: string; desc: string }> = [
	{ name: "host.element", desc: "The DOM node to render your input into." },
	{ name: "host.value", desc: "The current value (whatever you store)." },
	{ name: "host.field", desc: "Field config: column, title, settings, …" },
	{ name: "host.field.settings", desc: "Configured values for your settings." },
	{ name: "host.disabled", desc: "True when the form is read-only." },
	{ name: "host.onChange(next)", desc: "Push a new value up to the form." },
	{ name: "return.update(host)", desc: "Called when value/settings/disabled change." },
	{ name: "return.destroy()", desc: "Called on unmount — clean up the DOM." },
];
