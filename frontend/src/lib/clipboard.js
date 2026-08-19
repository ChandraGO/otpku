import { toast } from "sonner";

export async function copyText(text, msg = "Dikopi") {
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(text || "");
    } else {
      const ta = document.createElement("textarea");
      ta.value = text || "";
      document.body.appendChild(ta);
      ta.select();
      document.execCommand("copy");
      document.body.removeChild(ta);
    }
    toast.success(msg);
  } catch {
    toast.error("Tidak bisa mengakses clipboard. Salin manual.");
  }
}
