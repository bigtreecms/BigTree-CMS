import { describe, expect, it, vi } from "vitest";

import { fireEvent, render } from "@testing-library/react";
import { useRef } from "react";

import { useOnClickOutside } from "@/hooks/useOnClickOutside";

interface ProbeProps {
	enabled?: boolean;
	handler: () => void;
}

const Probe = ({ handler, enabled }: ProbeProps) => {
	const ref = useRef<HTMLDivElement>(null);

	useOnClickOutside(ref, handler, enabled);

	return (
		<div>
			<div data-testid="inside" ref={ref}>
				inside
			</div>

			<div data-testid="outside">outside</div>
		</div>
	);
};

describe("useOnClickOutside", () => {
	it("does not fire when the mousedown lands inside the ref", () => {
		const handler = vi.fn();
		const { getByTestId } = render(<Probe handler={handler} />);

		fireEvent.mouseDown(getByTestId("inside"));

		expect(handler).not.toHaveBeenCalled();
	});

	it("fires when the mousedown lands outside the ref", () => {
		const handler = vi.fn();
		const { getByTestId } = render(<Probe handler={handler} />);

		fireEvent.mouseDown(getByTestId("outside"));

		expect(handler).toHaveBeenCalledTimes(1);
	});

	it("does nothing while disabled", () => {
		const handler = vi.fn();
		const { getByTestId } = render(<Probe enabled={false} handler={handler} />);

		fireEvent.mouseDown(getByTestId("outside"));

		expect(handler).not.toHaveBeenCalled();
	});

	it("removes the listener on unmount", () => {
		const handler = vi.fn();
		const { unmount } = render(<Probe handler={handler} />);

		unmount();
		fireEvent.mouseDown(document.body);

		expect(handler).not.toHaveBeenCalled();
	});
});
