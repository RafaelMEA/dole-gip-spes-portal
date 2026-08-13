import { Check, CircleDashed } from "lucide-react"
import type { StatusHistoryItem } from "@/types/api"
import { formatDateTime } from "@/lib/format"
import { ApplicationStatusBadge } from "@/components/StatusBadge"
import { cn } from "@/lib/utils"

export function StatusTimeline({ items }: { items: StatusHistoryItem[] }) {
  if (!items.length) {
    return (
      <p className="text-sm text-muted-foreground">
        No status updates yet. This application is still awaiting its first update.
      </p>
    )
  }

  const [latest, ...rest] = items

  return (
    <ol className="relative space-y-4 border-l pl-5">
      <li className="relative">
        <span className="absolute -left-[27px] flex size-5 items-center justify-center rounded-full bg-emerald-600 text-white ring-4 ring-emerald-600/15">
          <Check className="size-3" aria-hidden="true" />
        </span>
        <div className="space-y-0.5">
          <div className="flex flex-wrap items-center gap-2">
            <p className="text-sm font-medium">{latest.status_label}</p>
            <ApplicationStatusBadge status={latest.status} />
          </div>
          <p className="text-xs text-muted-foreground">
            {formatDateTime(latest.changed_at)}
            {latest.changed_by ? ` · by ${latest.changed_by}` : ""}
          </p>
          {latest.remarks ? (
            <p className="rounded-md bg-muted/60 px-3 py-2 text-sm text-muted-foreground">
              {latest.remarks}
            </p>
          ) : null}
        </div>
      </li>
      {rest.map((item) => (
        <li key={item.id} className="relative">
          <span
            className={cn(
              "absolute -left-[27px] flex size-5 items-center justify-center rounded-full bg-muted text-muted-foreground ring-4 ring-background",
            )}
          >
            <CircleDashed className="size-3" aria-hidden="true" />
          </span>
          <div className="space-y-0.5">
            <div className="flex flex-wrap items-center gap-2">
              <p className="text-sm text-muted-foreground">{item.status_label}</p>
            </div>
            <p className="text-xs text-muted-foreground/80">
              {formatDateTime(item.changed_at)}
              {item.changed_by ? ` · by ${item.changed_by}` : ""}
            </p>
            {item.remarks ? (
              <p className="rounded-md bg-muted/60 px-3 py-2 text-sm text-muted-foreground">
                {item.remarks}
              </p>
            ) : null}
          </div>
        </li>
      ))}
    </ol>
  )
}
