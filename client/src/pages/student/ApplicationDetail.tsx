import { useCallback, useState } from "react"
import { Link, useParams } from "react-router-dom"
import {
  ArrowLeft,
  CalendarRange,
  Download,
  Loader2,
  MapPin,
  Paperclip,
  Send,
  Trash2,
  Upload,
  User,
} from "lucide-react"
import { deleteDocument, fetchApplication, submitApplication, uploadDocument, withdrawApplication } from "@/api/student"
import { useAsync } from "@/lib/useAsync"
import { ApiError } from "@/lib/api"
import { formatDate, formatDateTime, formatFileSize } from "@/lib/format"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { PageHeader } from "@/components/PageHeader"
import { StatusTimeline } from "@/components/StatusTimeline"
import { ConfirmDialog } from "@/components/ConfirmDialog"
import { ApplicationStatusBadge, DeploymentStatusBadge, DocumentStatusBadge } from "@/components/StatusBadge"
import { FullPageLoader } from "@/components/FullPageLoader"
import { useToast } from "@/toast/useToast"
import type { ApplicationDocument } from "@/types/api"

const ACTIVE_STATUSES = ["submitted", "under_review", "documents_incomplete", "documents_verified", "approved", "for_deployment", "deployed"]

export function StudentApplicationDetailPage() {
  const { id } = useParams<{ id: string }>()
  const applicationId = Number(id)
  const { toast } = useToast()

  const fetcher = useCallback(() => fetchApplication(applicationId), [applicationId])
  const { data: application, loading, error, reload } = useAsync(fetcher)

  const [submitOpen, setSubmitOpen] = useState(false)
  const [withdrawOpen, setWithdrawOpen] = useState(false)
  const [uploadOpen, setUploadOpen] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<ApplicationDocument | null>(null)

  const [actionRemarks, setActionRemarks] = useState("")
  const [actionLoading, setActionLoading] = useState(false)

  const [selectedRequirement, setSelectedRequirement] = useState<string>("")
  const [selectedFile, setSelectedFile] = useState<File | null>(null)
  const [uploadLoading, setUploadLoading] = useState(false)
  const [uploadError, setUploadError] = useState<string | null>(null)

  async function handleSubmit() {
    setActionLoading(true)
    try {
      await submitApplication(applicationId, actionRemarks.trim() || undefined)
      toast({ title: "Application submitted", description: "Your application is now under review.", variant: "success" })
      setSubmitOpen(false)
      setActionRemarks("")
      await reload()
    } catch (err) {
      toast({ title: "Unable to submit", description: err instanceof ApiError ? err.message : "Please try again.", variant: "error" })
    } finally {
      setActionLoading(false)
    }
  }

  async function handleWithdraw() {
    setActionLoading(true)
    try {
      await withdrawApplication(applicationId, actionRemarks.trim() || undefined)
      toast({ title: "Application withdrawn", description: "Your application has been withdrawn.", variant: "success" })
      setWithdrawOpen(false)
      setActionRemarks("")
      await reload()
    } catch (err) {
      toast({ title: "Unable to withdraw", description: err instanceof ApiError ? err.message : "Please try again.", variant: "error" })
    } finally {
      setActionLoading(false)
    }
  }

  async function handleUpload() {
    if (!selectedFile) {
      setUploadError("Please choose a file to upload.")
      return
    }
    setUploadLoading(true)
    setUploadError(null)
    try {
      await uploadDocument(
        applicationId,
        selectedRequirement ? Number(selectedRequirement) : null,
        selectedFile,
      )
      toast({ title: "Document uploaded", description: "Your document has been uploaded successfully.", variant: "success" })
      setUploadOpen(false)
      setSelectedFile(null)
      setSelectedRequirement("")
      await reload()
    } catch (err) {
      const message = err instanceof ApiError ? err.message : "Upload failed. Please try again."
      setUploadError(message)
      toast({ title: "Upload failed", description: message, variant: "error" })
    } finally {
      setUploadLoading(false)
    }
  }

  async function handleDeleteDocument() {
    if (!deleteTarget) return
    setActionLoading(true)
    try {
      await deleteDocument(applicationId, deleteTarget.id)
      toast({ title: "Document removed", description: "The document has been deleted.", variant: "success" })
      setDeleteTarget(null)
      await reload()
    } catch (err) {
      toast({ title: "Unable to delete", description: err instanceof ApiError ? err.message : "Please try again.", variant: "error" })
    } finally {
      setActionLoading(false)
    }
  }

  if (loading && !application) return <FullPageLoader />

  if (error && !application) {
    return (
      <div className="space-y-6">
        <Button nativeButton={false} variant="ghost" size="sm" render={<Link to="/student/applications" />}>
          <ArrowLeft aria-hidden="true" />
          Back to applications
        </Button>
        <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {error.message}
        </p>
      </div>
    )
  }

  if (!application) return null

  const cycle = application.program_cycle
  const canSubmit = application.status === "draft"
  const canWithdraw = application.status !== "withdrawn" && application.status !== "rejected" && application.status !== "completed"
  const canUpload = ACTIVE_STATUSES.includes(application.status) || application.status === "draft"
  const documents = application.documents ?? []

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <Button nativeButton={false} variant="ghost" size="sm" render={<Link to="/student/applications" />}>
          <ArrowLeft aria-hidden="true" />
          Back to applications
        </Button>
        <div className="flex items-center gap-2">
          {canSubmit ? (
            <Button onClick={() => setSubmitOpen(true)}>
              <Send aria-hidden="true" />
              Submit application
            </Button>
          ) : null}
          {canWithdraw ? (
            <Button variant="outline" onClick={() => setWithdrawOpen(true)}>
              Withdraw
            </Button>
          ) : null}
        </div>
      </div>

      <PageHeader
        title={cycle?.name ?? `Application #${application.id}`}
        description={cycle?.program?.name}
      >
        <ApplicationStatusBadge status={application.status} label={application.status_label} />
      </PageHeader>

      {error ? (
        <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {error.message}
        </p>
      ) : null}

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <Card>
            <CardHeader>
              <CardTitle>Status history</CardTitle>
              <CardDescription>Track the progress of your application</CardDescription>
            </CardHeader>
            <CardContent>
              <StatusTimeline items={application.status_history ?? []} />
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex-row items-center justify-between space-y-0">
              <div className="space-y-1">
                <CardTitle>Documents</CardTitle>
                <CardDescription>
                  {application.missing_required_documents.length > 0 ? (
                    <span className="text-amber-600">
                      {application.missing_required_documents.length} required document
                      {application.missing_required_documents.length === 1 ? "" : "s"} still missing
                    </span>
                  ) : (
                    "All required documents uploaded"
                  )}
                </CardDescription>
              </div>
              {canUpload ? (
                <Button size="sm" onClick={() => setUploadOpen(true)}>
                  <Upload aria-hidden="true" />
                  Upload
                </Button>
              ) : null}
            </CardHeader>
            <CardContent className="space-y-2">
              {documents.length === 0 ? (
                <p className="text-sm text-muted-foreground">No documents uploaded yet.</p>
              ) : (
                documents.map((document) => (
                  <div
                    key={document.id}
                    className="flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
                  >
                    <div className="min-w-0 space-y-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <Paperclip className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <p className="truncate text-sm font-medium">{document.requirement ?? document.file_name}</p>
                        <DocumentStatusBadge status={document.verification_status} label={document.verification_label} />
                      </div>
                      <p className="text-xs text-muted-foreground">
                        {document.file_name} · {formatFileSize(document.file_size)} · Uploaded {formatDateTime(document.uploaded_at)}
                      </p>
                      {document.verification_status === "rejected" && document.rejection_reason ? (
                        <p className="text-xs text-red-600">Reason: {document.rejection_reason}</p>
                      ) : null}
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                      <Button
                        nativeButton={false}
                        variant="outline"
                        size="sm"
                        render={<a href={document.download_url} target="_blank" rel="noreferrer" />}
                      >
                        <Download aria-hidden="true" />
                        Download
                      </Button>
                      {application.status === "draft" || application.status === "documents_incomplete" ? (
                        <Button
                          variant="ghost"
                          size="icon-sm"
                          onClick={() => setDeleteTarget(document)}
                          aria-label={`Delete ${document.file_name}`}
                          className="text-destructive hover:text-destructive"
                        >
                          <Trash2 aria-hidden="true" />
                        </Button>
                      ) : null}
                    </div>
                  </div>
                ))
              )}
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Program details</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              <div>
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Program</p>
                <p className="mt-0.5">{cycle?.program?.name ?? "—"}</p>
              </div>
              <div>
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Cycle</p>
                <p className="mt-0.5">{cycle?.name ?? "—"}</p>
              </div>
              {cycle?.description ? (
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Description</p>
                  <p className="mt-0.5 text-muted-foreground">{cycle.description}</p>
                </div>
              ) : null}
              <div className="flex items-center gap-1.5 text-muted-foreground">
                <CalendarRange className="size-4" aria-hidden="true" />
                {formatDate(cycle?.application_start)} – {formatDate(cycle?.application_deadline)}
              </div>
              {application.remarks ? (
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Remarks</p>
                  <p className="mt-0.5 text-muted-foreground">{application.remarks}</p>
                </div>
              ) : null}
            </CardContent>
          </Card>

          {application.assignment ? (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  Deployment
                  <DeploymentStatusBadge status={application.assignment.status} label={application.assignment.status_label} />
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3 text-sm">
                {application.assignment.position ? (
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Position</p>
                    <p className="mt-0.5">{application.assignment.position}</p>
                  </div>
                ) : null}
                {application.assignment.host_agency ? (
                  <div className="flex items-start gap-2 text-muted-foreground">
                    <User className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <div>
                      <p className="font-medium text-foreground">{application.assignment.host_agency.name}</p>
                      {application.assignment.host_agency.address ? (
                        <p>{application.assignment.host_agency.address}</p>
                      ) : null}
                    </div>
                  </div>
                ) : null}
                {application.assignment.deployment_site ? (
                  <div className="flex items-start gap-2 text-muted-foreground">
                    <MapPin className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <div>
                      <p className="font-medium text-foreground">{application.assignment.deployment_site.name}</p>
                      {application.assignment.deployment_site.address ? (
                        <p>{application.assignment.deployment_site.address}</p>
                      ) : null}
                    </div>
                  </div>
                ) : null}
                {application.assignment.start_date ? (
                  <p className="flex items-center gap-1.5 text-muted-foreground">
                    <CalendarRange className="size-4" aria-hidden="true" />
                    {formatDate(application.assignment.start_date)}
                    {application.assignment.end_date ? ` – ${formatDate(application.assignment.end_date)}` : ""}
                  </p>
                ) : null}
                {application.assignment.remarks ? (
                  <p className="text-muted-foreground">{application.assignment.remarks}</p>
                ) : null}
              </CardContent>
            </Card>
          ) : null}
        </div>
      </div>

      <Dialog open={submitOpen} onOpenChange={setSubmitOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Submit application</DialogTitle>
            <DialogDescription>
              Once submitted, your application will be reviewed by DOLE staff. You can still add documents afterward.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-2">
            <Label htmlFor="submit-remarks">Remarks (optional)</Label>
            <Textarea
              id="submit-remarks"
              value={actionRemarks}
              onChange={(event) => setActionRemarks(event.target.value)}
              placeholder="Any additional notes for the reviewer..."
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setSubmitOpen(false)} disabled={actionLoading}>
              Cancel
            </Button>
            <Button onClick={handleSubmit} disabled={actionLoading}>
              {actionLoading ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              Submit
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={withdrawOpen} onOpenChange={setWithdrawOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Withdraw application</DialogTitle>
            <DialogDescription>
              You can withdraw your application at any time before it is completed. This action can be undone by submitting again.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-2">
            <Label htmlFor="withdraw-remarks">Reason (optional)</Label>
            <Textarea
              id="withdraw-remarks"
              value={actionRemarks}
              onChange={(event) => setActionRemarks(event.target.value)}
              placeholder="Why are you withdrawing?"
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setWithdrawOpen(false)} disabled={actionLoading}>
              Cancel
            </Button>
            <Button variant="outline" onClick={handleWithdraw} disabled={actionLoading} className="text-destructive hover:text-destructive">
              {actionLoading ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              Withdraw
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={uploadOpen} onOpenChange={setUploadOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Upload document</DialogTitle>
            <DialogDescription>
              PDF, JPG, JPEG, or PNG files up to 10 MB. Allowed only while your application is in progress.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="requirement-select">Document type</Label>
              <Select value={selectedRequirement} onValueChange={(value) => setSelectedRequirement(value ?? "")}>
                <SelectTrigger id="requirement-select" className="w-full">
                  <SelectValue placeholder="Select a requirement (optional)" />
                </SelectTrigger>
                <SelectContent>
                  {(cycle?.requirements ?? []).map((requirement) => (
                    <SelectItem key={requirement.id} value={String(requirement.id)}>
                      {requirement.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="file-upload">File</Label>
              <Input
                id="file-upload"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                onChange={(event) => setSelectedFile(event.target.files?.[0] ?? null)}
              />
            </div>
            {uploadError ? (
              <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {uploadError}
              </p>
            ) : null}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setUploadOpen(false)} disabled={uploadLoading}>
              Cancel
            </Button>
            <Button onClick={handleUpload} disabled={uploadLoading || !selectedFile}>
              {uploadLoading ? <Loader2 className="animate-spin" aria-hidden="true" /> : <Upload aria-hidden="true" />}
              Upload
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <ConfirmDialog
        open={deleteTarget !== null}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
        title="Delete this document?"
        description={`"${deleteTarget?.file_name}" will be permanently removed.`}
        confirmLabel="Delete"
        destructive
        icon={Trash2}
        loading={actionLoading}
        onConfirm={handleDeleteDocument}
      />
    </div>
  )
}
