import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { Check, CircleAlert, X } from "lucide-react";
import "./toast.css";

type ToastKind = "success" | "error";
type Toast = { id: number; message: string; kind: ToastKind };
type Notify = (message: string, kind?: ToastKind) => void;
const ToastContext = createContext<Notify | null>(null);

export function useToast() {
  const notify = useContext(ToastContext);
  if (!notify) throw new Error("useToast requires ToastProvider");
  return notify;
}

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toast, setToast] = useState<Toast | null>(null);
  const sequence = useRef(0);
  const popup = useRef<HTMLDivElement>(null);
  const notify = useCallback<Notify>((message, kind = "success") => {
    setToast({ id: ++sequence.current, message, kind });
  }, []);

  useEffect(() => {
    if (!toast) return;
    // The top layer keeps feedback visible when an action happens in a dialog.
    popup.current?.showPopover?.();
    const timeout = window.setTimeout(() => setToast(null), 3000);
    return () => window.clearTimeout(timeout);
  }, [toast]);

  return (
    <ToastContext.Provider value={notify}>
      {children}
      {toast && (
        <div
          ref={popup}
          popover="manual"
          className={`app-toast ${toast.kind}`}
          key={toast.id}
        >
          <span className="app-toast-icon" aria-hidden="true">
            {toast.kind === "success" ? (
              <Check size={21} strokeWidth={2.5} />
            ) : (
              <CircleAlert size={21} />
            )}
          </span>
          <div
            className="app-toast-copy"
            role={toast.kind === "error" ? "alert" : "status"}
            aria-atomic="true"
          >
            <strong>
              {toast.kind === "success" ? "Berhasil!" : "Belum berhasil"}
            </strong>
            <p>{toast.message}</p>
          </div>
          <button
            type="button"
            className="app-toast-close"
            aria-label="Tutup notifikasi"
            onClick={() => setToast(null)}
          >
            <X size={17} />
          </button>
          <span className="app-toast-progress" aria-hidden="true" />
        </div>
      )}
    </ToastContext.Provider>
  );
}
