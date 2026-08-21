import { useCallback, useEffect, useRef, useState } from "react"
import { ArrowLeft, ArrowRight, History } from "lucide-react"
import { useAsync } from "@/lib/useAsync"
import { formatDateTime } from "@/lib/format"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import type { AuditEvent } from "@/types/api"

const FIELD_LABELS: Record<string, string> = {
  verification_status: "Verification",
  rejection_reason: "Reason",
  file_name: "File",
  mime_type: "File type",
  file_size: "File size",
  capacity: "Capacity",
  status: "Status",
  title: "Title",
  description: "Description",
  name: "Name",
  is_active: "Active",
}

function fieldLabel(key: string): string {
  return (
    FIELD_LABELS[key] ??
    key
      .split("_")
      .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
      .join(" ")
  )
}

function formatValue(value: unknown): string {
  if (value === null || value === undefined) return "—"
  if (typeof value === "boolean") return value ? "Yes" : "No"
  if (typeof value === "string") return value
  if (typeof value === "number") return String(value)
  return JSON.stringify(value)
}

function changedKeys(event: AuditEvent): string[] {
  const keys = new Set([
    ...Object.keys(event.old_values ?? {}),
    ...Object.keys(event.new_values ?? {}),
  ])
  return [...keys].filter((key) => {
    const before = event.old_values?.[key]
    const after = event.new_values?.[key]
    return JSON.stringify(before ?? null) !== JSON.stringify(after ?? null)
  })
}

function EventChanges({ event }: { event: AuditEvent }) {
  const keys = changedKeys(event)
  if (keys.length === 0) return null

  return (
    <dl className="space-y-1 rounded-md bg-muted/60 px-3 py-2 text-xs">
      {keys.map((key) => (
        <div key={key} className="flex flex-wrap items-baseline gap-x-1.5">
          <dt className="font-medium text-muted-foreground">{fieldLabel(key)}:</dt>
          <dd className="text-muted-foreground">
            {event.old_values && key in event.old_values ? (
              <>
                <span className="line-through">{formatValue(event.old_values[key])}</span>
                <span aria-hidden="true"> → </span>
                <span className="font-medium text-foreground">
                  {formatValue(event.new_values?.[key])}
                </span>
              </>
            ) : (
              <span className="font-medium text-foreground">
                {formatValue(event.new_values?.[key])}
              </span>
            )}
          </dd>
        </div>
      ))}
    </dl>
  )
}

export function HistoryTimeline({
  events,
  showChanges = false,
}: {
  events: AuditEvent[]
  showChanges?: boolean
}) {
  if (!events.length) {
    return <p className="text-sm text-muted-foreground">No history available.</p>
  }

  return (
    <ol className="relative space-y-4 border-l pl-5" data-testid="history-timeline">
      {events.map((event, index) => (
        <li key={`${event.source ?? "audit"}-${event.id}`} className="relative">
          <span
            className={
              index === 0
                ? "absolute -left-[27px] flex size-5 items-center justify-center rounded-full bg-emerald-600 text-white ring-4 ring-emerald-600/15"
                : "absolute -left-[27px] flex size-5 items-center justify-center rounded-full bg-muted text-muted-foreground ring-4 ring-background"
            }
          >
            <History className="size-3" aria-hidden="true" />
          </span>
          <div className="space-y-1">
            <p className={index === 0 ? "text-sm font-medium" : "text-sm text-muted-foreground"}>
              {event.label}
            </p>
            <p className="text-xs text-muted-foreground/80">
              {formatDateTime(event.occurred_at)}
              {event.actor ? ` · by ${event.actor}` : ""}
            </p>
            {event.reason ? (
              <p className="rounded-md bg-muted/60 px-3 py-2 text-sm text-muted-foreground">
                {event.reason}
              </p>
            ) : null}
            {showChanges ? <EventChanges event={event} /> : null}
          </div>
        </li>
      ))}
    </ol>
  )
}

function HistorySkeleton() {
  return (
    <div className="space-y-4" aria-hidden="true" data-testid="history-loading">
      {[0, 1, 2].map((row) => (
        <div key={row} className="space-y-2 border-l pl-5">
          <Skeleton className="h-4 w-40" />
          <Skeleton className="h-3 w-56" />
        </div>
      ))}
    </div>
  )
}

/**
 * Self-contained paged feed over one entity's history endpoint.
 *
 * Renders loading, error and empty states and Previous/Next pagination;
 * pass `refreshKey` (e.g. the parent record's updated_at) to refetch after
 * workflow actions.
 */
export function HistoryFeed({
  fetchPage,
  showChanges = false,
  refreshKey,
}: {
  fetchPage: (page: number) => Promise<{ data: AuditEvent[]; current_page: number; last_page: number }>
  showChanges?: boolean
  refreshKey?: string | number
}) {
  const [request, setRequest] = useState({ page: 1, key: refreshKey })

  // Restart from the first page whenever the parent record changes.
  if (refreshKey !== request.key) {
    setRequest({ page: 1, key: refreshKey })
  }

  // Keep the latest fetcher without refetching on every parent render.
  const fetchPageRef = useRef(fetchPage)
  useEffect(() => {
    fetchPageRef.current = fetchPage
  })

  const fetcher = useCallback(() => fetchPageRef.current(request.page), [request])
  const { data, loading, error } = useAsync(fetcher)

  if (loading && !data) return <HistorySkeleton />

  if (error) {
    return (
      <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
        {error.message}
      </p>
    )
  }

  if (!data) return null

  return (
    <div className="space-y-4" data-testid="history-feed">
      <HistoryTimeline events={data.data} showChanges={showChanges} />

      {data.last_page > 1 ? (
        <div className="flex items-center justify-between">
          <Button
            variant="outline"
            size="sm"
            disabled={data.current_page <= 1 || loading}
            onClick={() => setRequest((current) => ({ ...current, page: current.page - 1 }))}
          >
            <ArrowLeft aria-hidden="true" />
            Previous
          </Button>
          <p className="text-sm text-muted-foreground">
            Page {data.current_page} of {data.last_page}
          </p>
          <Button
            variant="outline"
            size="sm"
            disabled={data.current_page >= data.last_page || loading}
            onClick={() => setRequest((current) => ({ ...current, page: current.page + 1 }))}
          >
            Next
            <ArrowRight aria-hidden="true" />
          </Button>
        </div>
      ) : null}
    </div>
  )
}
