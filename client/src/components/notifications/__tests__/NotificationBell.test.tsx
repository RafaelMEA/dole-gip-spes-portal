import { describe, expect, it, vi, beforeEach } from "vitest"
import { render, screen, waitFor } from "@testing-library/react"
import userEvent from "@testing-library/user-event"
import { MemoryRouter } from "react-router-dom"
import { NotificationBell } from "@/components/notifications/NotificationBell"
import type { AppNotification } from "@/types/notifications"

const { fetchNotificationsMock, markNotificationReadMock, markAllNotificationsReadMock } =
  vi.hoisted(() => ({
    fetchNotificationsMock: vi.fn(),
    markNotificationReadMock: vi.fn(),
    markAllNotificationsReadMock: vi.fn(),
  }))

const { authUserRef, notificationsStateRef } = vi.hoisted(() => ({
  authUserRef: { current: null as null | { id: number; role: string } },
  notificationsStateRef: {
    current: {
      unreadCount: 0,
      refreshUnreadCount: vi.fn().mockResolvedValue(undefined),
      decrementUnread: vi.fn(),
      clearUnread: vi.fn(),
    },
  },
}))

vi.mock("@/api/notifications", () => ({
  fetchNotifications: fetchNotificationsMock,
  markNotificationRead: markNotificationReadMock,
  markAllNotificationsRead: markAllNotificationsReadMock,
}))

vi.mock("@/auth/useAuth", () => ({
  useAuth: () => ({ user: authUserRef.current }),
}))

vi.mock("@/notifications/useNotifications", () => ({
  useNotifications: () => notificationsStateRef.current,
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

function renderBell() {
  return render(
    <MemoryRouter>
      <NotificationBell />
    </MemoryRouter>,
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  authUserRef.current = { id: 1, role: "student" }
  fetchNotificationsMock.mockResolvedValue({
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: 6,
    total: 0,
  })
})

describe("formatBadgeCount via NotificationBell", () => {
  it("hides the badge when there are no unread notifications", () => {
    notificationsStateRef.current.unreadCount = 0
    renderBell()
    expect(screen.queryByTestId("unread-badge")).not.toBeInTheDocument()
  })

  it("shows the unread count", () => {
    notificationsStateRef.current.unreadCount = 3
    renderBell()
    expect(screen.getByTestId("unread-badge")).toHaveTextContent("3")
  })

  it("caps the badge at 99+", () => {
    notificationsStateRef.current.unreadCount = 150
    renderBell()
    expect(screen.getByTestId("unread-badge")).toHaveTextContent("99+")
  })

  it("announces the unread count in the trigger label", () => {
    notificationsStateRef.current.unreadCount = 2
    renderBell()
    expect(screen.getByLabelText("Notifications, 2 unread")).toBeInTheDocument()
  })
})

describe("NotificationBell dropdown", () => {
  it("loads and lists notifications when opened", async () => {
    const user = userEvent.setup()
    fetchNotificationsMock.mockResolvedValue({
      data: [
        notification(),
        notification({
          id: "00000000-0000-4000-8000-000000000002",
          title: "Documents requested",
          action_url: "/student/applications/10/documents",
          read_at: new Date().toISOString(),
        }),
      ],
      current_page: 1,
      last_page: 1,
      per_page: 6,
      total: 2,
    })
    renderBell()

    await user.click(screen.getByTestId("notification-bell"))

    expect(await screen.findByText("Application submitted")).toBeInTheDocument()
    expect(screen.getByText("Documents requested")).toBeInTheDocument()
    expect(fetchNotificationsMock).toHaveBeenCalledWith({ per_page: 6 })
  })

  it("shows an empty state when there are no notifications", async () => {
    const user = userEvent.setup()
    renderBell()

    await user.click(screen.getByTestId("notification-bell"))

    expect(await screen.findByText("No notifications yet.")).toBeInTheDocument()
  })

  it("shows an error when loading fails", async () => {
    const user = userEvent.setup()
    fetchNotificationsMock.mockRejectedValue(new Error("Network down"))
    renderBell()

    await user.click(screen.getByTestId("notification-bell"))

    expect(await screen.findByRole("alert")).toHaveTextContent(/couldn't load notifications/i)
  })

  it("marks a notification as read and decrements the counter when clicked", async () => {
    const user = userEvent.setup()
    markNotificationReadMock.mockResolvedValue(undefined)
    fetchNotificationsMock.mockResolvedValue({
      data: [notification()],
      current_page: 1,
      last_page: 1,
      per_page: 6,
      total: 1,
    })
    renderBell()

    await user.click(screen.getByTestId("notification-bell"))
    await user.click(await screen.findByText("Application submitted"))

    await waitFor(() => {
      expect(markNotificationReadMock).toHaveBeenCalledWith(
        "00000000-0000-4000-8000-000000000001",
      )
    })
    expect(notificationsStateRef.current.decrementUnread).toHaveBeenCalled()
  })

  it("does not call the read endpoint for already-read notifications", async () => {
    const user = userEvent.setup()
    fetchNotificationsMock.mockResolvedValue({
      data: [notification({ read_at: new Date().toISOString() })],
      current_page: 1,
      last_page: 1,
      per_page: 6,
      total: 1,
    })
    renderBell()

    await user.click(screen.getByTestId("notification-bell"))
    await user.click(await screen.findByText("Application submitted"))

    expect(markNotificationReadMock).not.toHaveBeenCalled()
    expect(notificationsStateRef.current.decrementUnread).not.toHaveBeenCalled()
  })

  it("marks everything as read", async () => {
    const user = userEvent.setup()
    markAllNotificationsReadMock.mockResolvedValue({ count: 0 })
    fetchNotificationsMock.mockResolvedValue({
      data: [notification()],
      current_page: 1,
      last_page: 1,
      per_page: 6,
      total: 1,
    })
    notificationsStateRef.current.unreadCount = 1
    renderBell()

    await user.click(screen.getByTestId("notification-bell"))
    await user.click(await screen.findByTestId("mark-all-read"))

    await waitFor(() => {
      expect(markAllNotificationsReadMock).toHaveBeenCalled()
    })
    expect(notificationsStateRef.current.clearUnread).toHaveBeenCalled()
  })

  it("links to the staff notifications page for staff users", async () => {
    const user = userEvent.setup()
    authUserRef.current = { id: 2, role: "staff" }
    fetchNotificationsMock.mockResolvedValue({
      data: [notification()],
      current_page: 1,
      last_page: 1,
      per_page: 6,
      total: 1,
    })
    renderBell()

    await user.click(screen.getByTestId("notification-bell"))
    const link = await screen.findByRole("link", { name: /view all notifications/i })

    expect(link).toHaveAttribute("href", "/staff/notifications")
  })

  it("links to the student notifications page for students", async () => {
    const user = userEvent.setup()
    fetchNotificationsMock.mockResolvedValue({
      data: [],
      current_page: 1,
      last_page: 1,
      per_page: 6,
      total: 0,
    })
    renderBell()

    await user.click(screen.getByTestId("notification-bell"))
    const link = await screen.findByRole("link", { name: /view all notifications/i })

    expect(link).toHaveAttribute("href", "/student/notifications")
  })
})
