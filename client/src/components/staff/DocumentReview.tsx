import { useMemo, useState } from "react"
import {
  CheckCircle2,
  Download,
  Eye,
  Loader2,
  Paperclip,
  XCircle,
} from "lucide-react"
import { verifyDocument } from "@/api/staff"
import type { ApplicationDocument, DocumentVerificationAction } from "@/types/api"
import { ApiError } from "@/lib/api"
import { formatDateTime, formatFileSize } from "@/lib/format"
import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { DocumentStatusBadge } from "@/components/StatusBadge"
import { useToast } from "@/toast/useToast"

export const REJECTION_REASON_MIN_LENGTH = 10
export const REJECTION_REASON_MAX_LENGTH = 1000

interface DocumentReviewProps {
  applicationId: number
  documents: ApplicationDocument[]
  requiredCount: number
  onChanged: () => void | Promise<void>
}

interface DocumentReviewSummary {
  verified: number
  rejected: number
  pending: number
}

export function DocumentReview({ applicationId, documents, requiredCount, onChanged }: DocumentReviewProps) {
  const { toast } = useToast()

  const [target, setTarget] = useState<ApplicationDocument | null>(null)
  const [mode, setMode] = useState<DocumentVerificationAction>("verified")
  const [reason, setReason] = useState("")
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  const summary: DocumentReviewSummary = useMemo(() => {
    const counts = { verified: 0, rejected: 0, pending: 0 }
    for (const document of documents) {
      if (document.verification_status === "verified") counts.verified += 1
      else if (document.verification_status === "rejected") counts.rejected += 1
      else counts.pending += 1
    }
    return counts
  }, [documents])

  const trimmedReason = reason.trim()
  const reasonValid =
    trimmedReason.length >= REJECTION_REASON_MIN_LENGTH &&
    trimmedReason.length <= REJECTION_REASON_MAX_LENGTH

  function openDialog(document: ApplicationDocument, nextMode: DocumentVerificationAction) {
    setTarget(document)
    setMode(nextMode)
    setReason("")
    setError(null)
  }

  function closeDialog() {
    if (saving) return
    setTarget(null)
    setMode("verified")
    setReason("")
    setError(null)
  }

  async function handleSubmit() {
    if (!target) return
    if (mode === "rejected" && !reasonValid) {
      setError(
        `Please provide a clear rejection reason (at least ${REJECTION_REASON_MIN_LENGTH} characters).`,
      )
      return
    }

    setSaving(true)
    setError(null)
    try {
      await verifyDocument(
        applicationId,
        target.id,
        mode,
        mode === "rejected" ? trimmedReason : undefined,
      )
      toast({
        title: mode === "verified" ? "Document verified" : "Document rejected",
        description:
          mode === "verified"
            ? "The document has been verified."
            : "The document was rejected with the stated reason.",
        variant: mode === "verified" ? "success" : "error",
      })
      setTarget(null)
      setReason("")
      await onChanged()
    } catch (err) {
      const message = err instanceof ApiError ? err.message : "Unable to update the document."
      setError(message)
      toast({ title: "Unable to update", description: message, variant: "error" })
    } finally {
      setSaving(false)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Document review</CardTitle>
        <CardDescription>
          {documents.length === 0
            ? "No documents uploaded yet."
            : `${documents.length} document${documents.length === 1 ? "" : "s"} on this application.`}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="rounded-lg border bg-muted/30 p-3">
          <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-4">
            <div>
              <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                Required
              </dt>
              <dd className="mt-0.5 font-semibold">{requiredCount}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                Verified
              </dt>
              <dd className="mt-0.5 font-semibold text-emerald-600">✓ {summary.verified}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                Rejected
              </dt>
              <dd className="mt-0.5 font-semibold text-red-600">✗ {summary.rejected}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                Pending
              </dt>
              <dd className="mt-0.5 font-semibold text-amber-600">○ {summary.pending}</dd>
            </div>
          </dl>
        </div>

        {documents.length === 0 ? (
          <p className="text-sm text-muted-foreground">No documents uploaded yet.</p>
        ) : (
          <ul className="space-y-2">
            {documents.map((document) => {
              const isPending = document.verification_status === "pending"
              return (
                <li
                  key={document.id}
                  className="flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <Paperclip className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                      <p className="truncate text-sm font-medium">
                        {document.requirement?.name ?? document.file_name}
                      </p>
                      <DocumentStatusBadge
                        status={document.verification_status}
                        label={document.verification_label}
                      />
                    </div>
                    <p className="truncate text-xs text-muted-foreground">
                      {document.file_name} · {document.mime_type ?? "file"} ·{" "}
                      {formatFileSize(document.file_size)}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      Uploaded {formatDateTime(document.uploaded_at)}
                    </p>
                    {document.verification_status === "verified" ? (
                      <p className="text-xs text-emerald-700">
                        Verified by {document.verified_by ?? "staff"} on{" "}
                        {formatDateTime(document.verified_at)}
                      </p>
                    ) : null}
                    {document.verification_status === "rejected" ? (
                      <p className="text-xs text-red-600">Rejected: {document.rejection_reason}</p>
                    ) : null}
                  </div>
                  <div className="flex shrink-0 items-center gap-2">
                    <Button
                      nativeButton={false}
                      variant="outline"
                      size="sm"
                      render={<a href={document.view_url} target="_blank" rel="noreferrer" />}
                    >
                      <Eye aria-hidden="true" />
                      View
                    </Button>
                    <Button
                      nativeButton={false}
                      variant="outline"
                      size="sm"
                      render={<a href={document.download_url} target="_blank" rel="noreferrer" />}
                    >
                      <Download aria-hidden="true" />
                      Download
                    </Button>
                    {isPending ? (
                      <>
                        <Button size="sm" onClick={() => openDialog(document, "verified")}>
                          <CheckCircle2 aria-hidden="true" />
                          Verify
                        </Button>
                        <Button
                          variant="outline"
                          size="sm"
                          className="text-destructive hover:text-destructive"
                          onClick={() => openDialog(document, "rejected")}
                        >
                          <XCircle aria-hidden="true" />
                          Reject
                        </Button>
                      </>
                    ) : null}
                  </div>
                </li>
              )
            })}
          </ul>
        )}
      </CardContent>

      <Dialog
        open={target !== null && mode === "verified"}
        onOpenChange={(open) => {
          if (!open) closeDialog()
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Verify document?</DialogTitle>
            <DialogDescription>
              Are you sure this document satisfies the requirement?
            </DialogDescription>
          </DialogHeader>
          {error ? (
            <p
              role="alert"
              className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
            >
              {error}
            </p>
          ) : null}
          <DialogFooter>
            <Button variant="outline" onClick={closeDialog} disabled={saving}>
              Cancel
            </Button>
            <Button onClick={handleSubmit} disabled={saving}>
              {saving ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              {saving ? "Verifying..." : "Verify Document"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog
        open={target !== null && mode === "rejected"}
        onOpenChange={(open) => {
          if (!open) closeDialog()
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Reject document</DialogTitle>
            <DialogDescription>
              Explain why this document is not acceptable. The student will see this reason.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-2">
            <Label htmlFor="rejection-reason">Rejection reason (required)</Label>
            <Textarea
              id="rejection-reason"
              value={reason}
              onChange={(event) => {
                setReason(event.target.value)
                setError(null)
              }}
              maxLength={REJECTION_REASON_MAX_LENGTH}
              placeholder="e.g. The uploaded COR is from the previous semester. Please provide the current one."
              aria-invalid={error !== null}
              aria-describedby="rejection-reason-hint"
            />
            <div className="flex items-center justify-between gap-2">
              <p
                id="rejection-reason-hint"
                className={cn(
                  "text-xs",
                  !reasonValid && trimmedReason.length > 0
                    ? "text-destructive"
                    : "text-muted-foreground",
                )}
              >
                {trimmedReason.length > 0 && !reasonValid
                  ? `At least ${REJECTION_REASON_MIN_LENGTH} characters required.`
                  : `Minimum ${REJECTION_REASON_MIN_LENGTH} characters.`}
              </p>
              <p className="text-xs text-muted-foreground">
                {reason.length}/{REJECTION_REASON_MAX_LENGTH}
              </p>
            </div>
          </div>
          {error ? (
            <p
              role="alert"
              className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
            >
              {error}
            </p>
          ) : null}
          <DialogFooter>
            <Button variant="outline" onClick={closeDialog} disabled={saving}>
              Cancel
            </Button>
            <Button
              className="text-destructive hover:text-destructive"
              onClick={handleSubmit}
              disabled={saving}
            >
              {saving ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              {saving ? "Rejecting..." : "Reject Document"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Card>
  )
}
