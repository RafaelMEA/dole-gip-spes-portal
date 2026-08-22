import { useCallback, useEffect, useState } from "react"
import { Link } from "react-router-dom"
import { Bell, CheckCheck, Loader2 } from "lucide-react"
import {
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from "@/api/notifications"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { NotificationItemBody } from "@/components/notifications/NotificationItem"
import { useNotifications } from "@/notifications/useNotifications"
import { useAuth } from "@/auth/useAuth"
import { formatBadgeCount } from "@/lib/format"
import type { AppNotification } from "@/types/notifications"

const PREVIEW_COUNT = 6

interface PreviewState {
  items: AppNotification[]
  error: string | null
}

function PreviewSkeletons() {
  return (
    <div className="space-y-1 p-1" aria-hidden="true">
      {[0, 1, 2].map((row) => (
        <div key={row} className="flex gap-2.5 rounded-md px-2 py-2.5">
          <Skeleton className="mt-1 size-2 shrink-0 rounded-full" />
          <div className="flex-1 space-y-1.5">
            <Skeleton className="h-3.5 w-3/5" />
            <Skeleton className="h-3 w-full" />
            <Skeleton className="h-2.5 w-16" />
          </div>
        </div>
      ))}
    </div>
  )
}

export function NotificationBell() {
  const { user } = useAuth()
  const { unreadCount, decrementUnread, clearUnread, refreshUnreadCount } = useNotifications()
  const [open, setOpen] = useState(false)
  const [preview, setPreview] = useState<PreviewState | null>(null)
  const [isMarkingAll, setIsMarkingAll] = useState(false)
  const viewAllPath =
    user?.role === "staff" ? "/staff/notifications" : "/student/notifications"

  // Fetched when the dropdown opens; the first open renders skeletons
  // while preview is still null, later opens refresh silently.
  useEffect(() => {
    if (!open) return
    let active = true

    fetchNotifications({ per_page: PREVIEW_COUNT })
      .then((response) => {
        if (active) setPreview({ items: response.data, error: null })
      })
      .catch(() => {
        if (active) {
          setPreview((current) => ({
            items: current?.items ?? [],
            error: "Couldn't load notifications.",
          }))
        }
      })

    return () => {
      active = false
    }
  }, [open])

  async function handleOpen(notification: AppNotification) {
    setOpen(false)
    if (notification.read_at !== null) return
    decrementUnread()
    try {
      await markNotificationRead(notification.id)
    } catch {
      await refreshUnreadCount()
    }
  }

  const handleOpenChange = useCallback(
    (nextOpen: boolean, eventDetails: unknown) => {
      const details = eventDetails as { reason?: string } | undefined
      if (details?.reason === "link-press") return
      setOpen(nextOpen)
    },
    [],
  )

  const badge = formatBadgeCount(unreadCount)

  return (
    <DropdownMenu open={open} onOpenChange={handleOpenChange}>
      <DropdownMenuTrigger
        render={
          <Button variant="ghost" size="icon" aria-label={`Notifications${badge ? `, ${unreadCount} unread` : ""}`} />
        }
        data-testid="notification-bell"
      >
        <span className="relative inline-flex">
          <Bell aria-hidden="true" />
          {badge ? (
            <span
              data-testid="unread-badge"
              className="absolute -right-2 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-none text-white"
            >
              {badge}
            </span>
          ) : null}
        </span>
      </DropdownMenuTrigger>

      <DropdownMenuContent className="w-80 p-0" sideOffset={8}>
        <div className="flex items-center justify-between border-b px-3 py-2.5">
          <p className="text-sm font-semibold">Notifications</p>
          {preview && preview.items.length > 0 ? (
            <Button
              variant="ghost"
              size="sm"
              className="h-7 gap-1 text-xs text-muted-foreground"
              disabled={isMarkingAll || unreadCount === 0}
              onClick={async () => {
                setIsMarkingAll(true)
                try {
                  await markAllNotificationsRead()
                  setPreview((current) =>
                    current
                      ? {
                          ...current,
                          items: current.items.map((item) => ({
                            ...item,
                            read_at: item.read_at ?? new Date().toISOString(),
                          })),
                        }
                      : current,
                  )
                  clearUnread()
                  await refreshUnreadCount()
                } catch {
                  await refreshUnreadCount()
                } finally {
                  setIsMarkingAll(false)
                }
              }}
              data-testid="mark-all-read"
            >
              {isMarkingAll ? (
                <Loader2 aria-hidden="true" className="size-3 animate-spin" />
              ) : (
                <CheckCheck aria-hidden="true" />
              )}
              Mark all read
            </Button>
          ) : null}
        </div>

        {!preview ? (
          <PreviewSkeletons />
        ) : preview.error && preview.items.length === 0 ? (
          <div role="alert" className="px-3 py-6 text-center text-sm text-muted-foreground">
            {preview.error}
          </div>
        ) : preview.items.length === 0 ? (
          <div className="px-3 py-6 text-center text-sm text-muted-foreground">
            No notifications yet.
          </div>
        ) : (
          <>
            {preview.error ? (
              <p role="alert" className="border-b bg-destructive/10 px-3 py-1.5 text-xs text-destructive">
                {preview.error} Showing the last loaded list.
              </p>
            ) : null}
            <div className="max-h-96 overflow-y-auto p-1">
              {preview.items.map((notification) => (
                <DropdownMenuItem
                  key={notification.id}
                  className="w-full rounded-md p-0"
                  onClick={() => void handleOpen(notification)}
                >
                  <NotificationItemBody notification={notification} />
                </DropdownMenuItem>
              ))}
            </div>
          </>
        )}

        <div className="border-t p-1">
          <Button
            variant="ghost"
            size="sm"
            className="w-full justify-center text-xs"
            render={<Link to={viewAllPath} />}
            onClick={() => setOpen(false)}
          >
            View all notifications
          </Button>
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
