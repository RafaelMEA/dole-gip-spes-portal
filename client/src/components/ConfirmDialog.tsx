import { Loader2 } from "lucide-react"
import type { LucideIcon } from "lucide-react"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Button } from "@/components/ui/button"

export interface ConfirmDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: string
  description?: string
  confirmLabel?: string
  cancelLabel?: string
  destructive?: boolean
  icon?: LucideIcon
  loading?: boolean
  onConfirm: () => void
}

export function ConfirmDialog({
  open,
  onOpenChange,
  title,
  description,
  confirmLabel = "Continue",
  cancelLabel = "Cancel",
  destructive = false,
  icon: Icon,
  loading = false,
  onConfirm,
}: ConfirmDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader className="gap-3 sm:flex-row sm:items-start">
          {Icon ? (
            <div
              className={
                destructive
                  ? "flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600"
                  : "flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground"
              }
            >
              <Icon className="size-5" aria-hidden="true" />
            </div>
          ) : null}
          <div className="space-y-1.5">
            <DialogTitle>{title}</DialogTitle>
            {description ? <DialogDescription>{description}</DialogDescription> : null}
          </div>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={loading}>
            {cancelLabel}
          </Button>
          <Button
            variant={destructive ? "default" : "default"}
            className={destructive ? "bg-red-600 text-white hover:bg-red-700" : undefined}
            onClick={onConfirm}
            disabled={loading}
          >
            {loading ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
            {confirmLabel}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
