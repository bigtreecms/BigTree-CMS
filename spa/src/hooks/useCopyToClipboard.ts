import { toast } from "@/lib/toast";

export function useCopyToClipboard() {
	return async (text: string, successMessage = "Copied to clipboard") => {
		try {
			await navigator.clipboard.writeText(text);
			toast.success(successMessage);
		} catch {
			toast.error("Failed to copy to clipboard");
		}
	};
}
