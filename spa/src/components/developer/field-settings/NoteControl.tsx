import type { ControlProps } from "./types";

/** Static help text with no input. */
export const NoteControl = ({ descriptor }: ControlProps) => {
	const text = descriptor.note ?? descriptor.label;

	if (!text) {
		return null;
	}

	return <p className="text-[11.5px] leading-relaxed text-text-3">{text}</p>;
};
