import type { Paginated } from "@/types/api"

export type NotificationType =
  | "application.submitted"
  | "application.resubmitted"
  | "application.returned_for_correction"
  | "application.documents_requested"
  | "application.approved"
  | "application.rejected"
  | "deployment.assigned"
  | "deployment.cancelled"

export interface AppNotification {
  id: string
  type: NotificationType
  title: string
  message: string
  action_url: string
  application_id: number | null
  assignment_id?: number | null
  read_at: string | null
  created_at: string
}

export type NotificationListResponse = Paginated<AppNotification>

export interface UnreadNotificationCount {
  count: number
}

export interface NotificationFilters {
  unread?: boolean
  page?: number
  per_page?: number
}
