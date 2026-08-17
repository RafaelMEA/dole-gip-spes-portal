import { useCallback, useEffect, useState } from "react"
import { Link } from "react-router-dom"
import {
  ArrowRight,
  Building2,
  CalendarRange,
  Inbox,
  Loader2,
  MapPin,
  Plus,
  Send,
} from "lucide-react"
import {
  createDeployment,
  fetchDeploymentSites,
  fetchDeployments,
  fetchHostAgencies,
  fetchReviewQueue,
  updateDeploymentStatus,
} from "@/api/staff"
import { useAsync } from "@/lib/useAsync"
import { ApiError } from "@/lib/api"
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
import { Card, CardContent } from "@/components/ui/card"
import { PageHeader } from "@/components/PageHeader"
import { EmptyState } from "@/components/EmptyState"
import { DeploymentStatusBadge } from "@/components/StatusBadge"
import { FullPageLoader } from "@/components/FullPageLoader"
import { useToast } from "@/toast/useToast"
import type { DeploymentAssignment } from "@/types/api"

export function StaffDeploymentsPage() {
  const { toast } = useToast()
  const [pageNumber, setPageNumber] = useState(1)

  const fetcher = useCallback(() => fetchDeployments(pageNumber), [pageNumber])
  const { data: page, loading, error, reload } = useAsync(fetcher)

  const { data: approvedPage } = useAsync(
    useCallback(() => fetchReviewQueue({ status: "approved", per_page: 100 }), []),
  )
  const { data: agenciesPage } = useAsync(useCallback(() => fetchHostAgencies({ per_page: 100 }), []))
  const { data: sites } = useAsync(fetchDeploymentSites)

  const [createOpen, setCreateOpen] = useState(false)
  const [creating, setCreating] = useState(false)
  const [createError, setCreateError] = useState<string | null>(null)
  const [form, setForm] = useState({
    application_id: "",
    host_agency_id: "",
    deployment_site_id: "",
    position: "",
    start_date: "",
    end_date: "",
    remarks: "",
  })

  const [statusTarget, setStatusTarget] = useState<DeploymentAssignment | null>(null)
  const [statusTo, setStatusTo] = useState<"active" | "completed" | "cancelled" | null>(null)
  const [statusLoading, setStatusLoading] = useState(false)

  useEffect(() => {
    if (error) {
      toast({
        title: "Unable to load deployments",
        description: error instanceof ApiError ? error.message : "Please try again.",
        variant: "error",
      })
    }
  }, [error, toast])

  function openCreate() {
    setForm({
      application_id: "",
      host_agency_id: "",
      deployment_site_id: "",
      position: "",
      start_date: "",
      end_date: "",
      remarks: "",
    })
    setCreateError(null)
    setCreateOpen(true)
  }

  async function handleCreate() {
    if (!form.application_id || !form.host_agency_id || !form.start_date) {
      setCreateError("Application, host agency, and start date are required.")
      return
    }
    setCreating(true)
    setCreateError(null)
    try {
      await createDeployment({
        application_id: Number(form.application_id),
        host_agency_id: Number(form.host_agency_id),
        deployment_site_id: form.deployment_site_id ? Number(form.deployment_site_id) : null,
        position: form.position || undefined,
        start_date: form.start_date,
        end_date: form.end_date || null,
        remarks: form.remarks || undefined,
      })
      toast({ title: "Deployment scheduled", description: "The deployment assignment has been created.", variant: "success" })
      setCreateOpen(false)
      await reload()
    } catch (err) {
      const message = err instanceof ApiError ? err.message : "Unable to create deployment."
      setCreateError(message)
      toast({ title: "Unable to schedule", description: message, variant: "error" })
    } finally {
      setCreating(false)
    }
  }

  async function handleStatusChange() {
    if (!statusTarget || !statusTo) return
    setStatusLoading(true)
    try {
      await updateDeploymentStatus(statusTarget.id, statusTo)
      toast({ title: "Deployment updated", description: "The assignment status has been updated.", variant: "success" })
      setStatusTarget(null)
      await reload()
    } catch (err) {
      toast({
        title: "Unable to update",
        description: err instanceof ApiError ? err.message : "Please try again.",
        variant: "error",
      })
    } finally {
      setStatusLoading(false)
    }
  }

  if (loading && !page) return <FullPageLoader />

  const approvedApplications = approvedPage?.data ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Deployments"
        description="Manage deployment assignments for approved applications."
      >
        <Button onClick={openCreate}>
          <Plus aria-hidden="true" />
          Schedule deployment
        </Button>
      </PageHeader>

      {page ? (
        <>
          <p className="text-sm text-muted-foreground">
            Showing {page.from ?? 0}–{page.to ?? 0} of {page.total} assignments
          </p>

          {page.data.length === 0 ? (
            <EmptyState
              icon={Inbox}
              title="No deployments yet"
              description="Schedule a deployment for an approved application to get started."
            />
          ) : (
            <div className="space-y-3">
              {page.data.map((assignment) => (
                <Card key={assignment.id}>
                  <CardContent className="flex flex-col gap-3 p-4 lg:flex-row lg:items-center lg:justify-between">
                    <div className="min-w-0 space-y-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <p className="text-sm font-medium">{assignment.applicant?.name ?? `Assignment #${assignment.id}`}</p>
                        <DeploymentStatusBadge status={assignment.status} label={assignment.status_label} />
                      </div>
                      <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                        {assignment.host_agency ? (
                          <span className="inline-flex items-center gap-1">
                            <Building2 className="size-3.5" aria-hidden="true" />
                            {assignment.host_agency.name}
                          </span>
                        ) : null}
                        {assignment.deployment_site ? (
                          <span className="inline-flex items-center gap-1">
                            <MapPin className="size-3.5" aria-hidden="true" />
                            {assignment.deployment_site.name}
                          </span>
                        ) : null}
                        {assignment.position ? (
                          <span>{assignment.position}</span>
                        ) : null}
                        {assignment.start_date ? (
                          <span className="inline-flex items-center gap-1">
                            <CalendarRange className="size-3.5" aria-hidden="true" />
                            {assignment.start_date}
                            {assignment.end_date ? ` – ${assignment.end_date}` : ""}
                          </span>
                        ) : null}
                      </div>
                    </div>

                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                      {assignment.status === "scheduled" ? (
                        <>
                          <Button size="sm" onClick={() => { setStatusTarget(assignment); setStatusTo("active") }}>
                            Mark active
                          </Button>
                          <Button variant="outline" size="sm" onClick={() => { setStatusTarget(assignment); setStatusTo("cancelled") }} className="text-destructive hover:text-destructive">
                            Cancel
                          </Button>
                        </>
                      ) : null}
                      {assignment.status === "active" ? (
                        <Button size="sm" onClick={() => { setStatusTarget(assignment); setStatusTo("completed") }}>
                          Mark completed
                        </Button>
                      ) : null}
                      <Button
                        nativeButton={false}
                        variant="ghost"
                        size="icon-sm"
                        render={<Link to={`/staff/applications/${assignment.application_id}`} />}
                        aria-label="Open application"
                      >
                        <ArrowRight aria-hidden="true" />
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}

          {page.last_page > 1 ? (
            <div className="flex items-center justify-between">
              <Button variant="outline" size="sm" disabled={page.current_page <= 1} onClick={() => setPageNumber(page.current_page - 1)}>
                Previous
              </Button>
              <p className="text-sm text-muted-foreground">
                Page {page.current_page} of {page.last_page}
              </p>
              <Button variant="outline" size="sm" disabled={page.current_page >= page.last_page} onClick={() => setPageNumber(page.current_page + 1)}>
                Next
              </Button>
            </div>
          ) : null}
        </>
      ) : null}

      <Dialog open={createOpen} onOpenChange={setCreateOpen}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Schedule deployment</DialogTitle>
            <DialogDescription>Assign an approved applicant to a host agency.</DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            {createError ? (
              <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {createError}
              </p>
            ) : null}

            <div className="space-y-2">
              <Label htmlFor="deploy-app">Approved applicant</Label>
              <Select value={form.application_id} onValueChange={(value) => setForm({ ...form, application_id: value ?? "" })}>
                <SelectTrigger id="deploy-app" className="w-full">
                  <SelectValue placeholder="Select an approved application" />
                </SelectTrigger>
                <SelectContent>
                  {approvedApplications.map((application) => (
                    <SelectItem key={application.id} value={String(application.id)}>
                      {application.applicant?.name ?? `Application #${application.id}`}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="deploy-agency">Host agency</Label>
              <Select value={form.host_agency_id} onValueChange={(value) => setForm({ ...form, host_agency_id: value ?? "" })}>
                <SelectTrigger id="deploy-agency" className="w-full">
                  <SelectValue placeholder="Select host agency" />
                </SelectTrigger>
                <SelectContent>
                  {(agenciesPage?.data ?? []).map((agency) => (
                    <SelectItem key={agency.id} value={String(agency.id)}>
                      {agency.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="deploy-site">Deployment site (optional)</Label>
              <Select value={form.deployment_site_id} onValueChange={(value) => setForm({ ...form, deployment_site_id: value ?? "" })}>
                <SelectTrigger id="deploy-site" className="w-full">
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
              <Label htmlFor="deploy-position">Position (optional)</Label>
              <Input id="deploy-position" value={form.position} onChange={(event) => setForm({ ...form, position: event.target.value })} placeholder="e.g. Administrative Intern" />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="deploy-start">Start date</Label>
                <Input id="deploy-start" type="date" value={form.start_date} onChange={(event) => setForm({ ...form, start_date: event.target.value })} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="deploy-end">End date (optional)</Label>
                <Input id="deploy-end" type="date" value={form.end_date} onChange={(event) => setForm({ ...form, end_date: event.target.value })} />
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="deploy-remarks">Remarks (optional)</Label>
              <Textarea id="deploy-remarks" value={form.remarks} onChange={(event) => setForm({ ...form, remarks: event.target.value })} placeholder="Any notes..." />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setCreateOpen(false)} disabled={creating}>
              Cancel
            </Button>
            <Button onClick={handleCreate} disabled={creating}>
              {creating ? <Loader2 className="animate-spin" aria-hidden="true" /> : <Send aria-hidden="true" />}
              Schedule
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={statusTarget !== null} onOpenChange={(open) => !open && setStatusTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {statusTo === "active"
                ? "Mark deployment active"
                : statusTo === "completed"
                  ? "Mark deployment completed"
                  : "Cancel deployment"}
            </DialogTitle>
            <DialogDescription>
              {statusTarget?.applicant?.name ?? `Assignment #${statusTarget?.id}`} ·{" "}
              {statusTarget?.host_agency?.name ?? "Host agency"}
            </DialogDescription>
          </DialogHeader>
          <p className="text-sm text-muted-foreground">
            {statusTo === "active"
              ? "This will mark the applicant as deployed and move the application to 'deployed'."
              : statusTo === "completed"
                ? "This will complete the application and end the assignment."
                : "This will cancel the assignment and return the application to 'approved'."}
          </p>
          <DialogFooter>
            <Button variant="outline" onClick={() => setStatusTarget(null)} disabled={statusLoading}>
              Close
            </Button>
            <Button
              variant={statusTo === "cancelled" ? "outline" : "default"}
              className={statusTo === "cancelled" ? "text-destructive hover:text-destructive" : undefined}
              onClick={handleStatusChange}
              disabled={statusLoading}
            >
              {statusLoading ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              Confirm
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
