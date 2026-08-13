import { useCallback, useEffect, useMemo, useState } from "react"
import type { ReactNode } from "react"
import { CheckCircle2, Info, X, XCircle } from "lucide-react"
import { ToastContext, type ToastOptions, type ToastVariant } from "@/toast/toast-context"
import { cn } from "@/lib/utils"

const DEFAULT_DURATION = 4000
const CLOSE_ANIMATION_MS = 200

interface ToastItem extends Required<Pick<ToastOptions, "title" | "variant" | "duration">> {
  id: number
  description?: string
  closing: boolean
}

let nextToastId = 1

function ToastIcon({ variant }: { variant: ToastVariant }) {
  switch (variant) {
    case "success":
      return <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" aria-hidden="true" />
    case "error":
      return <XCircle className="mt-0.5 size-5 shrink-0 text-destructive" aria-hidden="true" />
    default:
      return <Info className="mt-0.5 size-5 shrink-0 text-primary" aria-hidden="true" />
  }
}

function ToastViewportItem({
  toast,
  onClose,
}: {
  toast: ToastItem
  onClose: (id: number) => void
}) {
  useEffect(() => {
    const timer = window.setTimeout(() => onClose(toast.id), toast.duration)
    return () => window.clearTimeout(timer)
  }, [toast.id, toast.duration, onClose])

  return (
    <li
      role={toast.variant === "error" ? "alert" : "status"}
      className={cn(
        "pointer-events-auto flex w-full items-start gap-3 rounded-xl border bg-popover p-4 text-popover-foreground shadow-lg",
        "animate-in fade-in slide-in-from-right-4 duration-200 ease-out",
        toast.closing && "animate-out fade-out slide-out-to-right-4 duration-150 ease-in",
      )}
    >
      <ToastIcon variant={toast.variant} />
      <div className="min-w-0 flex-1 space-y-1">
        <p className="text-sm font-semibold leading-none">{toast.title}</p>
        {toast.description ? (
          <p className="text-sm text-muted-foreground">{toast.description}</p>
        ) : null}
      </div>
      <button
        type="button"
        onClick={() => onClose(toast.id)}
        className="flex size-6 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
        aria-label="Dismiss notification"
      >
        <X className="size-4" aria-hidden="true" />
      </button>
    </li>
  )
}

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<ToastItem[]>([])

  const dismiss = useCallback((id: number) => {
    setToasts((current) =>
      current.map((toast) => (toast.id === id ? { ...toast, closing: true } : toast)),
    )
    window.setTimeout(() => {
      setToasts((current) => current.filter((toast) => toast.id !== id))
    }, CLOSE_ANIMATION_MS)
  }, [])

  const toast = useCallback<(options: ToastOptions) => void>((options) => {
    const id = nextToastId++
    const item: ToastItem = {
      id,
      title: options.title,
      description: options.description,
      variant: options.variant ?? "info",
      duration: options.duration ?? DEFAULT_DURATION,
      closing: false,
    }
    setToasts((current) => [...current, item])
  }, [])

  const value = useMemo(() => ({ toast }), [toast])

  return (
    <ToastContext value={value}>
      {children}
      <ul className="pointer-events-none fixed right-4 top-4 z-[100] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2">
        {toasts.map((item) => (
          <ToastViewportItem key={item.id} toast={item} onClose={dismiss} />
        ))}
      </ul>
    </ToastContext>
  )
}
