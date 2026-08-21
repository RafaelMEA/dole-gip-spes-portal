import { useCallback, useEffect, useState } from "react"
import { Link } from "react-router-dom"
import {
  ArrowRight,
  Building2,
  CalendarRange,
  History,
  Inbox,
  Loader2,
  MapPin,
  Search,
} from "lucide-react"
import {
  cancelDeployment,
  fetchDeployments,
  fetchAssignmentHistory,
  fetchCyclesCatalog,
  fetchHostAgencies,
  fetchDeploymentSitesAll,
  updateDeploymentStatus,
} from "@/api/staff"
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
import { Card, CardContent } from "@/components/ui/card"
import { PageHeader } from "@/components/PageHeader"
import { EmptyState } from "@/components/EmptyState"
import { HistoryFeed } from "@/components/HistoryTimeline"
import { DeploymentStatusBadge } from "@/components/StatusBadge"
import { FullPageLoader } from "@/components/FullPageLoader"
import { useToast } from "@/toast/useToast"
import type { AssignmentFilters, DeploymentAssignment } from "@/types/api"

export function StaffDeploymentsPage() {
  const { toast } = useToast()
  const [pageNumber, setPageNumber] = useState(1)
  const [filters, setFilters] = useState<AssignmentFilters>({})

  const fetcher = useCallback(() => fetchDeployments(pageNumber, filters), [pageNumber, filters])
  const { data: page, loading, error, reload } = useAsync(fetcher)

  const { data: cycles } = useAsync(useCallback(() => fetchCyclesCatalog(), []))
  const { data: agenciesPage } = useAsync(useCallback(() => fetchHostAgencies({ per_page: 100 }), []))
  const { data: sites } = useAsync(fetchDeploymentSitesAll)

  const [statusTarget, setStatusTarget] = useState<DeploymentAssignment | null>(null)
  const [statusTo, setStatusTo] = useState<"active" | "completed" | null>(null)
  const [statusLoading, setStatusLoading] = useState(false)

  const [cancelTarget, setCancelTarget] = useState<DeploymentAssignment | null>(null)
  const [cancelRemarks, setCancelRemarks] = useState("")
  const [cancelLoading, setCancelLoading] = useState(false)

  const [historyTarget, setHistoryTarget] = useState<DeploymentAssignment | null>(null)

  useEffect(() => {
    if (error) {
      toast({
        title: "Unable to load deployments",
        description: error instanceof ApiError ? errMessage(error) : "Please try again.",
        variant: "error",
      })
    }
  }, [error, toast])

  function errMessage(err: unknown): string {
    return err instanceof ApiError ? err.message : "Please try again."
  }

  function updateFilter(key: keyof AssignmentFilters, value: string | null) {
    setFilters((prev) => {
      const next = { ...prev }
      if (value && value !== "all") {
        ;(next as Record<string, unknown>)[key] = value
      } else {
        delete (next as Record<string, unknown>)[key]
      }
      return next
    })
    setPageNumber(1)
  }

  function handleSearchChange(value: string) {
    setFilters((prev) => {
      const next = { ...prev }
      if (value.trim()) {
        next.search = value.trim()
      } else {
        delete next.search
      }
      return next
    })
    setPageNumber(1)
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
        description: errMessage(err),
        variant: "error",
      })
    } finally {
      setStatusLoading(false)
    }
  }

  async function handleCancel() {
    if (!cancelTarget) return
    setCancelLoading(true)
    try {
      await cancelDeployment(cancelTarget.id, cancelRemarks.trim() || undefined)
      toast({ title: "Assignment cancelled", description: "The deployment assignment has been cancelled.", variant: "success" })
      setCancelTarget(null)
      setCancelRemarks("")
      await reload()
    } catch (err) {
      toast({
        title: "Unable to cancel",
        description: errMessage(err),
        variant: "error",
      })
    } finally {
      setCancelLoading(false)
    }
  }

  if (loading && !page) return <FullPageLoader />

  const agencies = agenciesPage?.data ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Deployment Assignments"
        description="View and manage student deployment assignments."
      />

      <div className="flex flex-wrap items-center gap-3">
        <div className="relative flex-1 sm:max-w-xs">
          <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" aria-hidden="true" />
          <Input
            placeholder="Search student..."
            className="pl-8"
            value={filters.search ?? ""}
            onChange={(event) => handleSearchChange(event.target.value)}
          />
        </div>

        <Select value={filters.program_cycle_id ? String(filters.program_cycle_id) : "all"} onValueChange={(v) => updateFilter("program_cycle_id", v)}>
          <SelectTrigger className="w-[160px]">
            <SelectValue placeholder="Program Cycle" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Cycles</SelectItem>
            {(cycles ?? []).map((cycle) => (
              <SelectItem key={cycle.id} value={String(cycle.id)}>
                {cycle.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={filters.host_agency_id ? String(filters.host_agency_id) : "all"} onValueChange={(v) => updateFilter("host_agency_id", v)}>
          <SelectTrigger className="w-[160px]">
            <SelectValue placeholder="Host Agency" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Agencies</SelectItem>
            {agencies.map((agency) => (
              <SelectItem key={agency.id} value={String(agency.id)}>
                {agency.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={filters.deployment_site_id ? String(filters.deployment_site_id) : "all"} onValueChange={(v) => updateFilter("deployment_site_id", v)}>
          <SelectTrigger className="w-[160px]">
            <SelectValue placeholder="Site" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Sites</SelectItem>
            {(sites ?? []).map((site) => (
              <SelectItem key={site.id} value={String(site.id)}>
                {site.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={filters.status ?? "all"} onValueChange={(v) => updateFilter("status", v)}>
          <SelectTrigger className="w-[140px]">
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Status</SelectItem>
            <SelectItem value="scheduled">Scheduled</SelectItem>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="completed">Completed</SelectItem>
            <SelectItem value="cancelled">Cancelled</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {page ? (
        <>
          <p className="text-sm text-muted-foreground">
            Showing {page.from ?? 0}–{page.to ?? 0} of {page.total} assignments
          </p>

          {page.data.length === 0 ? (
            <EmptyState
              icon={Inbox}
              title="No assignments found"
              description="Try adjusting your filters or assign a student to a deployment slot."
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
                        {assignment.deployment_slot ? (
                          <span>{assignment.deployment_slot.title}</span>
                        ) : assignment.position ? (
                          <span>{assignment.position}</span>
                        ) : null}
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
                        {assignment.assigned_at ? (
                          <span className="inline-flex items-center gap-1">
                            <CalendarRange className="size-3.5" aria-hidden="true" />
                            {formatDateTime(assignment.assigned_at)}
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
                          <Button variant="outline" size="sm" onClick={() => setCancelTarget(assignment)} className="text-destructive hover:text-destructive">
                            Cancel
                          </Button>
                        </>
                      ) : null}
                      {assignment.status === "active" ? (
                        <>
                          <Button size="sm" onClick={() => { setStatusTarget(assignment); setStatusTo("completed") }}>
                            Mark completed
                          </Button>
                          <Button variant="outline" size="sm" onClick={() => setCancelTarget(assignment)} className="text-destructive hover:text-destructive">
                            Cancel
                          </Button>
                        </>
                      ) : null}
                      <Button variant="outline" size="sm" onClick={() => setHistoryTarget(assignment)}>
                        <History aria-hidden="true" />
                        History
                      </Button>
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

      <Dialog open={statusTarget !== null} onOpenChange={(open) => !open && setStatusTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {statusTo === "active"
                ? "Mark deployment active"
                : "Mark deployment completed"}
            </DialogTitle>
            <DialogDescription>
              {statusTarget?.applicant?.name ?? `Assignment #${statusTarget?.id}`} ·{" "}
              {statusTarget?.deployment_slot?.title ?? statusTarget?.host_agency?.name ?? "Deployment"}
            </DialogDescription>
          </DialogHeader>
          <p className="text-sm text-muted-foreground">
            {statusTo === "active"
              ? "This will mark the applicant as deployed and move the application to 'deployed'."
              : "This will complete the application and end the assignment."}
          </p>
          <DialogFooter>
            <Button variant="outline" onClick={() => setStatusTarget(null)} disabled={statusLoading}>
              Close
            </Button>
            <Button onClick={handleStatusChange} disabled={statusLoading}>
              {statusLoading ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              Confirm
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={historyTarget !== null} onOpenChange={(open) => !open && setHistoryTarget(null)}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Assignment history</DialogTitle>
            <DialogDescription>
              {historyTarget?.applicant?.name ?? `Assignment #${historyTarget?.id}`} ·{" "}
              {historyTarget?.deployment_slot?.title ?? historyTarget?.host_agency?.name ?? "Deployment"}
            </DialogDescription>
          </DialogHeader>
          {historyTarget ? (
            <HistoryFeed
              fetchPage={(page) => fetchAssignmentHistory(historyTarget.id, page)}
              showChanges
              refreshKey={historyTarget.updated_at}
            />
          ) : null}
        </DialogContent>
      </Dialog>

      <Dialog open={cancelTarget !== null} onOpenChange={(open) => { if (!open) { setCancelTarget(null); setCancelRemarks("") } }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Cancel deployment assignment</DialogTitle>
            <DialogDescription>
              {cancelTarget?.applicant?.name ?? `Assignment #${cancelTarget?.id}`} ·{" "}
              {cancelTarget?.deployment_slot?.title ?? cancelTarget?.host_agency?.name ?? "Deployment"}
            </DialogDescription>
          </DialogHeader>
          <p className="text-sm text-muted-foreground">
            This will cancel the assignment and return the application to approved status. The student may then be assigned to a different slot.
          </p>
          <div className="space-y-2">
            <Label htmlFor="cancel-remarks">Remarks (optional)</Label>
            <Textarea
              id="cancel-remarks"
              value={cancelRemarks}
              onChange={(event) => setCancelRemarks(event.target.value)}
              placeholder="Reason for cancellation..."
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => { setCancelTarget(null); setCancelRemarks("") }} disabled={cancelLoading}>
              Close
            </Button>
            <Button variant="outline" onClick={handleCancel} disabled={cancelLoading} className="text-destructive hover:text-destructive">
              {cancelLoading ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              Cancel Assignment
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
