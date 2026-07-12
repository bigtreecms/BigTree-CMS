// SDK import-map shim: re-exports the SPA's own React instance to custom action
// modules so there is exactly one React. Backed by the global registerSdk() sets.
const React = globalThis.__BIGTREE_SDK__ && globalThis.__BIGTREE_SDK__.react;

if (!React) {
	throw new Error("BigTree SDK not initialized — `react` is unavailable.");
}

export default React;
export const {
	Children,
	Component,
	Fragment,
	Profiler,
	PureComponent,
	StrictMode,
	Suspense,
	cloneElement,
	createContext,
	createElement,
	createRef,
	forwardRef,
	isValidElement,
	lazy,
	memo,
	startTransition,
	useCallback,
	useContext,
	useDeferredValue,
	useEffect,
	useId,
	useImperativeHandle,
	useInsertionEffect,
	useLayoutEffect,
	useMemo,
	useReducer,
	useRef,
	useState,
	useSyncExternalStore,
	useTransition,
} = React;
