import { describe, expect, it, vi, beforeEach } from "vitest"
import { render, screen, waitFor } from "@testing-library/react"
import userEvent from "@testing-library/user-event"
import { MemoryRouter } from "react-router-dom"
import { NotificationsPage } from "@/pages/shared/Notifications"
import { ApiError } from "@/lib/api"
import type { AppNotification, NotificationListResponse } from "@/types/notifications"

const { fetchNotificationsMock, markNotificationReadMock, markAllNotificationsReadMock } =
  vi.hoisted(() => ({
    fetchNotificationsMock: vi.fn(),
    markNotificationReadMock: vi.fn(),
    markAllNotificationsReadMock: vi.fn(),
  }))

const { notificationsStateRef, toastMock } = vi.hoisted(() => ({
  notificationsStateRef: {
    current: {
      unreadCount: 0,
      refreshUnreadCount: vi.fn().mockResolvedValue(undefined),
      decrementUnread: vi.fn(),
      clearUnread: vi.fn(),
    },
  },
  toastMock: vi.fn(),
}))

vi.mock("@/api/notifications", () => ({
  fetchNotifications: fetchNotificationsMock,
  markNotificationRead: markNotificationReadMock,
  markAllNotificationsRead: markAllNotificationsReadMock,
}))

vi.mock("@/notifications/useNotifications", () => ({
  useNotifications: () => notificationsStateRef.current,
}))

vi.mock("@/toast/useToast", () => ({
  useToast: () => ({ toast: toastMock }),
}))

function notification(overrides: Partial<AppNotification> = {}): AppNotification {
  return {
    id: "00000000-0000-4000-8000-000000000001",
    type: "application.submitted",
    title: "Application submitted",
    message: "Your application was received.",
    action_url: "/student/applications/10",
    application_id: 10,
    read_at: null,
    created_at: new Date(Date.now() - 60_000).toISOString(),
    ...overrides,
  }
}

function pageOf(
  data: AppNotification[],
  overrides: Partial<NotificationListResponse> = {},
): NotificationListResponse {
  return {
    data,
    current_page: 1,
    first_page_url: null,
    from: data.length ? 1 : null,
    last_page: 1,
    last_page_url: null,
    next_page_url: null,
    path: "/api/notifications",
    per_page: 10,
    prev_page_url: null,
    to: data.length || null,
    total: data.length,
    ...overrides,
  }
}

function renderPage() {
  return render(
    <MemoryRouter>
      <NotificationsPage />
    </MemoryRouter>,
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  fetchNotificationsMock.mockResolvedValue(pageOf([]))
  notificationsStateRef.current.unreadCount = 0
})

describe("NotificationsPage", () => {
  it("shows a loading skeleton before the first page arrives", async () => {
    let resolveFetch: (value: NotificationListResponse) => void = () => {}
    fetchNotificationsMock.mockReturnValue(
      new Promise<NotificationListResponse>((resolve) => {
        resolveFetch = resolve
      }),
    )
    renderPage()

    expect(screen.getByTestId("notifications-skeleton")).toBeInTheDocument()

    resolveFetch(pageOf([]))
    await waitFor(() => {
      expect(screen.queryByTestId("notifications-skeleton")).not.toBeInTheDocument()
    })
  })

  it("requests the first page with a page size of 10", async () => {
    renderPage()

    await waitFor(() => {
      expect(fetchNotificationsMock).toHaveBeenCalledWith({ page: 1, per_page: 10 })
    })
  })

  it("renders each notification with its title and message", async () => {
    fetchNotificationsMock.mockResolvedValue(
      pageOf([
        notification(),
        notification({
          id: "00000000-0000-4000-8000-000000000002",
          type: "application.approved",
          title: "Application approved",
          message: "Congratulations!",
          action_url: "/student/applications/10",
        }),
      ]),
    )
    renderPage()

    expect(await screen.findByText("Application submitted")).toBeInTheDocument()
    expect(screen.getByText("Your application was received.")).toBeInTheDocument()
    expect(screen.getByText("Application approved")).toBeInTheDocument()
    expect(screen.getAllByTestId("notification-item")).toHaveLength(2)
  })

  it("marks unread rows with an aria label", async () => {
    fetchNotificationsMock.mockResolvedValue(
      pageOf([
        notification(),
        notification({
          id: "00000000-0000-4000-8000-000000000002",
          read_at: new Date().toISOString(),
        }),
      ]),
    )
    renderPage()

    const items = await screen.findAllByTestId("notification-item")

    expect(items[0]).toHaveAttribute("aria-label", "Application submitted (unread)")
    expect(items[0]).toHaveAttribute("data-unread")
    expect(items[1]).toHaveAttribute("aria-label", "Application submitted")
    expect(items[1]).not.toHaveAttribute("data-unread")
  })

  it("links every row to the notification target", async () => {
    fetchNotificationsMock.mockResolvedValue(
      pageOf([notification({ action_url: "/staff/applications/42" })]),
    )
    renderPage()

    const item = await screen.findByTestId("notification-item")

    expect(item).toHaveAttribute("href", "/staff/applications/42")
  })

  it("shows an empty state when there are no notifications", async () => {
    renderPage()

    expect(await screen.findByText("No notifications yet.")).toBeInTheDocument()
  })

  it("shows an error banner when loading fails", async () => {
    fetchNotificationsMock.mockRejectedValue(new ApiError("Server exploded.", 500))
    renderPage()

    expect(await screen.findByRole("alert")).toHaveTextContent("Server exploded.")
  })

  it("paginates to the next page when requested", async () => {
    const user = userEvent.setup()
    fetchNotificationsMock
      .mockResolvedValueOnce(
        pageOf([notification()], { current_page: 1, last_page: 2, total: 11 }),
      )
      .mockResolvedValueOnce(
        pageOf(
          [notification({ id: "00000000-0000-4000-8000-000000000009", title: "Older update" })],
          { current_page: 2, last_page: 2, total: 11 },
        ),
      )
    renderPage()

    await screen.findByTestId("notification-item")
    await user.click(screen.getByRole("button", { name: "Next page" }))

    expect(await screen.findByText("Older update")).toBeInTheDocument()
    expect(fetchNotificationsMock).toHaveBeenLastCalledWith({ page: 2, per_page: 10 })
    expect(screen.getByText(/page 2 of 2/i)).toBeInTheDocument()
  })

  it("disables pagination buttons on a single page", async () => {
    fetchNotificationsMock.mockResolvedValue(pageOf([notification()], { total: 1 }))
    renderPage()
    await screen.findByTestId("notification-item")

    expect(screen.getByRole("button", { name: "Previous page" })).toBeDisabled()
    expect(screen.getByRole("button", { name: "Next page" })).toBeDisabled()
  })

  it("marks all as read and refreshes the badge count", async () => {
    const user = userEvent.setup()
    markAllNotificationsReadMock.mockResolvedValue({ count: 0 })
    fetchNotificationsMock.mockResolvedValue(
      pageOf([notification()], { total: 1 }),
    )
    notificationsStateRef.current.unreadCount = 4
    renderPage()

    await user.click(await screen.findByTestId("mark-all-read-page"))

    await waitFor(() => {
      expect(markAllNotificationsReadMock).toHaveBeenCalled()
    })
    expect(notificationsStateRef.current.refreshUnreadCount).toHaveBeenCalled()
    expect(toastMock).toHaveBeenCalledWith({ title: "All notifications marked as read" })
  })

  it("keeps the mark-all button disabled when there is nothing unread", async () => {
    renderPage()
    await waitFor(() => screen.getByTestId("mark-all-read-page"))

    expect(screen.getByTestId("mark-all-read-page")).toBeDisabled()
  })

  it("shows a toast instead of crashing when marking all fails", async () => {
    const user = userEvent.setup()
    markAllNotificationsReadMock.mockRejectedValue(new ApiError("Request failed.", 500))
    fetchNotificationsMock.mockResolvedValue(pageOf([notification()], { total: 1 }))
    notificationsStateRef.current.unreadCount = 2
    renderPage()

    await user.click(await screen.findByTestId("mark-all-read-page"))

    await waitFor(() => {
      expect(toastMock).toHaveBeenCalledWith({
        title: "Action failed",
        description: "Request failed.",
        variant: "error",
      })
    })
  })
})
