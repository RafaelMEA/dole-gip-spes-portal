import { useCallback, useState } from "react"
import { Link, useNavigate, useParams } from "react-router-dom"
import {
  AlertCircle,
  ArrowLeft,
  BadgeCheck,
  CheckCircle2,
  ClipboardCheck,
  FileText,
  Loader2,
  Pencil,
  Send,
  User,
  XCircle,
} from "lucide-react"
import {
  fetchApplication,
  fetchApplicationCompleteness,
  fetchProfile,
  submitApplication,
} from "@/api/student"
import { useAsync } from "@/lib/useAsync"
import { ApiError } from "@/lib/api"
import { formatDate } from "@/lib/format"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
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
import { ApplicationStatusBadge } from "@/components/StatusBadge"
import { FullPageLoader } from "@/components/FullPageLoader"
import { useToast } from "@/toast/useToast"
import type { Application, ApplicationCompleteness, StudentProfile } from "@/types/api"

interface ReviewData {
  application: Application
  completeness: ApplicationCompleteness
  profile: StudentProfile
}

function ordinalSuffix(value: string | number): string {
  const num = Number(value)
  const j = num % 10
  const k = num % 100
  if (j === 1 && k !== 11) return `${num}st`
  if (j === 2 && k !== 12) return `${num}nd`
  if (j === 3 && k !== 13) return `${num}rd`
  return `${num}th`
}

function DetailRow({ label, value }: { label: string; value: string | null | undefined }) {
  return (
    <div>
      <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
      <p className="mt-0.5">{value?.trim() ? value : "—"}</p>
    </div>
  )
}

function ChecklistRow({
  complete,
  label,
  missing,
}: {
  complete: boolean
  label: string
  missing: string[]
}) {
  return (
    <div className="space-y-1.5">
      <div className="flex items-center gap-2">
        {complete ? (
          <CheckCircle2 className="size-4 shrink-0 text-emerald-600" aria-hidden="true" />
        ) : (
          <XCircle className="size-4 shrink-0 text-red-600" aria-hidden="true" />
        )}
        <p className="text-sm font-medium">{label}</p>
      </div>
      {!complete && missing.length > 0 ? (
        <ul className="ml-6 space-y-1 text-sm text-muted-foreground">
          {missing.map((item) => (
            <li key={item} className="flex items-center gap-1.5 text-red-600">
              <XCircle className="size-3.5 shrink-0" aria-hidden="true" />
              {item}
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  )
}

export function StudentApplicationReviewPage() {
  const { id } = useParams<{ id: string }>()
  const applicationId = Number(id)
  const { toast } = useToast()
  const navigate = useNavigate()

  const fetcher = useCallback(async (): Promise<ReviewData> => {
    const [application, completeness, profile] = await Promise.all([
      fetchApplication(applicationId),
      fetchApplicationCompleteness(applicationId),
      fetchProfile(),
    ])
    return { application, completeness, profile }
  }, [applicationId])

  const { data, loading, error } = useAsync(fetcher)

  const [confirmOpen, setConfirmOpen] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState<string | null>(null)

  async function handleSubmit() {
    setSubmitting(true)
    setSubmitError(null)
    try {
      await submitApplication(applicationId)
      toast({
        title: "Application submitted successfully",
        description: "Your application has been submitted and is awaiting review.",
        variant: "success",
      })
      setConfirmOpen(false)
      navigate(`/student/applications/${applicationId}`, { replace: true })
    } catch (err) {
      const message =
        err instanceof ApiError ? err.message : "Unable to submit your application. Please try again."
      setSubmitError(message)
      toast({ title: "Unable to submit", description: message, variant: "error" })
    } finally {
      setSubmitting(false)
    }
  }

  if (loading && !data) return <FullPageLoader />

  if (error && !data) {
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

  if (!data) return null

  const { application, completeness, profile } = data
  const cycle = application.program_cycle
  const documents = application.documents ?? []
  const requirements = cycle?.requirements ?? []
  const requiredRequirements = requirements.filter((requirement) => requirement.is_required)
  const uploadedByRequirement = new Map(
    documents.map((document) => [document.requirement_id, document]),
  )
  const detail = profile.student_details
  const missingDocNames = completeness.missing_requirements.map((requirement) => requirement.name)

  const infoIncomplete = !completeness.application_complete
  const documentsIncomplete = !completeness.documents_complete
  const canSubmit = completeness.is_complete

  const backLink = (
    <Button nativeButton={false} variant="ghost" size="sm" render={<Link to="/student/applications" />}>
      <ArrowLeft aria-hidden="true" />
      Back to applications
    </Button>
  )

  if (application.status !== "draft") {
    return (
      <div className="space-y-6">
        {backLink}
        <PageHeader
          title={cycle?.name ?? `Application #${application.id}`}
          description={cycle?.program?.name}
        >
          <ApplicationStatusBadge status={application.status} label={application.status_label} />
        </PageHeader>
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <BadgeCheck className="size-5 text-emerald-600" aria-hidden="true" />
              Application submitted
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3 text-sm">
            <p>
              Your application has been submitted successfully and is awaiting review. You can no
              longer edit it or modify its documents.
            </p>
            <div>
              <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Status</p>
              <p className="mt-0.5 font-medium">{application.status_label}</p>
            </div>
            <div>
              <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Submitted</p>
              <p className="mt-0.5">{formatDate(application.submitted_at)}</p>
            </div>
          </CardContent>
        </Card>
        <Button nativeButton={false} render={<Link to={`/student/applications/${applicationId}`} />}>
          View application details
        </Button>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {backLink}

      <PageHeader
        title="Application Review"
        description={`${cycle?.name ?? `Application #${application.id}`}${cycle?.program?.name ? ` · ${cycle.program.name}` : ""}`}
      >
        <ApplicationStatusBadge status={application.status} label={application.status_label} />
      </PageHeader>

      <div className="grid gap-6 lg:grid-cols-2">
        <div className="space-y-6">
          <Card>
            <CardHeader className="flex-row items-center justify-between space-y-0">
              <div className="space-y-1">
                <CardTitle className="flex items-center gap-2">
                  <User className="size-4 text-muted-foreground" aria-hidden="true" />
                  Personal information
                </CardTitle>
                <CardDescription>This profile is submitted as your application information.</CardDescription>
              </div>
              <Button
                nativeButton={false}
                variant="outline"
                size="sm"
                render={<Link to="/student/profile" />}
              >
                <Pencil aria-hidden="true" />
                Edit
              </Button>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              <DetailRow label="Name" value={profile.name} />
              <DetailRow label="School" value={detail?.school_name} />
              <DetailRow label="Course" value={detail?.course} />
              <DetailRow
                label="Year level"
                value={detail?.year_level ? ordinalSuffix(detail.year_level) : null}
              />
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex-row items-center justify-between space-y-0">
              <div className="space-y-1">
                <CardTitle className="flex items-center gap-2">
                  <FileText className="size-4 text-muted-foreground" aria-hidden="true" />
                  Application information
                </CardTitle>
                <CardDescription>Details specific to this application.</CardDescription>
              </div>
              <Button
                nativeButton={false}
                variant="outline"
                size="sm"
                render={<Link to={`/student/applications/${applicationId}`} />}
              >
                <Pencil aria-hidden="true" />
                Edit
              </Button>
            </CardHeader>
            <CardContent className="text-sm">
              <p className="text-muted-foreground">
                {application.remarks?.trim() ? application.remarks : "No additional information provided."}
              </p>
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <ClipboardCheck className="size-4 text-muted-foreground" aria-hidden="true" />
                Documents
              </CardTitle>
              <CardDescription>
                {requiredRequirements.length === 0
                  ? "No documents required for this program."
                  : completeness.documents_complete
                    ? "All required documents uploaded."
                    : `${requiredRequirements.length - completeness.missing_requirements.length} of ${requiredRequirements.length} required documents uploaded.`}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-2">
              {requirements.length === 0 ? (
                <p className="text-sm text-muted-foreground">No documents required for this program.</p>
              ) : (
                requirements.map((requirement) => {
                  const document = uploadedByRequirement.get(requirement.id)
                  const missing = requirement.is_required && !document
                  return (
                    <div
                      key={requirement.id}
                      className="flex items-center justify-between gap-3 rounded-lg border p-3"
                    >
                      <div className="flex min-w-0 items-center gap-2">
                        {missing ? (
                          <XCircle className="size-4 shrink-0 text-red-600" aria-hidden="true" />
                        ) : (
                          <CheckCircle2 className="size-4 shrink-0 text-emerald-600" aria-hidden="true" />
                        )}
                        <p className="truncate text-sm font-medium">{requirement.name}</p>
                        {requirement.is_required ? (
                          <Badge variant="secondary">Required</Badge>
                        ) : (
                          <Badge variant="outline">Optional</Badge>
                        )}
                      </div>
                      {missing ? (
                        <span className="shrink-0 text-xs text-red-600">Missing</span>
                      ) : (
                        <span className="shrink-0 truncate text-xs text-emerald-600">
                          {document?.file_name ?? "Uploaded"}
                        </span>
                      )}
                    </div>
                  )
                })
              )}
            </CardContent>
          </Card>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Application checklist</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <ChecklistRow
            complete={completeness.application_complete}
            label="Required information complete"
            missing={completeness.missing_application_fields}
          />
          <ChecklistRow
            complete={completeness.documents_complete}
            label="Required documents complete"
            missing={missingDocNames}
          />
        </CardContent>
      </Card>

      {canSubmit ? (
        <div className="flex flex-col gap-3 rounded-lg border border-emerald-600/30 bg-emerald-600/10 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-2 text-sm text-emerald-700">
            <CheckCircle2 className="size-5 shrink-0" aria-hidden="true" />
            <p className="font-medium">Application is ready for submission.</p>
          </div>
          <Button size="lg" onClick={() => setConfirmOpen(true)}>
            <Send aria-hidden="true" />
            Submit application
          </Button>
        </div>
      ) : (
        <div className="space-y-4">
          <div className="rounded-lg border border-amber-600/30 bg-amber-600/10 px-4 py-3 text-sm text-amber-800">
            <div className="flex items-center gap-2 font-medium">
              <AlertCircle className="size-5 shrink-0" aria-hidden="true" />
              <p>Application is not ready for submission.</p>
            </div>
            <div className="mt-2 space-y-1 text-sm">
              {infoIncomplete && completeness.missing_application_fields.length > 0 ? (
                <p>
                  Missing application information:{" "}
                  {completeness.missing_application_fields.join(", ")}
                </p>
              ) : null}
              {documentsIncomplete && missingDocNames.length > 0 ? (
                <p>Missing required documents: {missingDocNames.join(", ")}</p>
              ) : null}
            </div>
          </div>
          <div className="flex flex-wrap gap-3">
            {infoIncomplete ? (
              <Button
                nativeButton={false}
                variant="outline"
                render={<Link to={`/student/applications/${applicationId}`} />}
              >
                Complete application
              </Button>
            ) : null}
            {documentsIncomplete ? (
              <Button
                nativeButton={false}
                render={<Link to={`/student/applications/${applicationId}`} />}
              >
                Upload missing documents
              </Button>
            ) : null}
          </div>
        </div>
      )}

      <Dialog open={confirmOpen} onOpenChange={(open) => !submitting && setConfirmOpen(open)}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Submit application?</DialogTitle>
            <DialogDescription>
              Once submitted, you will no longer be able to edit your application or modify its
              documents unless DOLE staff returns the application for correction. Are you sure you
              want to submit?
            </DialogDescription>
          </DialogHeader>
          {submitError ? (
            <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
              {submitError}
            </p>
          ) : null}
          <DialogFooter>
            <Button variant="outline" onClick={() => setConfirmOpen(false)} disabled={submitting}>
              Cancel
            </Button>
            <Button onClick={handleSubmit} disabled={submitting}>
              {submitting ? <Loader2 className="animate-spin" aria-hidden="true" /> : <Send aria-hidden="true" />}
              {submitting ? "Submitting..." : "Submit application"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
