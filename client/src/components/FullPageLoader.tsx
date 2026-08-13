import { Loader2 } from "lucide-react"

export function FullPageLoader() {
  return (
    <div
      className="flex min-h-screen items-center justify-center bg-muted/40"
      role="status"
      aria-live="polite"
    >
      <div className="flex flex-col items-center gap-3 text-muted-foreground">
        <Loader2 className="size-8 animate-spin" aria-hidden="true" />
        <p className="text-sm">Loading...</p>
      </div>
    </div>
  )
}
