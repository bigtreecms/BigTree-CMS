/**
 * Example BigTree field-type module (contract v1) — a 1–5 star rating.
 *
 * Demonstrates the imperative field contract from
 * spa/src/renderer/forms/fieldModuleContract.ts: the host hands us a DOM
 * `element` plus the controlled `value` / `onChange`, and we own what we render
 * inside it. No React, no bundler — a plain ES module.
 *
 * To try it: register a field type whose record sets
 *   { "render": "module", "trust": "core", "asset_url": "/field-modules/stars.mjs" }
 * (trust core/verified is required to load in-context; marketplace needs the
 * sandbox). The stored value is a number 1–5.
 */
export default {
	contractVersion: 1,

	render(host) {
		const root = document.createElement("div");
		root.style.display = "inline-flex";
		root.style.gap = "4px";
		host.element.appendChild(root);

		const draw = (value, disabled) => {
			root.innerHTML = "";
			const current = Number(value) || 0;

			for (let i = 1; i <= 5; i++) {
				const star = document.createElement("button");
				star.type = "button";
				star.textContent = i <= current ? "★" : "☆";
				star.disabled = !!disabled;
				star.style.fontSize = "20px";
				star.style.lineHeight = "1";
				star.style.background = "none";
				star.style.border = "none";
				star.style.padding = "0";
				star.style.cursor = disabled ? "default" : "pointer";
				star.style.color = i <= current ? "#f5a623" : "#bbb";
				star.addEventListener("click", () => host.onChange(i));
				root.appendChild(star);
			}
		};

		draw(host.value, host.disabled);

		return {
			update(next) {
				draw(next.value, next.disabled);
			},
			destroy() {
				root.remove();
			},
		};
	},

	validate(value) {
		const n = Number(value);

		if (value === "" || value === null || value === undefined) {
			return null;
		}

		return n >= 1 && n <= 5 ? null : "Rating must be between 1 and 5.";
	},
};
