import type { ReactNode } from "react"
import { Link } from "react-router-dom"
import { cn } from "@/lib/utils"
import { formatDateTime, formatRelativeTime } from "@/lib/format"
import type { AppNotification } from "@/types/notifications"

/**
 * The visual body of one notification row: unread dot, title, message and
 * relative time. Shared by the bell dropdown and the notifications page so
 * both stay consistent; the wrapping element differs per surface.
 */
export function NotificationItemBody({
  notification,
  className,
  children,
}: {
  notification: AppNotification
  className?: string
  children?: ReactNode
}) {
  const isUnread = notification.read_at === null

  return (
    <span
      data-testid="notification-item-body"
      className={cn("relative flex gap-3 px-3 py-2.5 text-left", className)}
    >
      <span
        aria-hidden="true"
        className={cn(
          "mt-1.5 size-2 shrink-0 rounded-full",
          isUnread ? "bg-primary" : "bg-transparent",
        )}
      />
      <span className="min-w-0 flex-1">
        <span
          className={cn(
            "block text-sm leading-5",
            isUnread ? "font-semibold text-foreground" : "font-normal text-foreground/90",
          )}
        >
          {notification.title}
        </span>
        <span className="mt-0.5 block truncate text-sm text-muted-foreground">
          {notification.message}
        </span>
        <span className="mt-1 flex items-center justify-between gap-2 text-xs text-muted-foreground/80">
          <time dateTime={notification.created_at} title={formatDateTime(notification.created_at)}>
            {formatRelativeTime(notification.created_at)}
          </time>
          {children}
        </span>
      </span>
    </span>
  )
}

/**
 * One notification row for the full notifications page. Unread rows get an
 * emphasized appearance; clicking navigates to the notification target.
 */
export function NotificationItem({
  notification,
  onOpen,
}: {
  notification: AppNotification
  onOpen?: (notification: AppNotification) => void
}) {
  const isUnread = notification.read_at === null

  return (
    <Link
      to={notification.action_url}
      onClick={() => onOpen?.(notification)}
      role="listitem"
      aria-label={isUnread ? `${notification.title} (unread)` : notification.title}
      data-testid="notification-item"
      data-unread={isUnread || undefined}
      className={cn(
        "block rounded-md transition-colors outline-none",
        "hover:bg-accent/60 focus-visible:bg-accent/60",
        isUnread && "bg-primary/[0.04]",
      )}
    >
      <NotificationItemBody notification={notification} />
    </Link>
  )
}
