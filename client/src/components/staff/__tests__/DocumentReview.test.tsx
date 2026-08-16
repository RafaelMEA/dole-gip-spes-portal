import { describe, expect, it, vi, beforeEach } from "vitest"
import { render, screen, within, waitFor } from "@testing-library/react"
import userEvent from "@testing-library/user-event"
import { DocumentReview, REJECTION_REASON_MIN_LENGTH } from "@/components/staff/DocumentReview"
import { ApiError } from "@/lib/api"
import type { ApplicationDocument } from "@/types/api"

const { verifyDocumentMock, toastMock } = vi.hoisted(() => ({
  verifyDocumentMock: vi.fn(),
  toastMock: vi.fn(),
}))

vi.mock("@/api/staff", () => ({
  verifyDocument: verifyDocumentMock,
}))

vi.mock("@/toast/useToast", () => ({
  useToast: () => ({ toast: toastMock }),
}))

const requirement = (id: number, name: string) => ({ id, name, description: null })

const verifiedDocument: ApplicationDocument = {
  id: 1,
  application_id: 10,
  requirement_id: 1,
  requirement: requirement(1, "Proof of Enrollment"),
  file_name: "cor.pdf",
  mime_type: "application/pdf",
  file_size: 2048,
  verification_status: "verified",
  verification_label: "Verified",
  rejection_reason: null,
  uploaded_at: "2026-08-01T10:00:00+08:00",
  verified_at: "2026-08-02T11:30:00+08:00",
  verified_by: "Staff Person",
  view_url: "/api/documents/1/download?disposition=inline",
  download_url: "/api/documents/1/download",
}

const rejectedDocument: ApplicationDocument = {
  id: 2,
  application_id: 10,
  requirement_id: 2,
  requirement: requirement(2, "Barangay Clearance"),
  file_name: "clearance.jpg",
  mime_type: "image/jpeg",
  file_size: 4096,
  verification_status: "rejected",
  verification_label: "Rejected",
  rejection_reason: "Blurred scan, please re-upload a clearer copy.",
  uploaded_at: "2026-08-01T12:00:00+08:00",
  verified_at: "2026-08-03T09:00:00+08:00",
  verified_by: "Staff Person",
  view_url: "/api/documents/2/download?disposition=inline",
  download_url: "/api/documents/2/download",
}

const pendingDocument: ApplicationDocument = {
  id: 3,
  application_id: 10,
  requirement_id: 3,
  requirement: requirement(3, "Certificate of Good Moral"),
  file_name: "moral.pdf",
  mime_type: "application/pdf",
  file_size: 1024,
  verification_status: "pending",
  verification_label: "Pending",
  rejection_reason: null,
  uploaded_at: "2026-08-04T08:00:00+08:00",
  verified_at: null,
  view_url: "/api/documents/3/download?disposition=inline",
  download_url: "/api/documents/3/download",
}

const sampleDocuments = [verifiedDocument, rejectedDocument, pendingDocument]

function renderReview(documents: ApplicationDocument[] = sampleDocuments, requiredCount = 4) {
  const onChanged = vi.fn().mockResolvedValue(undefined)
  const utils = render(
    <DocumentReview
      applicationId={10}
      documents={documents}
      requiredCount={requiredCount}
      onChanged={onChanged}
    />,
  )
  return { onChanged, ...utils }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe("DocumentReview", () => {
  it("shows the summary counts", () => {
    renderReview()
    expect(screen.getByText("Required")).toBeInTheDocument()
    expect(screen.getAllByText("Verified").length).toBeGreaterThan(0)
    expect(screen.getAllByText("Rejected").length).toBeGreaterThan(0)
    expect(screen.getAllByText("Pending").length).toBeGreaterThan(0)
    expect(screen.getByText("4")).toBeInTheDocument()
    expect(screen.getByText("✓ 1")).toBeInTheDocument()
    expect(screen.getByText("✗ 1")).toBeInTheDocument()
    expect(screen.getByText("○ 1")).toBeInTheDocument()
  })

  it("renders each document with its status", () => {
    renderReview()
    expect(screen.getByText("Proof of Enrollment")).toBeInTheDocument()
    expect(screen.getByText("Barangay Clearance")).toBeInTheDocument()
    expect(screen.getByText("Certificate of Good Moral")).toBeInTheDocument()

    const statuses = screen.getAllByText(/Verified|Rejected|Pending/).map((node) => node.textContent)
    expect(statuses).toEqual(expect.arrayContaining(["Verified", "Rejected", "Pending"]))
  })

  it("shows reviewer info on verified documents and reason on rejected documents", () => {
    renderReview()
    expect(screen.getByText(/Verified by Staff Person/)).toBeInTheDocument()
    expect(screen.getByText(/Rejected: Blurred scan/)).toBeInTheDocument()
  })

  it("only offers Verify/Reject actions on pending documents", () => {
    renderReview()
    const pendingRow = screen.getByText("Certificate of Good Moral").closest("li")
    expect(pendingRow).not.toBeNull()
    if (!pendingRow) return
    expect(within(pendingRow as HTMLElement).getByRole("button", { name: "Verify" })).toBeInTheDocument()
    expect(within(pendingRow as HTMLElement).getByRole("button", { name: "Reject" })).toBeInTheDocument()

    const verifiedRow = screen.getByText("Proof of Enrollment").closest("li")
    if (!verifiedRow) return
    expect(within(verifiedRow as HTMLElement).queryByRole("button", { name: "Verify" })).not.toBeInTheDocument()
    expect(within(verifiedRow as HTMLElement).queryByRole("button", { name: "Reject" })).not.toBeInTheDocument()
  })

  it("renders View and Download links with the correct hrefs", () => {
    renderReview()
    const view = screen
      .getAllByRole("button", { name: "View" })
      .find((element) => element.getAttribute("href") === pendingDocument.view_url)
    expect(view).toBeDefined()
    expect(view).toHaveAttribute("target", "_blank")
    const download = screen
      .getAllByRole("button", { name: "Download" })
      .find((element) => element.getAttribute("href") === pendingDocument.download_url)
    expect(download).toBeDefined()
  })

  it("shows an empty state when there are no documents", () => {
    renderReview([])
    expect(screen.getAllByText("No documents uploaded yet.").length).toBeGreaterThan(0)
  })

  describe("verify flow", () => {
    it("verifies a document and reloads", async () => {
      const user = userEvent.setup()
      const { onChanged } = renderReview()
      verifyDocumentMock.mockResolvedValue({ ...pendingDocument, verification_status: "verified" })

      await user.click(screen.getByRole("button", { name: "Verify" }))
      const dialog = screen.getByRole("dialog")
      expect(within(dialog).getByText("Verify document?")).toBeInTheDocument()

      await user.click(within(dialog).getByRole("button", { name: "Verify Document" }))

      await waitFor(() => {
        expect(verifyDocumentMock).toHaveBeenCalledTimes(1)
      })
      expect(verifyDocumentMock).toHaveBeenCalledWith(10, 3, "verified", undefined)
      expect(toastMock).toHaveBeenCalledWith(
        expect.objectContaining({ title: "Document verified", variant: "success" }),
      )
      await waitFor(() => {
        expect(onChanged).toHaveBeenCalledTimes(1)
      })
      await waitFor(() => {
        expect(screen.queryByRole("dialog")).not.toBeInTheDocument()
      })
    })

    it("shows a failure message when the API errors", async () => {
      const user = userEvent.setup()
      renderReview()
      verifyDocumentMock.mockRejectedValue(new ApiError("Document was already reviewed.", 422))

      await user.click(screen.getByRole("button", { name: "Verify" }))
      const dialog = screen.getByRole("dialog")
      await user.click(within(dialog).getByRole("button", { name: "Verify Document" }))

      await waitFor(() => {
        expect(screen.getByRole("alert")).toHaveTextContent("Document was already reviewed.")
      })
      expect(toastMock).toHaveBeenCalledWith(
        expect.objectContaining({ title: "Unable to update", variant: "error" }),
      )
      expect(screen.getByRole("dialog")).toBeInTheDocument()
    })

    it("disables the confirm button while saving and prevents double submission", async () => {
      const user = userEvent.setup()
      renderReview()
      let resolveVerify: (value: ApplicationDocument) => void = () => {}
      verifyDocumentMock.mockImplementation(
        () =>
          new Promise<ApplicationDocument>((resolve) => {
            resolveVerify = resolve
          }),
      )

      await user.click(screen.getByRole("button", { name: "Verify" }))
      const dialog = screen.getByRole("dialog")
      const confirm = within(dialog).getByRole("button", { name: "Verify Document" })
      await user.click(confirm)

      expect(confirm).toBeDisabled()
      expect(within(dialog).getByRole("button", { name: "Verifying..." })).toBeInTheDocument()

      await user.click(confirm)
      resolveVerify({ ...pendingDocument, verification_status: "verified" })

      await waitFor(() => {
        expect(verifyDocumentMock).toHaveBeenCalledTimes(1)
      })
    })
  })

  describe("reject flow", () => {
    it("blocks submission without a reason", async () => {
      const user = userEvent.setup()
      renderReview()

      await user.click(screen.getByRole("button", { name: "Reject" }))
      const dialog = screen.getByRole("dialog")
      expect(within(dialog).getByText("Reject document")).toBeInTheDocument()

      await user.click(within(dialog).getByRole("button", { name: "Reject Document" }))

      expect(verifyDocumentMock).not.toHaveBeenCalled()
      expect(screen.getByRole("alert")).toHaveTextContent("Please provide a clear rejection reason")
      expect(screen.getByRole("dialog")).toBeInTheDocument()
    })

    it("blocks submission when the reason is too short", async () => {
      const user = userEvent.setup()
      renderReview()

      await user.click(screen.getByRole("button", { name: "Reject" }))
      const dialog = screen.getByRole("dialog")
      await user.type(within(dialog).getByLabelText(/Rejection reason/), "No")

      await user.click(within(dialog).getByRole("button", { name: "Reject Document" }))

      expect(verifyDocumentMock).not.toHaveBeenCalled()
      expect(screen.getByRole("alert")).toHaveTextContent(
        `Please provide a clear rejection reason (at least ${REJECTION_REASON_MIN_LENGTH} characters).`,
      )
    })

    it("rejects a document with a valid reason", async () => {
      const user = userEvent.setup()
      const { onChanged } = renderReview()
      verifyDocumentMock.mockResolvedValue({ ...pendingDocument, verification_status: "rejected" })
      const reason = "The uploaded COR is from the previous semester. Please provide the current one."

      await user.click(screen.getByRole("button", { name: "Reject" }))
      const dialog = screen.getByRole("dialog")
      await user.type(within(dialog).getByLabelText(/Rejection reason/), reason)

      await user.click(within(dialog).getByRole("button", { name: "Reject Document" }))

      await waitFor(() => {
        expect(verifyDocumentMock).toHaveBeenCalledTimes(1)
      })
      expect(verifyDocumentMock).toHaveBeenCalledWith(10, 3, "rejected", reason)
      expect(toastMock).toHaveBeenCalledWith(
        expect.objectContaining({ title: "Document rejected", variant: "error" }),
      )
      await waitFor(() => {
        expect(onChanged).toHaveBeenCalledTimes(1)
      })
      await waitFor(() => {
        expect(screen.queryByRole("dialog")).not.toBeInTheDocument()
      })
    })

    it("shows the live character hint and counter", async () => {
      const user = userEvent.setup()
      renderReview()

      await user.click(screen.getByRole("button", { name: "Reject" }))
      const dialog = screen.getByRole("dialog")
      const input = within(dialog).getByLabelText(/Rejection reason/)

      await user.type(input, "short")
      expect(within(dialog).getByText(/At least 10 characters required/)).toBeInTheDocument()
      expect(within(dialog).getByText(`5/1000`)).toBeInTheDocument()
    })
  })
})
