import { apiRequest, requestCsrfCookie } from "@/lib/api"
import type {
  NotificationFilters,
  NotificationListResponse,
  UnreadNotificationCount,
} from "@/types/notifications"

function serializeQuery(query: NotificationFilters): URLSearchParams {
  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== null && value !== "" && value !== false) {
      params.set(key, String(value))
    }
  }
  return params
}

export async function fetchNotifications(
  query: NotificationFilters = {},
): Promise<NotificationListResponse> {
  const qs = serializeQuery(query).toString()
  return apiRequest<NotificationListResponse>(`/api/notifications${qs ? `?${qs}` : ""}`)
}

export async function fetchUnreadCount(): Promise<number> {
  const response = await apiRequest<UnreadNotificationCount>("/api/notifications/unread-count")
  return response.count
}

export async function markNotificationRead(id: string): Promise<void> {
  await requestCsrfCookie()
  await apiRequest(`/api/notifications/${id}/read`, { method: "PATCH" })
}

export async function markAllNotificationsRead(): Promise<void> {
  await requestCsrfCookie()
  await apiRequest("/api/notifications/read-all", { method: "PATCH" })
}
