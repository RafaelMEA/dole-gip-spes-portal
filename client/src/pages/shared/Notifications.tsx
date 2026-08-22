import { useEffect, useState } from "react"
import { BellRing, ChevronLeft, ChevronRight } from "lucide-react"
import {
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from "@/api/notifications"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { PageHeader } from "@/components/PageHeader"
import { EmptyState } from "@/components/EmptyState"
import { NotificationItem } from "@/components/notifications/NotificationItem"
import { useNotifications } from "@/notifications/useNotifications"
import { useToast } from "@/toast/useToast"
import { ApiError } from "@/lib/api"
import type { NotificationListResponse } from "@/types/notifications"

const PAGE_SIZE = 10

function ListSkeleton() {
  return (
    <div className="space-y-2" aria-hidden="true" data-testid="notifications-skeleton">
      {[0, 1, 2, 3].map((row) => (
        <div key={row} className="flex items-start gap-3 rounded-lg border px-3 py-3">
          <Skeleton className="mt-1.5 size-2 shrink-0 rounded-full" />
          <div className="flex-1 space-y-1.5">
            <Skeleton className="h-4 w-2/5" />
            <Skeleton className="h-3.5 w-4/5" />
            <Skeleton className="h-3 w-1/4" />
          </div>
        </div>
      ))}
    </div>
  )
}

/**
 * The full notification centre, shared by students and staff — the API
 * already scopes every row to the authenticated user.
 */
export function NotificationsPage() {
  const { refreshUnreadCount, unreadCount } = useNotifications()
  const { toast } = useToast()
  const [page, setPage] = useState(1)
  const [loadedPage, setLoadedPage] = useState(0)
  const [result, setResult] = useState<NotificationListResponse | null>(null)
  const [isMarkingAll, setIsMarkingAll] = useState(false)
  const [error, setError] = useState<string | null>(null)

  // Derived: a page change immediately shows the skeleton without any
  // synchronous state writes inside effects.
  const isLoading = result === null || loadedPage !== page

  useEffect(() => {
    let cancelled = false

    fetchNotifications({ page, per_page: PAGE_SIZE })
      .then((response) => {
        if (!cancelled) {
          setResult(response)
          setLoadedPage(page)
          setError(null)
        }
      })
      .catch((caught: unknown) => {
        if (!cancelled) {
          setError(
            caught instanceof ApiError
              ? caught.message
              : "Unable to reach the server. Please check your connection and try again.",
          )
        }
      })

    return () => {
      cancelled = true
    }
  }, [page])

  async function handleOpen(id: string, wasUnread: boolean) {
    if (!wasUnread) return

    setResult((current) =>
      current
        ? {
            ...current,
            data: current.data.map((item) =>
              item.id === id ? { ...item, read_at: new Date().toISOString() } : item,
            ),
          }
        : current,
    )

    try {
      await markNotificationRead(id)
    } catch {
      toast({
        title: "Could not update the notification",
        description: "Please try again.",
        variant: "error",
      })
    } finally {
      await refreshUnreadCount()
    }
  }

  async function handleMarkAllRead() {
    setIsMarkingAll(true)
    try {
      await markAllNotificationsRead()
      toast({ title: "All notifications marked as read" })
      setResult(await fetchNotifications({ page, per_page: PAGE_SIZE }))
      setLoadedPage(page)
      await refreshUnreadCount()
    } catch (caught) {
      toast({
        title: "Action failed",
        description:
          caught instanceof ApiError
            ? caught.message
            : "Please check your connection and try again.",
        variant: "error",
      })
    } finally {
      setIsMarkingAll(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Notifications"
        description="Updates about applications and deployment."
      >
        <Button
          variant="outline"
          onClick={handleMarkAllRead}
          disabled={isMarkingAll || unreadCount === 0}
          data-testid="mark-all-read-page"
        >
          Mark all as read
        </Button>
      </PageHeader>

      {error ? (
        <p
          role="alert"
          className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {error}
        </p>
      ) : isLoading ? (
        <ListSkeleton />
      ) : result === null || result.total === 0 ? (
        <EmptyState
          icon={BellRing}
          title="No notifications yet."
          description="Updates about your applications will appear here."
        />
      ) : (
        <>
          <div role="list" data-testid="notification-list" className="-mx-1 space-y-1">
            {result.data.map((notification) => (
              <NotificationItem
                key={notification.id}
                notification={notification}
                onOpen={(item) => void handleOpen(item.id, item.read_at === null)}
              />
            ))}
          </div>

          <div className="flex items-center justify-between gap-3">
            <p className="text-sm text-muted-foreground">
              Page {result.current_page} of {result.last_page}
            </p>
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="icon"
                aria-label="Previous page"
                disabled={page <= 1}
                onClick={() => setPage((current) => Math.max(1, current - 1))}
              >
                <ChevronLeft aria-hidden="true" />
              </Button>
              <Button
                variant="outline"
                size="icon"
                aria-label="Next page"
                disabled={page >= result.last_page}
                onClick={() => setPage((current) => current + 1)}
              >
                <ChevronRight aria-hidden="true" />
              </Button>
            </div>
          </div>
        </>
      )}
    </div>
  )
}
