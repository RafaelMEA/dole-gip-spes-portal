import { useCallback, useState } from "react"
import { Link, useParams } from "react-router-dom"
import {
  AlertTriangle,
  ArrowLeft,
  ArrowRight,
  Building2,
  CalendarRange,
  CheckCircle2,
  FileText,
  GraduationCap,
  Loader2,
  Mail,
  MapPin,
  Send,
  User,
  XCircle,
} from "lucide-react"
import {
  assignDeployment,
  cancelDeployment,
  fetchDeploymentOptions,
  fetchStaffApplication,
  reviewApplication,
  updateDeploymentStatus,
} from "@/api/staff"
import type { ReviewAction } from "@/api/staff"
import { useAsync } from "@/lib/useAsync"
import { ApiError } from "@/lib/api"
import { formatDateTime } from "@/lib/format"
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
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { PageHeader } from "@/components/PageHeader"
import { StatusTimeline } from "@/components/StatusTimeline"
import { ApplicationStatusBadge, DeploymentStatusBadge } from "@/components/StatusBadge"
import { DocumentReview } from "@/components/staff/DocumentReview"
import { FullPageLoader } from "@/components/FullPageLoader"
import { useToast } from "@/toast/useToast"
import type {
  DeploymentAgencyOption,
  DeploymentOptions,
  DeploymentSiteOption,
  DeploymentSlotOption,
} from "@/types/api"

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
    {
      type: "return_for_correction",
      title: "Return for correction",
      description: "Return the application to the student with specific correction instructions.",
      requiresRemarks: true,
    },
    {
      type: "reject",
      title: "Reject application",
      description: "Permanently reject this application. This action cannot be undone.",
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
    {
      type: "return_for_correction",
      title: "Return for correction",
      description: "Return the application to the student with specific correction instructions.",
      requiresRemarks: true,
    },
    {
      type: "reject",
      title: "Reject application",
      description: "Permanently reject this application. This action cannot be undone.",
      requiresRemarks: true,
    },
  ],
  returned_for_correction: [
    {
      type: "approve",
      title: "Approve application",
      description: "Approve this application despite the correction request.",
      requiresRemarks: false,
    },
    {
      type: "reject",
      title: "Reject application",
      description: "Permanently reject this application. This action cannot be undone.",
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

  const [activeAction, setActiveAction] = useState<ActionModal | null>(null)
  const [remarks, setRemarks] = useState("")
  const [actionLoading, setActionLoading] = useState(false)

  const [assignOpen, setAssignOpen] = useState(false)
  const [assignStep, setAssignStep] = useState<"select" | "confirm">("select")
  const [deployOptions, setDeployOptions] = useState<DeploymentOptions | null>(null)
  const [optionsLoading, setOptionsLoading] = useState(false)
  const [optionsError, setOptionsError] = useState<string | null>(null)

  const [selectedAgency, setSelectedAgency] = useState<DeploymentAgencyOption | null>(null)
  const [selectedSite, setSelectedSite] = useState<DeploymentSiteOption | null>(null)
  const [selectedSlot, setSelectedSlot] = useState<DeploymentSlotOption | null>(null)

  const [assignLoading, setAssignLoading] = useState(false)
  const [assignError, setAssignError] = useState<string | null>(null)

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
  const requiredCount =
    application.program_cycle?.requirements?.filter((requirement) => requirement.is_required)
      .length ?? 0
  const assignment = application.assignment
  const isTerminal = ["approved", "rejected", "withdrawn", "completed"].includes(application.status)

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

  function openAssignDialog() {
    setAssignOpen(true)
    setAssignStep("select")
    setDeployOptions(null)
    setOptionsError(null)
    setSelectedAgency(null)
    setSelectedSite(null)
    setSelectedSlot(null)
    setAssignError(null)
    setOptionsLoading(true)

    fetchDeploymentOptions(applicationId)
      .then((options) => {
        setDeployOptions(options)
        setOptionsLoading(false)
      })
      .catch((err) => {
        const message = err instanceof ApiError ? err.message : "Unable to load deployment options."
        setOptionsError(message)
        setOptionsLoading(false)
      })
  }

  function handleAgencyChange(agencyId: string) {
    const agency = deployOptions?.host_agencies.find((a) => a.id === Number(agencyId)) ?? null
    setSelectedAgency(agency)
    setSelectedSite(null)
    setSelectedSlot(null)
  }

  function handleSiteChange(siteId: string) {
    const site = selectedAgency?.deployment_sites.find((s) => s.id === Number(siteId)) ?? null
    setSelectedSite(site)
    setSelectedSlot(null)
  }

  function handleSlotChange(slotId: string) {
    const slot = selectedSite?.slots.find((s) => s.id === Number(slotId)) ?? null
    setSelectedSlot(slot)
  }

  function handleContinueToConfirm() {
    if (!selectedSlot) return
    setAssignStep("confirm")
  }

  async function handleConfirmAssign() {
    if (!selectedSlot) return
    setAssignLoading(true)
    setAssignError(null)
    try {
      await assignDeployment(applicationId, selectedSlot.id)
      toast({
        title: "Student assigned",
        description: "The student has been assigned to the deployment slot.",
        variant: "success",
      })
      setAssignOpen(false)
      await reload()
    } catch (err) {
      const message = err instanceof ApiError ? err.message : "Unable to assign student."
      setAssignError(message)
      toast({ title: "Unable to assign", description: message, variant: "error" })
    } finally {
      setAssignLoading(false)
    }
  }

  function handleCancelAssign() {
    if (assignStep === "confirm") {
      setAssignStep("select")
      setAssignError(null)
    } else {
      setAssignOpen(false)
    }
  }

  const hasActiveAssignment = assignment && ["scheduled", "active"].includes(assignment.status)

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

      {application.status === "returned_for_correction" && application.decision_reason ? (
        <div className="rounded-lg border border-amber-600/30 bg-amber-600/10 px-4 py-3">
          <div className="flex items-start gap-2">
            <AlertTriangle className="mt-0.5 size-5 shrink-0 text-amber-600" aria-hidden="true" />
            <div>
              <p className="text-sm font-medium text-amber-800">Returned for correction</p>
              <p className="mt-1 text-sm text-amber-700">{application.decision_reason}</p>
            </div>
          </div>
        </div>
      ) : null}

      {application.status === "rejected" && application.decision_reason ? (
        <div className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3">
          <div className="flex items-start gap-2">
            <XCircle className="mt-0.5 size-5 shrink-0 text-destructive" aria-hidden="true" />
            <div>
              <p className="text-sm font-medium text-destructive">Application rejected</p>
              <p className="mt-1 text-sm text-destructive/80">{application.decision_reason}</p>
            </div>
          </div>
        </div>
      ) : null}

      {!isTerminal && actions.length > 0 ? (
        <Card>
          <CardHeader>
            <CardTitle>Application Decision</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="rounded-lg border bg-muted/30 p-3">
              <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-4">
                <div>
                  <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    Required Docs
                  </dt>
                  <dd className="mt-0.5 font-semibold">{requiredCount}</dd>
                </div>
                <div>
                  <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    Verified
                  </dt>
                  <dd className="mt-0.5 font-semibold text-emerald-600">
                    {documents.filter((d) => d.verification_status === "verified").length}
                  </dd>
                </div>
                <div>
                  <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    Rejected
                  </dt>
                  <dd className="mt-0.5 font-semibold text-red-600">
                    {documents.filter((d) => d.verification_status === "rejected").length}
                  </dd>
                </div>
                <div>
                  <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    Pending
                  </dt>
                  <dd className="mt-0.5 font-semibold text-amber-600">
                    {documents.filter((d) => d.verification_status === "pending").length}
                  </dd>
                </div>
              </dl>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              {application.status === "approved" && !hasActiveAssignment ? (
                <Button onClick={openAssignDialog}>
                  <Building2 aria-hidden="true" />
                  Assign deployment
                </Button>
              ) : null}
              {actions.map((action) => (
                <Button
                  key={action.type}
                  variant={action.type === "reject" ? "outline" : "default"}
                  className={action.type === "reject" ? "text-destructive hover:text-destructive" : ""}
                  onClick={() => openAction(action)}
                >
                  {action.type === "approve" && <CheckCircle2 className="size-4" aria-hidden="true" />}
                  {action.type === "reject" && <XCircle className="size-4" aria-hidden="true" />}
                  {action.type === "return_for_correction" && <AlertTriangle className="size-4" aria-hidden="true" />}
                  {action.title}
                </Button>
              ))}
            </div>
          </CardContent>
        </Card>
      ) : application.status === "approved" && !hasActiveAssignment ? (
        <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/30 p-4">
          <p className="mr-2 text-sm font-medium">Actions:</p>
          <Button onClick={openAssignDialog}>
            <Building2 aria-hidden="true" />
            Assign deployment
          </Button>
        </div>
      ) : null}

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <DocumentReview
            applicationId={applicationId}
            documents={documents}
            requiredCount={requiredCount}
            onChanged={() => void reload()}
          />

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
                <GraduationCap className="size-4 shrink-0" aria-hidden="true" />
                <div>
                  <p>{application.applicant?.student_detail?.school_name ?? "—"}</p>
                  {application.applicant?.student_detail?.course ||
                  application.applicant?.student_detail?.year_level ? (
                    <p className="text-xs">
                      {[application.applicant.student_detail?.course, application.applicant.student_detail?.year_level]
                        .filter(Boolean)
                        .map((part) => (typeof part === "number" ? `Year ${part}` : part))
                        .join(" · ")}
                    </p>
                  ) : null}
                </div>
              </div>
              <div className="flex items-center gap-2 text-muted-foreground">
                <CalendarRange className="size-4 shrink-0" aria-hidden="true" />
                <p>Submitted {formatDateTime(application.submitted_at ?? application.created_at)}</p>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                Application
                <ApplicationStatusBadge status={application.status} label={application.status_label} />
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              <div className="flex items-start gap-2 text-muted-foreground">
                <FileText className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Status</p>
                  <p className="mt-0.5 capitalize text-foreground">{application.status_label}</p>
                </div>
              </div>
              <div>
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Submitted</p>
                <p className="mt-0.5">{formatDateTime(application.submitted_at ?? application.created_at)}</p>
              </div>
              <div>
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Created</p>
                <p className="mt-0.5">{formatDateTime(application.created_at)}</p>
              </div>
              <div>
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Last updated</p>
                <p className="mt-0.5">{formatDateTime(application.updated_at)}</p>
              </div>
              {application.decision_reason ? (
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Decision reason</p>
                  <p className="mt-0.5 text-muted-foreground">{application.decision_reason}</p>
                </div>
              ) : null}
              {application.remarks ? (
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Remarks</p>
                  <p className="mt-0.5 text-muted-foreground">{application.remarks}</p>
                </div>
              ) : null}
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
                {assignment.deployment_slot ? (
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Position</p>
                    <p className="mt-0.5">{assignment.deployment_slot.title}</p>
                  </div>
                ) : assignment.position ? (
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Position</p>
                    <p className="mt-0.5">{assignment.position}</p>
                  </div>
                ) : null}
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
                {assignment.assigned_at ? (
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Assigned</p>
                    <p className="mt-0.5">{formatDateTime(assignment.assigned_at)}</p>
                  </div>
                ) : null}
                {assignment.assigned_by_name ? (
                  <div>
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Assigned by</p>
                    <p className="mt-0.5">{assignment.assigned_by_name}</p>
                  </div>
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
              className={activeAction?.type === "reject" ? "text-destructive hover:text-destructive" : ""}
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

      <Dialog open={assignOpen} onOpenChange={(open) => !assignLoading && setAssignOpen(open)}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
          {assignStep === "select" ? (
            <>
              <DialogHeader>
                <DialogTitle>Assign Deployment</DialogTitle>
                <DialogDescription>
                  Select a deployment slot for this student.
                </DialogDescription>
              </DialogHeader>
              <div className="space-y-4">
                {optionsLoading ? (
                  <div className="flex items-center justify-center py-8">
                    <Loader2 className="size-6 animate-spin text-muted-foreground" aria-hidden="true" />
                  </div>
                ) : optionsError ? (
                  <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {optionsError}
                  </p>
                ) : deployOptions && deployOptions.host_agencies.length === 0 ? (
                  <p className="py-4 text-center text-sm text-muted-foreground">
                    No active deployment slots available for this program cycle.
                  </p>
                ) : (
                  <>
                    <div className="rounded-lg border bg-muted/30 p-3 text-sm">
                      <dl className="grid grid-cols-2 gap-x-4 gap-y-1">
                        <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Student</dt>
                        <dd>{application.applicant?.name ?? "—"}</dd>
                        <dt className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Program</dt>
                        <dd>{deployOptions?.program_cycle?.name ?? "—"}</dd>
                      </dl>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="assign-agency">Host Agency</Label>
                      <Select value={selectedAgency?.id ? String(selectedAgency.id) : ""} onValueChange={handleAgencyChange}>
                        <SelectTrigger id="assign-agency" className="w-full">
                          <SelectValue placeholder="Select Host Agency" />
                        </SelectTrigger>
                        <SelectContent>
                          {(deployOptions?.host_agencies ?? []).map((agency) => (
                            <SelectItem key={agency.id} value={String(agency.id)}>
                              {agency.name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>

                    {selectedAgency ? (
                      <div className="space-y-2">
                        <Label htmlFor="assign-site">Deployment Site</Label>
                        <Select value={selectedSite?.id ? String(selectedSite.id) : ""} onValueChange={handleSiteChange}>
                          <SelectTrigger id="assign-site" className="w-full">
                            <SelectValue placeholder="Select Site" />
                          </SelectTrigger>
                          <SelectContent>
                            {selectedAgency.deployment_sites.map((site) => (
                              <SelectItem key={site.id} value={String(site.id)}>
                                {site.name}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                    ) : null}

                    {selectedSite ? (
                      <div className="space-y-2">
                        <Label htmlFor="assign-slot">Deployment Slot</Label>
                        <Select value={selectedSlot?.id ? String(selectedSlot.id) : ""} onValueChange={handleSlotChange}>
                          <SelectTrigger id="assign-slot" className="w-full">
                            <SelectValue placeholder="Select Slot" />
                          </SelectTrigger>
                          <SelectContent>
                            {selectedSite.slots.map((slot) => (
                              <SelectItem
                                key={slot.id}
                                value={String(slot.id)}
                                disabled={slot.available_count <= 0}
                              >
                                {slot.title} ({slot.available_count} of {slot.capacity} available)
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                    ) : null}

                    {selectedSlot ? (
                      <div className="rounded-lg border bg-muted/30 p-3">
                        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Capacity</p>
                        <div className="mt-1 flex gap-4 text-sm">
                          <span>{selectedSlot.capacity} total</span>
                          <span>{selectedSlot.assigned_count} assigned</span>
                          <span className={selectedSlot.available_count > 0 ? "font-medium text-emerald-600" : "font-medium text-red-600"}>
                            {selectedSlot.available_count} available
                          </span>
                        </div>
                      </div>
                    ) : null}
                  </>
                )}
              </div>
              <DialogFooter>
                <Button variant="outline" onClick={handleCancelAssign} disabled={assignLoading}>
                  Cancel
                </Button>
                <Button onClick={handleContinueToConfirm} disabled={!selectedSlot || assignLoading}>
                  Continue
                  <ArrowRight aria-hidden="true" />
                </Button>
              </DialogFooter>
            </>
          ) : (
            <>
              <DialogHeader>
                <DialogTitle>Confirm Deployment Assignment</DialogTitle>
                <DialogDescription>
                  Review the assignment details before confirming.
                </DialogDescription>
              </DialogHeader>
              <div className="space-y-3 text-sm">
                <div className="rounded-lg border bg-muted/30 p-3">
                  <dl className="space-y-2">
                    <div className="flex justify-between">
                      <dt className="text-muted-foreground">Student</dt>
                      <dd className="font-medium">{application.applicant?.name ?? "—"}</dd>
                    </div>
                    <div className="flex justify-between">
                      <dt className="text-muted-foreground">Host Agency</dt>
                      <dd className="font-medium">{selectedAgency?.name ?? "—"}</dd>
                    </div>
                    <div className="flex justify-between">
                      <dt className="text-muted-foreground">Deployment Site</dt>
                      <dd className="font-medium">{selectedSite?.name ?? "—"}</dd>
                    </div>
                    <div className="flex justify-between">
                      <dt className="text-muted-foreground">Position</dt>
                      <dd className="font-medium">{selectedSlot?.title ?? "—"}</dd>
                    </div>
                    <div className="flex justify-between">
                      <dt className="text-muted-foreground">Available Slots</dt>
                      <dd className="font-medium">{selectedSlot?.available_count ?? 0}</dd>
                    </div>
                  </dl>
                </div>
                {assignError ? (
                  <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {assignError}
                  </p>
                ) : null}
              </div>
              <DialogFooter>
                <Button variant="outline" onClick={handleCancelAssign} disabled={assignLoading}>
                  Cancel
                </Button>
                <Button onClick={handleConfirmAssign} disabled={assignLoading}>
                  {assignLoading ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
                  <Send aria-hidden="true" />
                  Confirm Assignment
                </Button>
              </DialogFooter>
            </>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}
