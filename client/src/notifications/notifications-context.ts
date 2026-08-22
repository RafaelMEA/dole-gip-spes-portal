import { createContext } from "react"

export interface NotificationsContextValue {
  unreadCount: number
  isLoadingCount: boolean
  refreshUnreadCount: () => Promise<void>
  decrementUnread: (by?: number) => void
  clearUnread: () => void
}

export const NotificationsContext = createContext<NotificationsContextValue | null>(null)
