import { describe, expect, it, vi, beforeEach } from "vitest"
import { render, screen, waitFor } from "@testing-library/react"
import userEvent from "@testing-library/user-event"
import { HistoryFeed, HistoryTimeline } from "@/components/HistoryTimeline"
import { ApiError } from "@/lib/api"
import type { AuditEvent, HistoryPage } from "@/types/api"

const approvedEvent: AuditEvent = {
  id: 4,
  source: "status_history",
  action: "approve",
  label: "Application approved",
  actor: "Maria Santos",
  occurred_at: "2026-08-21T10:12:00.000000Z",
  reason: null,
}

const returnedEvent: AuditEvent = {
  id: 3,
  source: "status_history",
  action: "return_for_correction",
  label: "Returned for correction",
  actor: "Maria Santos",
  occurred_at: "2026-08-20T14:10:00.000000Z",
  reason: "Barangay clearance required.",
}

const submittedEvent: AuditEvent = {
  id: 1,
  source: "status_history",
  action: "submit",
  label: "Application submitted",
  actor: "Juan Dela Cruz",
  occurred_at: "2026-08-20T09:12:00.000000Z",
  reason: null,
}

const verifiedEvent: AuditEvent = {
  id: 9,
  source: "audit_log",
  action: "document.verified",
  label: "Document verified",
  actor: "Maria Santos",
  occurred_at: "2026-08-20T13:00:00.000000Z",
  reason: null,
  old_values: { verification_status: "pending" },
  new_values: { verification_status: "verified" },
}

const newestFirst = [approvedEvent, verifiedEvent, returnedEvent, submittedEvent]

function pageOf(events: AuditEvent[], overrides: Partial<HistoryPage> = {}): HistoryPage {
  return {
    data: events,
    current_page: 1,
    first_page_url: null,
    from: events.length ? 1 : null,
    last_page: 1,
    last_page_url: null,
    next_page_url: null,
    path: "/api/test",
    per_page: 25,
    prev_page_url: null,
    to: events.length,
    total: events.length,
    ...overrides,
  }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe("HistoryTimeline", () => {
  it("renders every event with its label and actor", () => {
    render(<HistoryTimeline events={newestFirst} />)

    expect(screen.getByText("Application approved")).toBeInTheDocument()
    expect(screen.getAllByText(/by Maria Santos/)).toHaveLength(3)
    expect(screen.getByText("Document verified")).toBeInTheDocument()
    expect(screen.getByText(/by Juan Dela Cruz/)).toBeInTheDocument()
  })

  it("preserves the chronological order provided by the API", () => {
    render(<HistoryTimeline events={newestFirst} />)

    const labels = screen
      .getAllByTestId("history-timeline")[0]
      .querySelectorAll("p")
    const text = Array.from(labels).map((node) => node.textContent ?? "")
    expect(text.findIndex((t) => t.includes("Application approved"))).toBeLessThan(
      text.findIndex((t) => t.includes("Document verified")),
    )
    expect(text.findIndex((t) => t.includes("Document verified"))).toBeLessThan(
      text.findIndex((t) => t.includes("Returned for correction")),
    )
    expect(text.findIndex((t) => t.includes("Returned for correction"))).toBeLessThan(
      text.findIndex((t) => t.includes("Application submitted")),
    )
  })

  it("shows the reason when one is recorded", () => {
    render(<HistoryTimeline events={[returnedEvent]} />)

    expect(screen.getByText("Barangay clearance required.")).toBeInTheDocument()
  })

  it("renders date information for each event", () => {
    render(<HistoryTimeline events={[submittedEvent]} />)

    expect(screen.getByText(/Aug 20, 2026/)).toBeInTheDocument()
  })

  it("hides internal old/new values from students", () => {
    render(<HistoryTimeline events={[verifiedEvent]} showChanges={false} />)

    expect(screen.queryByText("Verification:")).not.toBeInTheDocument()
    expect(screen.queryByText("pending")).not.toBeInTheDocument()
  })

  it("shows changed values to staff when enabled", () => {
    render(<HistoryTimeline events={[verifiedEvent]} showChanges />)

    expect(screen.getByText("Verification:")).toBeInTheDocument()
    expect(screen.getByText("pending")).toBeInTheDocument()
    expect(screen.getByText("verified")).toBeInTheDocument()
  })

  it("shows an empty state when no history exists", () => {
    render(<HistoryTimeline events={[]} />)

    expect(screen.getByText("No history available.")).toBeInTheDocument()
  })
})

describe("HistoryFeed", () => {
  it("renders a loading skeleton before data arrives", async () => {
    let resolvePage: (value: HistoryPage) => void = () => {}
    const fetchPage = vi.fn(
      () =>
        new Promise<HistoryPage>((resolve) => {
          resolvePage = resolve
        }),
    )

    render(<HistoryFeed fetchPage={fetchPage} />)

    expect(screen.getByTestId("history-loading")).toBeInTheDocument()

    resolvePage(pageOf(newestFirst))
    await waitFor(() => {
      expect(screen.getByTestId("history-feed")).toBeInTheDocument()
    })
  })

  it("fetches and renders the first page", async () => {
    const fetchPage = vi.fn().mockResolvedValue(pageOf(newestFirst))

    render(<HistoryFeed fetchPage={fetchPage} />)

    await waitFor(() => {
      expect(fetchPage).toHaveBeenCalledWith(1)
    })
    expect(await screen.findByText("Application approved")).toBeInTheDocument()
  })

  it("renders an error state when the request fails", async () => {
    const fetchPage = vi.fn().mockRejectedValue(new ApiError("Something went wrong.", 500))

    render(<HistoryFeed fetchPage={fetchPage} />)

    await waitFor(() => {
      expect(screen.getByRole("alert")).toHaveTextContent("Something went wrong.")
    })
  })

  it("omits pagination controls on a single page", async () => {
    const fetchPage = vi.fn().mockResolvedValue(pageOf([approvedEvent]))

    render(<HistoryFeed fetchPage={fetchPage} />)

    expect(await screen.findByText("Application approved")).toBeInTheDocument()
    expect(screen.queryByRole("button", { name: /Previous/ })).not.toBeInTheDocument()
    expect(screen.queryByRole("button", { name: /Next/ })).not.toBeInTheDocument()
  })

  it("paginates through multiple pages", async () => {
    const user = userEvent.setup()
    const fetchPage = vi
      .fn()
      .mockResolvedValueOnce(
        pageOf([approvedEvent], { current_page: 1, last_page: 2, total: 3 }),
      )
      .mockResolvedValueOnce(
        pageOf([returnedEvent, submittedEvent], { current_page: 2, last_page: 2, total: 3 }),
      )

    render(<HistoryFeed fetchPage={fetchPage} />)
    expect(await screen.findByText("Application approved")).toBeInTheDocument()

    await user.click(screen.getByRole("button", { name: /Next/ }))

    await waitFor(() => {
      expect(fetchPage).toHaveBeenLastCalledWith(2)
    })
    expect(await screen.findByText("Application submitted")).toBeInTheDocument()
    expect(screen.getByRole("button", { name: /Previous/ })).toBeEnabled()
    expect(screen.getByRole("button", { name: /Next/ })).toBeDisabled()
  })

  it("refetches when the refresh key changes", async () => {
    const fetchPage = vi.fn().mockResolvedValue(pageOf([approvedEvent]))
    const { rerender } = render(<HistoryFeed fetchPage={fetchPage} refreshKey="t1" />)

    await screen.findByText("Application approved")
    expect(fetchPage).toHaveBeenCalledTimes(1)

    rerender(<HistoryFeed fetchPage={fetchPage} refreshKey="t2" />)

    await waitFor(() => {
      expect(fetchPage).toHaveBeenCalledTimes(2)
    })
  })
})
