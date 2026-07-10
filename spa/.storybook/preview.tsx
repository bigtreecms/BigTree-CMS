import { useEffect, type ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import type { Decorator, Preview } from "@storybook/react-vite";

// Load the app's real Tailwind + token pipeline so every story renders with the
// production palette. `index.css` pulls in tailwindcss, tokens.css and base.css
// in the correct order (tokens' @theme must precede base) — see src/styles.
import "../src/styles/index.css";

/**
 * Mirrors the app's theme mechanism: dark mode is driven by `[data-theme="dark"]`
 * on <html> (tokens.css redefines every --color-* var under that selector).
 */
const ThemeWrapper = ({ theme, children }: { theme: string; children: ReactNode }) => {
	useEffect(() => {
		document.documentElement.dataset.theme = theme;

		return () => {
			delete document.documentElement.dataset.theme;
		};
	}, [theme]);

	return (
		<div className="bg-bg text-text" style={{ minHeight: "100%", padding: "1.5rem" }}>
			{children}
		</div>
	);
};

// Theme toolbar toggle — every story must read in both modes since the tokens
// drive both.
const withTheme: Decorator = (Story, context) => (
	<ThemeWrapper theme={context.globals.theme ?? "light"}>
		<Story />
	</ThemeWrapper>
);

// Many primitives (Button `to`, anything using <Link>) need a router in scope.
// A story that must supply its own router (e.g. one using the data-router
// `useBlocker`) opts out with `parameters: { router: false }` to avoid nesting
// two routers.
const withRouter: Decorator = (Story, context) => {
	if (context.parameters.router === false) {
		return <Story />;
	}

	return (
		<MemoryRouter>
			<Story />
		</MemoryRouter>
	);
};

const preview: Preview = {
	decorators: [withTheme, withRouter],
	globalTypes: {
		theme: {
			description: "Token theme (light / dark)",
			defaultValue: "light",
			toolbar: {
				title: "Theme",
				icon: "circlehollow",
				items: [
					{ value: "light", title: "Light", icon: "sun" },
					{ value: "dark", title: "Dark", icon: "moon" },
				],
				dynamicTitle: true,
			},
		},
	},
	parameters: {
		controls: {
			matchers: {
				color: /(background|color)$/i,
				date: /Date$/i,
			},
		},

		a11y: {
			// 'todo' - show a11y violations in the test UI only
			// 'error' - fail CI on a11y violations
			// 'off' - skip a11y checks entirely
			test: "error",
		},
	},
};

export default preview;
