import { useCallback, useState } from "react"
import { Link, useParams } from "react-router-dom"
import {
  ArrowLeft,
  Building2,
  CalendarRange,
  CheckCircle2,
  Download,
  Loader2,
  Mail,
  MapPin,
  Paperclip,
  Send,
  User,
  XCircle,
} from "lucide-react"
import {
  createDeployment,
  fetchDeploymentSites,
  fetchHostAgencies,
  fetchStaffApplication,
  reviewApplication,
  updateDeploymentStatus,
  verifyDocument,
} from "@/api/staff"
import type { ReviewAction } from "@/api/staff"
import { useAsync } from "@/lib/useAsync"
import { ApiError } from "@/lib/api"
import { formatDateTime, formatFileSize } from "@/lib/format"
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
import { ApplicationStatusBadge, DeploymentStatusBadge, DocumentStatusBadge } from "@/components/StatusBadge"
import { FullPageLoader } from "@/components/FullPageLoader"
import { useToast } from "@/toast/useToast"
import type { ApplicationDocument } from "@/types/api"

interface ActionModal {
  type: ReviewAction | "schedule_deployment"
  title: string
  description: string
  requiresRemarks: boolean
}

const STATUS_ACTIONS: Record<string, ActionModal[]> = {
  submitted: [
    {
      type: "start_review",
      title: "Start review",
      description: "Begin reviewing this application. You can approve it or request more documents.",
      requiresRemarks: false,
    },
    {
      type: "request_documents",
      title: "Request more documents",
      description: "Return the application to the student to upload additional documents.",
      requiresRemarks: true,
    },
  ],
  under_review: [
    {
      type: "approve",
      title: "Approve application",
      description: "Approve this application. You can schedule deployment afterward.",
      requiresRemarks: false,
    },
    {
      type: "request_documents",
      title: "Request more documents",
      description: "Return the application to the student to upload additional documents.",
      requiresRemarks: true,
    },
  ],
  documents_incomplete: [
    {
      type: "approve",
      title: "Approve application",
      description: "Approve this application despite missing documents.",
      requiresRemarks: false,
    },
  ],
  documents_verified: [
    {
      type: "approve",
      title: "Approve application",
      description: "Approve this application now that documents are verified.",
      requiresRemarks: false,
    },
  ],
  approved: [],
  for_deployment: [
    {
      type: "deploy",
      title: "Mark as deployed",
      description: "Mark this applicant as deployed to their host agency.",
      requiresRemarks: false,
    },
  ],
  deployed: [
    {
      type: "complete",
      title: "Mark as completed",
      description: "Complete this application once the program term ends.",
      requiresRemarks: false,
    },
  ],
}

export function StaffApplicationDetailPage() {
  const { id } = useParams<{ id: string }>()
  const applicationId = Number(id)
  const { toast } = useToast()

  const fetcher = useCallback(() => fetchStaffApplication(applicationId), [applicationId])
  const { data: application, loading, error, reload } = useAsync(fetcher)

  const { data: agencies } = useAsync(fetchHostAgencies)
  const { data: sites } = useAsync(fetchDeploymentSites)

  const [activeAction, setActiveAction] = useState<ActionModal | null>(null)
  const [remarks, setRemarks] = useState("")
  const [actionLoading, setActionLoading] = useState(false)

  const [scheduleOpen, setScheduleOpen] = useState(false)
  const [scheduleForm, setScheduleForm] = useState({
    host_agency_id: "",
    deployment_site_id: "",
    position: "",
    start_date: "",
    end_date: "",
    remarks: "",
  })
  const [scheduleError, setScheduleError] = useState<string | null>(null)
  const [scheduleLoading, setScheduleLoading] = useState(false)

  const [docTarget, setDocTarget] = useState<ApplicationDocument | null>(null)
  const [docStatus, setDocStatus] = useState<"verified" | "rejected">("verified")
  const [docReason, setDocReason] = useState("")
  const [docError, setDocError] = useState<string | null>(null)
  const [docLoading, setDocLoading] = useState(false)

  if (loading && !application) return <FullPageLoader />

  if (error && !application) {
    return (
      <div className="space-y-6">
        <Button nativeButton={false} variant="ghost" size="sm" render={<Link to="/staff/review" />}>
          <ArrowLeft aria-hidden="true" />
          Back to review queue
        </Button>
        <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {error.message}
        </p>
      </div>
    )
  }

  if (!application) return null

  const actions = STATUS_ACTIONS[application.status] ?? []
  const documents = application.documents ?? []
  const assignment = application.assignment

  function openAction(action: ActionModal) {
    setActiveAction(action)
    setRemarks("")
  }

  async function handleAction() {
    if (!activeAction) return
    if (activeAction.requiresRemarks && !remarks.trim()) {
      toast({ title: "Reason required", description: "Please provide a reason.", variant: "error" })
      return
    }
    setActionLoading(true)
    try {
      await reviewApplication(applicationId, activeAction.type, remarks.trim() || undefined)
      toast({
        title: "Application updated",
        description: "The application status has been updated.",
        variant: "success",
      })
      setActiveAction(null)
      await reload()
    } catch (err) {
      toast({
        title: "Unable to update",
        description: err instanceof ApiError ? err.message : "Please try again.",
        variant: "error",
      })
    } finally {
      setActionLoading(false)
    }
  }

  async function handleDeployViaAssignment() {
    if (!assignment) return
    setActionLoading(true)
    try {
      await updateDeploymentStatus(assignment.id, "active")
      toast({ title: "Applicant deployed", description: "The assignment is now active.", variant: "success" })
      setActiveAction(null)
      await reload()
    } catch (err) {
      toast({
        title: "Unable to deploy",
        description: err instanceof ApiError ? err.message : "Please try again.",
        variant: "error",
      })
    } finally {
      setActionLoading(false)
    }
  }

  async function handleCompleteViaAssignment() {
    if (!assignment) return
    setActionLoading(true)
    try {
      await updateDeploymentStatus(assignment.id, "completed")
      toast({ title: "Application completed", description: "This application has been completed.", variant: "success" })
      setActiveAction(null)
      await reload()
    } catch (err) {
      toast({
        title: "Unable to complete",
        description: err instanceof ApiError ? err.message : "Please try again.",
        variant: "error",
      })
    } finally {
      setActionLoading(false)
    }
  }

  async function handleSchedule() {
    if (!scheduleForm.host_agency_id || !scheduleForm.start_date) {
      setScheduleError("Host agency and start date are required.")
      return
    }
    setScheduleLoading(true)
    setScheduleError(null)
    try {
      await createDeployment({
        application_id: applicationId,
        host_agency_id: Number(scheduleForm.host_agency_id),
        deployment_site_id: scheduleForm.deployment_site_id ? Number(scheduleForm.deployment_site_id) : null,
        position: scheduleForm.position || undefined,
        start_date: scheduleForm.start_date,
        end_date: scheduleForm.end_date || null,
        remarks: scheduleForm.remarks || undefined,
      })
      toast({ title: "Deployment scheduled", description: "The applicant is now marked for deployment.", variant: "success" })
      setScheduleOpen(false)
      await reload()
    } catch (err) {
      const message = err instanceof ApiError ? err.message : "Unable to schedule deployment."
      setScheduleError(message)
      toast({ title: "Unable to schedule", description: message, variant: "error" })
    } finally {
      setScheduleLoading(false)
    }
  }

  async function handleVerifyDocument() {
    if (!docTarget) return
    if (docStatus === "rejected" && !docReason.trim()) {
      setDocError("A rejection reason is required.")
      return
    }
    setDocLoading(true)
    setDocError(null)
    try {
      await verifyDocument(applicationId, docTarget.id, docStatus, docReason.trim() || undefined)
      toast({
        title: docStatus === "verified" ? "Document verified" : "Document rejected",
        description: docStatus === "verified" ? "The document has been verified." : "The document was rejected.",
        variant: docStatus === "verified" ? "success" : "error",
      })
      setDocTarget(null)
      setDocReason("")
      await reload()
    } catch (err) {
      const message = err instanceof ApiError ? err.message : "Unable to verify document."
      setDocError(message)
      toast({ title: "Unable to verify", description: message, variant: "error" })
    } finally {
      setDocLoading(false)
    }
  }

  return (
    <div className="space-y-6">
      <Button nativeButton={false} variant="ghost" size="sm" render={<Link to="/staff/review" />}>
        <ArrowLeft aria-hidden="true" />
        Back to review queue
      </Button>

      <PageHeader
        title={application.applicant?.name ?? `Application #${application.id}`}
        description={application.applicant?.email}
      >
        <ApplicationStatusBadge status={application.status} label={application.status_label} />
      </PageHeader>

      {error ? (
        <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {error.message}
        </p>
      ) : null}

      {actions.length > 0 || application.status === "approved" ? (
        <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/30 p-4">
          <p className="mr-2 text-sm font-medium">Actions:</p>
          {application.status === "approved" ? (
            <Button onClick={() => setScheduleOpen(true)}>
              <Building2 aria-hidden="true" />
              Schedule deployment
            </Button>
          ) : null}
          {actions.map((action) => (
            <Button
              key={action.type}
              variant="outline"
              onClick={() => openAction(action)}
            >
              {action.title}
            </Button>
          ))}
        </div>
      ) : null}

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <Card>
            <CardHeader>
              <CardTitle>Documents</CardTitle>
              <CardDescription>
                {application.missing_required_documents.length > 0 ? (
                  <span className="text-amber-600">
                    {application.missing_required_documents.join(", ")} still pending
                  </span>
                ) : (
                  "All required documents submitted"
                )}
              </CardDescription>
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
                        {document.file_name} · {formatFileSize(document.file_size)}
                      </p>
                      {document.verification_status === "rejected" && document.rejection_reason ? (
                        <p className="text-xs text-red-600">Rejected: {document.rejection_reason}</p>
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
                      {document.verification_status === "pending" ? (
                        <>
                          <Button
                            size="sm"
                            onClick={() => {
                              setDocStatus("verified")
                              setDocReason("")
                              setDocError(null)
                              setDocTarget(document)
                            }}
                          >
                            <CheckCircle2 aria-hidden="true" />
                            Verify
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            className="text-destructive hover:text-destructive"
                            onClick={() => {
                              setDocStatus("rejected")
                              setDocReason("")
                              setDocError(null)
                              setDocTarget(document)
                            }}
                          >
                            <XCircle aria-hidden="true" />
                            Reject
                          </Button>
                        </>
                      ) : null}
                    </div>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Status history</CardTitle>
            </CardHeader>
            <CardContent>
              <StatusTimeline items={application.status_history ?? []} />
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Applicant</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              <div className="flex items-start gap-2 text-muted-foreground">
                <User className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <p className="font-medium text-foreground">{application.applicant?.name ?? "—"}</p>
              </div>
              <div className="flex items-center gap-2 text-muted-foreground">
                <Mail className="size-4 shrink-0" aria-hidden="true" />
                <p>{application.applicant?.email ?? "—"}</p>
              </div>
              <div className="flex items-center gap-2 text-muted-foreground">
                <CalendarRange className="size-4 shrink-0" aria-hidden="true" />
                <p>Submitted {formatDateTime(application.submitted_at ?? application.created_at)}</p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Program</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              <div>
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Program</p>
                <p className="mt-0.5">{application.program_cycle?.program?.name ?? "—"}</p>
              </div>
              <div>
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Cycle</p>
                <p className="mt-0.5">{application.program_cycle?.name ?? "—"}</p>
              </div>
              <div>
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Slots remaining</p>
                <p className="mt-0.5">
                  {application.program_cycle?.slots_remaining} of {application.program_cycle?.total_slots}
                </p>
              </div>
            </CardContent>
          </Card>

          {assignment ? (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  Deployment
                  <DeploymentStatusBadge status={assignment.status} label={assignment.status_label} />
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3 text-sm">
                {assignment.host_agency ? (
                  <div className="flex items-start gap-2 text-muted-foreground">
                    <Building2 className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <div>
                      <p className="font-medium text-foreground">{assignment.host_agency.name}</p>
                      {assignment.host_agency.address ? <p>{assignment.host_agency.address}</p> : null}
                    </div>
                  </div>
                ) : null}
                {assignment.deployment_site ? (
                  <div className="flex items-start gap-2 text-muted-foreground">
                    <MapPin className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <p>{assignment.deployment_site.name}</p>
                  </div>
                ) : null}
                {assignment.start_date ? (
                  <p className="flex items-center gap-1.5 text-muted-foreground">
                    <CalendarRange className="size-4" aria-hidden="true" />
                    {assignment.start_date}
                    {assignment.end_date ? ` – ${assignment.end_date}` : ""}
                  </p>
                ) : null}
                {assignment.remarks ? <p className="text-muted-foreground">{assignment.remarks}</p> : null}
              </CardContent>
            </Card>
          ) : null}
        </div>
      </div>

      <Dialog open={activeAction !== null} onOpenChange={(open) => !open && setActiveAction(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{activeAction?.title}</DialogTitle>
            <DialogDescription>{activeAction?.description}</DialogDescription>
          </DialogHeader>
          <div className="space-y-2">
            <Label htmlFor="action-remarks">
              {activeAction?.requiresRemarks ? "Reason (required)" : "Remarks (optional)"}
            </Label>
            <Textarea
              id="action-remarks"
              value={remarks}
              onChange={(event) => setRemarks(event.target.value)}
              placeholder={activeAction?.requiresRemarks ? "Provide a clear reason..." : "Any notes for the record..."}
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setActiveAction(null)} disabled={actionLoading}>
              Cancel
            </Button>
            <Button
              onClick={() => {
                if (activeAction?.type === "deploy" && assignment) {
                  void handleDeployViaAssignment()
                } else if (activeAction?.type === "complete" && assignment) {
                  void handleCompleteViaAssignment()
                } else {
                  void handleAction()
                }
              }}
              disabled={actionLoading}
            >
              {actionLoading ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              Confirm
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={scheduleOpen} onOpenChange={setScheduleOpen}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Schedule deployment</DialogTitle>
            <DialogDescription>
              Assign this applicant to a host agency and site.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            {scheduleError ? (
              <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {scheduleError}
              </p>
            ) : null}

            <div className="space-y-2">
              <Label htmlFor="host-agency">Host agency</Label>
              <Select
                value={scheduleForm.host_agency_id}
                onValueChange={(value) => setScheduleForm({ ...scheduleForm, host_agency_id: value ?? "" })}
              >
                <SelectTrigger id="host-agency" className="w-full">
                  <SelectValue placeholder="Select host agency" />
                </SelectTrigger>
                <SelectContent>
                  {(agencies ?? []).map((agency) => (
                    <SelectItem key={agency.id} value={String(agency.id)}>
                      {agency.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="deployment-site">Deployment site (optional)</Label>
              <Select
                value={scheduleForm.deployment_site_id}
                onValueChange={(value) => setScheduleForm({ ...scheduleForm, deployment_site_id: value ?? "" })}
              >
                <SelectTrigger id="deployment-site" className="w-full">
                  <SelectValue placeholder="Select site" />
                </SelectTrigger>
                <SelectContent>
                  {(sites ?? []).map((site) => (
                    <SelectItem key={site.id} value={String(site.id)}>
                      {site.name}
                      {site.city ? ` · ${site.city}` : ""}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="position">Position (optional)</Label>
              <Input
                id="position"
                value={scheduleForm.position}
                onChange={(event) => setScheduleForm({ ...scheduleForm, position: event.target.value })}
                placeholder="e.g. Administrative Intern"
              />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="start-date">Start date</Label>
                <Input
                  id="start-date"
                  type="date"
                  value={scheduleForm.start_date}
                  onChange={(event) => setScheduleForm({ ...scheduleForm, start_date: event.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="end-date">End date (optional)</Label>
                <Input
                  id="end-date"
                  type="date"
                  value={scheduleForm.end_date}
                  onChange={(event) => setScheduleForm({ ...scheduleForm, end_date: event.target.value })}
                />
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="schedule-remarks">Remarks (optional)</Label>
              <Textarea
                id="schedule-remarks"
                value={scheduleForm.remarks}
                onChange={(event) => setScheduleForm({ ...scheduleForm, remarks: event.target.value })}
                placeholder="Any notes about this deployment..."
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setScheduleOpen(false)} disabled={scheduleLoading}>
              Cancel
            </Button>
            <Button onClick={handleSchedule} disabled={scheduleLoading}>
              {scheduleLoading ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              <Send aria-hidden="true" />
              Schedule
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={docTarget !== null} onOpenChange={(open) => !open && setDocTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{docStatus === "verified" ? "Verify document" : "Reject document"}</DialogTitle>
            <DialogDescription>{docTarget?.file_name}</DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            {docStatus === "verified" ? (
              <p className="text-sm text-muted-foreground">
                Confirm that this document is authentic and acceptable for the application.
              </p>
            ) : (
              <div className="space-y-2">
                <Label htmlFor="doc-reason">Rejection reason (required)</Label>
                <Textarea
                  id="doc-reason"
                  value={docReason}
                  onChange={(event) => setDocReason(event.target.value)}
                  placeholder="e.g. Blurred scan, expired certificate..."
                />
              </div>
            )}
            {docError ? (
              <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {docError}
              </p>
            ) : null}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDocTarget(null)} disabled={docLoading}>
              Cancel
            </Button>
            <Button
              variant={docStatus === "rejected" ? "outline" : "default"}
              className={docStatus === "rejected" ? "text-destructive hover:text-destructive" : undefined}
              onClick={handleVerifyDocument}
              disabled={docLoading}
            >
              {docLoading ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              {docStatus === "verified" ? "Verify" : "Reject"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
