import { useCallback, useEffect, useRef, useState } from "react"
import { Link, useParams } from "react-router-dom"
import {
  AlertTriangle,
  ArrowLeft,
  BadgeCheck,
  CalendarRange,
  ClipboardCheck,
  Download,
  Loader2,
  MapPin,
  Paperclip,
  Save,
  Trash2,
  Upload,
  User,
  XCircle,
} from "lucide-react"
import {
  deleteDocument,
  fetchApplication,
  fetchApplicationHistory,
  submitApplication,
  updateApplication,
  uploadDocument,
  withdrawApplication,
} from "@/api/student"
import { useAsync } from "@/lib/useAsync"
import { ApiError } from "@/lib/api"
import { formatDate, formatDateTime, formatFileSize, formatMaxUploadSize } from "@/lib/format"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Badge } from "@/components/ui/badge"
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
import { HistoryFeed } from "@/components/HistoryTimeline"
import { ConfirmDialog } from "@/components/ConfirmDialog"
import { ApplicationStatusBadge, DeploymentStatusBadge, DocumentStatusBadge } from "@/components/StatusBadge"
import { FullPageLoader } from "@/components/FullPageLoader"
import { useToast } from "@/toast/useToast"
import type { ApplicationDocument } from "@/types/api"

const DEFAULT_ALLOWED_TYPES = ["pdf", "jpg", "jpeg", "png"]
const DEFAULT_MAX_KB = 10240

export function StudentApplicationDetailPage() {
  const { id } = useParams<{ id: string }>()
  const applicationId = Number(id)
  const { toast } = useToast()

  const fetcher = useCallback(() => fetchApplication(applicationId), [applicationId])
  const { data: application, loading, error, reload } = useAsync(fetcher)

  const [withdrawOpen, setWithdrawOpen] = useState(false)
  const [uploadOpen, setUploadOpen] = useState(false)
  const [deleteTarget, setDeleteTarget] = useState<ApplicationDocument | null>(null)
  const [confirmSubmitOpen, setConfirmSubmitOpen] = useState(false)

  const [actionRemarks, setActionRemarks] = useState("")
  const [actionLoading, setActionLoading] = useState(false)

  const [selectedRequirement, setSelectedRequirement] = useState<string>("")
  const [selectedFile, setSelectedFile] = useState<File | null>(null)
  const [uploadLoading, setUploadLoading] = useState(false)
  const [uploadProgress, setUploadProgress] = useState(0)
  const [uploadError, setUploadError] = useState<string | null>(null)

  const [remarks, setRemarks] = useState("")
  const [remarksDirty, setRemarksDirty] = useState(false)
  const [saveLoading, setSaveLoading] = useState(false)
  const [saveError, setSaveError] = useState<string | null>(null)
  const syncedApplicationId = useRef<number | null>(null)

  useEffect(() => {
    if (application && syncedApplicationId.current !== application.id) {
      syncedApplicationId.current = application.id
      setRemarks(application.remarks ?? "")
      setRemarksDirty(false)
    }
  }, [application])

  async function handleSaveDraft() {
    setSaveLoading(true)
    setSaveError(null)
    try {
      const saved = await updateApplication(applicationId, { remarks: remarks.trim() || null })
      setRemarks(saved.remarks ?? "")
      setRemarksDirty(false)
      toast({
        title: "Draft saved",
        description: "Your application information has been saved.",
        variant: "success",
      })
      await reload()
    } catch (err) {
      const apiErr = err instanceof ApiError ? err : null
      const message =
        apiErr?.errors?.["remarks"]?.[0] ??
        apiErr?.message ??
        "Unable to save your application. Please try again."
      setSaveError(message)
      toast({ title: "Unable to save", description: message, variant: "error" })
    } finally {
      setSaveLoading(false)
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

  async function handleResubmit() {
    setActionLoading(true)
    try {
      await submitApplication(applicationId)
      toast({
        title: "Application resubmitted",
        description: "Your corrected application has been resubmitted for review.",
        variant: "success",
      })
      setConfirmSubmitOpen(false)
      await reload()
    } catch (err) {
      const message =
        err instanceof ApiError ? err.message : "Unable to resubmit your application. Please try again."
      toast({ title: "Unable to resubmit", description: message, variant: "error" })
    } finally {
      setActionLoading(false)
    }
  }

  async function handleUpload() {
    if (!selectedFile) {
      setUploadError("Please choose a file to upload.")
      return
    }
    const validationError = validateSelectedFile()
    if (validationError) {
      setUploadError(validationError)
      return
    }
    setUploadLoading(true)
    setUploadProgress(0)
    setUploadError(null)
    try {
      await uploadDocument(
        applicationId,
        selectedRequirement ? Number(selectedRequirement) : null,
        selectedFile,
        setUploadProgress,
      )
      toast({ title: "Document uploaded", description: "Your document has been uploaded successfully.", variant: "success" })
      setUploadOpen(false)
      setSelectedFile(null)
      setSelectedRequirement("")
      setUploadProgress(0)
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
  const isCorrectionRequired = application.status === "returned_for_correction"
  const isDraft = application.status === "draft"
  const isRejected = application.status === "rejected"
  const isSubmitted = application.status === "submitted"
  const canReview = isDraft || isCorrectionRequired
  const canWithdraw = !["withdrawn", "rejected", "completed", "approved"].includes(application.status)
  const canUpload = ["draft", "returned_for_correction"].includes(application.status)
  const canEditInfo = isDraft || isCorrectionRequired
  const canResubmit = isCorrectionRequired
  const documents = application.documents ?? []
  const requirements = cycle?.requirements ?? []
  const requiredRequirements = requirements.filter((requirement) => requirement.is_required)
  const uploadedRequiredCount = requiredRequirements.filter((requirement) =>
    documents.some((document) => document.requirement_id === requirement.id),
  ).length
  const isDocumentEditable = isDraft || isCorrectionRequired

  function selectedRequirementConfig() {
    if (!selectedRequirement) return null
    return requirements.find((requirement) => String(requirement.id) === selectedRequirement) ?? null
  }

  function allowedTypesFor(config: { allowed_file_types?: string[] | null } | null): string[] {
    const types = config?.allowed_file_types?.length ? config.allowed_file_types : DEFAULT_ALLOWED_TYPES
    return types.map((type) => type.toLowerCase())
  }

  function maxKbFor(config: { max_file_size?: number | null } | null): number {
    return config?.max_file_size ?? DEFAULT_MAX_KB
  }

  function validateSelectedFile(): string | null {
    if (!selectedFile) return "Please choose a file to upload."
    const config = selectedRequirementConfig()
    const allowed = allowedTypesFor(config)
    const extension = selectedFile.name.split(".").pop()?.toLowerCase() ?? ""
    if (!allowed.includes(extension)) {
      return `Only ${allowed.join(", ")} files are accepted${config ? " for this requirement" : ""}.`
    }
    const maxKb = maxKbFor(config)
    if (selectedFile.size > maxKb * 1024) {
      return `This file is larger than the ${formatMaxUploadSize(maxKb)} limit.`
    }
    return null
  }

  const selectedFileValidation = selectedFile ? validateSelectedFile() : null
  const replacingRequirement = selectedRequirement
    ? documents.find((document) => document.requirement_id === Number(selectedRequirement))
    : null

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <Button nativeButton={false} variant="ghost" size="sm" render={<Link to="/student/applications" />}>
          <ArrowLeft aria-hidden="true" />
          Back to applications
        </Button>
        <div className="flex items-center gap-2">
          {canReview ? (
            <Button nativeButton={false} render={<Link to={`/student/applications/${applicationId}/review`} />}>
              <ClipboardCheck aria-hidden="true" />
              {isCorrectionRequired ? "Review & resubmit" : "Review & submit"}
            </Button>
          ) : null}
          {canResubmit ? (
            <Button onClick={() => setConfirmSubmitOpen(true)}>
              <Upload aria-hidden="true" />
              Submit corrections
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

      {isSubmitted ? (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <BadgeCheck className="size-5 text-emerald-600" aria-hidden="true" />
              Application submitted
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3 text-sm">
            <p>Your application has been submitted successfully and is awaiting review.</p>
            <div>
              <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Submitted</p>
              <p className="mt-0.5">{formatDate(application.submitted_at)}</p>
            </div>
          </CardContent>
        </Card>
      ) : null}

      {isCorrectionRequired ? (
        <Card className="border-amber-600/30 bg-amber-600/10">
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-amber-800">
              <AlertTriangle className="size-5" aria-hidden="true" />
              Correction required
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3 text-sm">
            <p className="text-amber-700">
              DOLE staff has returned your application for correction. Please review the feedback below
              and make the necessary changes before resubmitting.
            </p>
            {application.decision_reason ? (
              <div className="rounded-md bg-amber-50 p-3">
                <p className="text-xs font-medium uppercase tracking-wide text-amber-600">Staff message</p>
                <p className="mt-1 text-sm text-amber-800">{application.decision_reason}</p>
              </div>
            ) : null}
          </CardContent>
        </Card>
      ) : null}

      {isRejected ? (
        <Card className="border-destructive/30 bg-destructive/10">
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-destructive">
              <XCircle className="size-5" aria-hidden="true" />
              Application rejected
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3 text-sm">
            <p className="text-destructive/80">
              Your application has been reviewed and unfortunately cannot be approved at this time.
            </p>
            {application.decision_reason ? (
              <div className="rounded-md bg-destructive/5 p-3">
                <p className="text-xs font-medium uppercase tracking-wide text-destructive">Reason</p>
                <p className="mt-1 text-sm text-destructive/80">{application.decision_reason}</p>
              </div>
            ) : null}
          </CardContent>
        </Card>
      ) : null}

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          {canEditInfo ? (
            <Card>
              <CardHeader>
                <CardTitle>Application information</CardTitle>
                <CardDescription>
                  {isCorrectionRequired
                    ? "Make any necessary corrections to your application information."
                    : "Details specific to this application. Your draft is saved so you can return later."}
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="application-remarks">Remarks (optional)</Label>
                  <Textarea
                    id="application-remarks"
                    value={remarks}
                    onChange={(event) => {
                      setRemarks(event.target.value)
                      setRemarksDirty(true)
                    }}
                    placeholder="Any additional notes for this application, such as preferred deployment area or availability."
                    maxLength={5000}
                    aria-invalid={saveError !== null}
                    aria-describedby={saveError ? "application-remarks-error" : undefined}
                  />
                  <div className="flex items-center justify-between gap-2">
                    <p className="text-xs text-muted-foreground">
                      {remarks.length}/5000
                      {remarksDirty ? " · Unsaved changes" : " · All changes saved"}
                    </p>
                    {saveError ? (
                      <p
                        id="application-remarks-error"
                        role="alert"
                        className="text-xs font-medium text-destructive"
                      >
                        {saveError}
                      </p>
                    ) : null}
                  </div>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                  <Button onClick={handleSaveDraft} disabled={saveLoading || remarks.length > 5000}>
                    {saveLoading ? (
                      <Loader2 className="animate-spin" aria-hidden="true" />
                    ) : (
                      <Save aria-hidden="true" />
                    )}
                    {saveLoading ? "Saving..." : "Save draft"}
                  </Button>
                  {!remarksDirty && application.updated_at ? (
                    <p className="text-xs text-muted-foreground">
                      Last saved {formatDateTime(application.updated_at)}
                    </p>
                  ) : null}
                </div>
              </CardContent>
            </Card>
          ) : null}

          <Card>
            <CardHeader>
              <CardTitle>Status history</CardTitle>
              <CardDescription>Track the progress of your application</CardDescription>
            </CardHeader>
            <CardContent>
              <HistoryFeed
                fetchPage={(page) => fetchApplicationHistory(applicationId, page)}
                refreshKey={application.updated_at}
              />
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex-row items-center justify-between space-y-0">
              <div className="space-y-1">
                <CardTitle>Documents</CardTitle>
                <CardDescription>
                  {requiredRequirements.length > 0
                    ? `${uploadedRequiredCount} of ${requiredRequirements.length} required documents uploaded`
                    : "No required documents for this program"}
                </CardDescription>
              </div>
              {canUpload ? (
                <Button
                  size="sm"
                  onClick={() => {
                    setSelectedRequirement("")
                    setSelectedFile(null)
                    setUploadProgress(0)
                    setUploadError(null)
                    setUploadOpen(true)
                  }}
                >
                  <Upload aria-hidden="true" />
                  Upload
                </Button>
              ) : null}
            </CardHeader>
            <CardContent className="space-y-2">
              {requiredRequirements.length > 0 ? (
                <div className="pb-2">
                  <div
                    className="h-2 w-full overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    aria-valuenow={uploadedRequiredCount}
                    aria-valuemin={0}
                    aria-valuemax={requiredRequirements.length}
                    aria-label="Required documents progress"
                  >
                    <div
                      className="h-full rounded-full bg-emerald-500 transition-[width]"
                      style={{
                        width: `${(uploadedRequiredCount / requiredRequirements.length) * 100}%`,
                      }}
                    />
                  </div>
                </div>
              ) : null}
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
                        <p className="truncate text-sm font-medium">
                          {document.requirement?.name ?? document.file_name}
                        </p>
                        {requirements.find((requirement) => requirement.id === document.requirement_id)?.is_required ? (
                          <Badge variant="secondary">Required</Badge>
                        ) : null}
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
                      {isDocumentEditable &&
                      (document.verification_status === "pending" || document.verification_status === "rejected") ? (
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
              {!canEditInfo && application.remarks ? (
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
                {application.assignment.deployment_slot?.title ? (
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Position</p>
                    <p className="mt-0.5">{application.assignment.deployment_slot.title}</p>
                  </div>
                ) : application.assignment.position ? (
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
                {application.assignment.assigned_at ? (
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Assignment Date</p>
                    <p className="mt-0.5">{formatDateTime(application.assignment.assigned_at)}</p>
                  </div>
                ) : application.assignment.start_date ? (
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
              Attach a requirement document or another supporting file. Uploading again for the same type replaces the previous file.
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
                  {requirements.map((requirement) => (
                    <SelectItem key={requirement.id} value={String(requirement.id)}>
                      {requirement.name}
                      {requirement.is_required ? " · Required" : ""}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {selectedRequirement ? (
                <p className="text-xs text-muted-foreground">
                  {allowedTypesFor(selectedRequirementConfig()).join(", ").toUpperCase()} files up to{" "}
                  {formatMaxUploadSize(maxKbFor(selectedRequirementConfig()))}
                </p>
              ) : (
                <p className="text-xs text-muted-foreground">
                  Leave blank to attach an unlisted document (PDF, JPG, JPEG, or PNG up to 10 MB).
                </p>
              )}
              {replacingRequirement ? (
                <p className="text-xs font-medium text-amber-600">
                  A document for this type already exists and will be replaced.
                </p>
              ) : null}
            </div>
            <div className="space-y-2">
              <Label htmlFor="file-upload">File</Label>
              <Input
                id="file-upload"
                type="file"
                accept={allowedTypesFor(selectedRequirementConfig()).map((type) => `.${type}`).join(",")}
                onChange={(event) => setSelectedFile(event.target.files?.[0] ?? null)}
              />
              {selectedFile ? (
                <div className="rounded-md border bg-muted/40 px-3 py-2 text-sm">
                  <p className="truncate font-medium">{selectedFile.name}</p>
                  <p className={selectedFileValidation ? "text-xs text-destructive" : "text-xs text-muted-foreground"}>
                    {selectedFileValidation ?? `${formatFileSize(selectedFile.size)} · Ready to upload`}
                  </p>
                </div>
              ) : null}
            </div>
            {uploadLoading ? (
              <div className="space-y-1">
                <div
                  className="h-2 w-full overflow-hidden rounded-full bg-muted"
                  role="progressbar"
                  aria-valuenow={uploadProgress}
                  aria-valuemin={0}
                  aria-valuemax={100}
                  aria-label="Upload progress"
                >
                  <div className="h-full bg-primary transition-[width]" style={{ width: `${uploadProgress}%` }} />
                </div>
                <p className="text-xs text-muted-foreground">{uploadProgress}% uploaded</p>
              </div>
            ) : null}
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
            <Button onClick={handleUpload} disabled={uploadLoading || !selectedFile || selectedFileValidation !== null}>
              {uploadLoading ? <Loader2 className="animate-spin" aria-hidden="true" /> : <Upload aria-hidden="true" />}
              {uploadLoading ? "Uploading..." : "Upload"}
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

      <Dialog open={confirmSubmitOpen} onOpenChange={(open) => !actionLoading && setConfirmSubmitOpen(open)}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Submit corrections?</DialogTitle>
            <DialogDescription>
              Your corrected application will be resubmitted for staff review. You will not be able to
              edit your application or modify its documents unless staff returns it for correction again.
              Are you sure you want to resubmit?
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setConfirmSubmitOpen(false)} disabled={actionLoading}>
              Cancel
            </Button>
            <Button onClick={handleResubmit} disabled={actionLoading}>
              {actionLoading ? <Loader2 className="animate-spin" aria-hidden="true" /> : <Upload aria-hidden="true" />}
              {actionLoading ? "Submitting..." : "Submit corrections"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
