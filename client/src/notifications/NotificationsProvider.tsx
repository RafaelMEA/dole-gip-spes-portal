import { useCallback, useEffect, useState, type ReactNode } from "react"
import { fetchUnreadCount } from "@/api/notifications"
import { NotificationsContext } from "./notifications-context"
import { useAuth } from "@/auth/useAuth"

interface UnreadStore {
  userId: number | null
  unreadCount: number
}

const POLL_INTERVAL_MS = 30_000

export function NotificationsProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth()
  const [store, setStore] = useState<UnreadStore>({ userId: null, unreadCount: 0 })

  const currentUserId = user?.id ?? null
  // Values belonging to a previous session are treated as a clean slate
  // without writing state during render or effects.
  const unreadCount = store.userId === currentUserId ? store.unreadCount : 0
  const isLoadingCount = user !== null && store.userId !== currentUserId

  const refreshUnreadCount = useCallback(async () => {
    if (!user) return
    try {
      const count = await fetchUnreadCount()
      setStore({ userId: user.id, unreadCount: count })
    } catch {
      // Ignore transient failures; the next poll will retry.
    }
  }, [user])

  useEffect(() => {
    if (!user) return
    let active = true

    fetchUnreadCount()
      .then((count) => {
        if (active) {
          setStore({ userId: user.id, unreadCount: count })
        }
      })
      .catch(() => {
        // Leave the badge as-is; polling will retry.
      })

    return () => {
      active = false
    }
  }, [user])

  // Poll for badge updates while signed in.
  useEffect(() => {
    if (!user) return

    const intervalId = window.setInterval(() => {
      void refreshUnreadCount()
    }, POLL_INTERVAL_MS)

    return () => {
      window.clearInterval(intervalId)
    }
  }, [user, refreshUnreadCount])

  const decrementUnread = useCallback(() => {
    setStore((current) => ({
      ...current,
      unreadCount:
        current.userId === (user?.id ?? null) ? Math.max(0, current.unreadCount - 1) : 0,
    }))
  }, [user])

  const clearUnread = useCallback(() => {
    setStore((current) =>
      current.userId === (user?.id ?? null) ? { ...current, unreadCount: 0 } : { userId: user?.id ?? null, unreadCount: 0 },
    )
  }, [user])

  return (
    <NotificationsContext
      value={{
        unreadCount,
        isLoadingCount,
        refreshUnreadCount,
        decrementUnread,
        clearUnread,
      }}
    >
      {children}
    </NotificationsContext>
  )
}
