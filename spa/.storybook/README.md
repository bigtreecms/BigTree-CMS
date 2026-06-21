# Storybook — the SPA component catalog

Storybook 10 (Vite builder) is the living catalog for the admin SPA's shared UI
primitives. It renders every primitive with the app's real Tailwind + design
tokens, in both light and dark mode, so overlaps are visible and the canonical
variants are documented.

## Running

```bash
nvm use            # Node 20.19+ — Storybook 10 needs it (default shell Node is 14)
npm run storybook  # dev server at http://localhost:6006
```

- `npm run build-storybook` — static build (output `storybook-static/`, gitignored). Wire this into CI so a broken story fails the build.
- `npm run test-storybook` — smoke-renders every story in a headless Chromium via the Storybook Vitest addon (Playwright). It is a separate Vitest project (`storybook`) from the jsdom unit tests (`unit`), so the core `npm test` gate stays fast and browser-independent.

## Theme + tokens

`.storybook/preview.tsx` imports `../src/styles/index.css` (which pulls in
Tailwind, `tokens.css` and `base.css`), so stories use the production palette. A
**Theme** toolbar toggle switches `[data-theme="dark"]` on `<html>` — the same
mechanism the app uses. **Every story must read correctly in both light and dark
mode.**

The `@` → `src` alias and Tailwind come from `vite.config.ts`, which the
`@storybook/react-vite` builder reads automatically.

## Conventions

- **Co-locate** stories next to source: `Foo.tsx` → `Foo.stories.tsx`.
- One story file per component, with a `Variants` (or `Matrix`) story showing
  every prop permutation. `Button.stories.tsx` is the **reference shape** — copy
  it.
- Use **autodocs** (`tags: ["autodocs"]`) so prop tables are generated from the
  TS types.
- For controlled components (`value`/`onChange`), drive state from a `render`
  function with `useState`.
- **Every primitive PR must include or update its story** — treat it as a review
  checklist item.
