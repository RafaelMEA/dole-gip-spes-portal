import { Link } from "react-router-dom"
import {
  ArrowRight,
  Building2,
  CalendarRange,
  CheckCircle2,
  ClipboardList,
  Clock,
  FileCheck2,
  Inbox,
  Users,
} from "lucide-react"
import { useAuth } from "@/auth/useAuth"
import { fetchStaffDashboard } from "@/api/staff"
import { useAsync } from "@/lib/useAsync"
import { formatDateTime } from "@/lib/format"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { PageHeader } from "@/components/PageHeader"
import { EmptyState } from "@/components/EmptyState"
import { ApplicationStatusBadge } from "@/components/StatusBadge"
import { FullPageLoader } from "@/components/FullPageLoader"
import { ApiError } from "@/lib/api"

function StatCard({
  icon: Icon,
  label,
  value,
  to,
}: {
  icon: typeof ClipboardList
  label: string
  value: number
  to?: string
}) {
  const content = (
    <Card className="h-full transition-shadow hover:shadow-sm">
      <CardContent className="flex items-center gap-4 p-5">
        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
          <Icon className="size-5" aria-hidden="true" />
        </div>
        <div className="min-w-0">
          <p className="text-2xl font-semibold tabular-nums">{value}</p>
          <p className="truncate text-sm text-muted-foreground">{label}</p>
        </div>
      </CardContent>
    </Card>
  )
  return to ? (
    <Link to={to} className="block">
      {content}
    </Link>
  ) : (
    content
  )
}

export function StaffDashboardPage() {
  const { user } = useAuth()
  const { data, loading, error } = useAsync(fetchStaffDashboard)

  if (loading && !data) return <FullPageLoader />

  return (
    <div className="space-y-6">
      <PageHeader
        title={`Welcome, ${user?.name?.split(" ")[0] ?? "staff"}`}
        description="Review applications, verify documents, and manage deployments."
      >
        <Button nativeButton={false} render={<Link to="/staff/review" />}>
          <ClipboardList aria-hidden="true" />
          Open review queue
        </Button>
      </PageHeader>

      {error ? (
        <p
          role="alert"
          className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {error instanceof ApiError ? error.message : "Failed to load the dashboard."}
        </p>
      ) : null}

      {data ? (
        <>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard icon={ClipboardList} label="Total applications" value={data.stats.total_applications} to="/staff/review" />
            <StatCard icon={Clock} label="Pending review" value={data.stats.pending_review} to="/staff/review" />
            <StatCard icon={FileCheck2} label="Documents pending" value={data.stats.documents_pending} to="/staff/review" />
            <StatCard icon={CheckCircle2} label="Approved" value={data.stats.approved} to="/staff/review" />
            <StatCard icon={Users} label="Deployed" value={data.stats.deployed} to="/staff/deployments" />
            <StatCard icon={Building2} label="Active assignments" value={data.stats.active_assignments} to="/staff/deployments" />
            <StatCard icon={CalendarRange} label="Open cycles" value={data.stats.open_cycles} to="/staff/catalog" />
          </div>

          <div className="grid gap-6 lg:grid-cols-2">
            <Card>
              <CardHeader className="flex-row items-center justify-between space-y-0">
                <div className="space-y-1">
                  <CardTitle>Review queue</CardTitle>
                  <CardDescription>Applications waiting for your action</CardDescription>
                </div>
                <Button nativeButton={false} variant="ghost" size="sm" render={<Link to="/staff/review" />}>
                  View all
                  <ArrowRight aria-hidden="true" />
                </Button>
              </CardHeader>
              <CardContent className="space-y-3">
                {data.review_queue.length === 0 ? (
                  <EmptyState icon={Inbox} title="Queue is clear" description="No applications are waiting for review." />
                ) : (
                  data.review_queue.map((application) => (
                    <div
                      key={application.id}
                      className="flex flex-col gap-2 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                      <div className="min-w-0 space-y-1">
                        <p className="truncate text-sm font-medium">{application.applicant?.name ?? `Applicant #${application.id}`}</p>
                        <p className="truncate text-xs text-muted-foreground">
                          {application.program_cycle?.program?.name} · {application.program_cycle?.name}
                        </p>
                        <p className="text-xs text-muted-foreground">
                          Submitted {formatDateTime(application.submitted_at ?? application.created_at)}
                        </p>
                      </div>
                      <div className="flex shrink-0 items-center gap-2">
                        <ApplicationStatusBadge status={application.status} label={application.status_label} />
                        <Button
                          nativeButton={false}
                          variant="ghost"
                          size="icon-sm"
                          render={<Link to={`/staff/applications/${application.id}`} />}
                          aria-label="Open application"
                        >
                          <ArrowRight aria-hidden="true" />
                        </Button>
                      </div>
                    </div>
                  ))
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex-row items-center justify-between space-y-0">
                <div className="space-y-1">
                  <CardTitle>Recent applications</CardTitle>
                  <CardDescription>Latest submissions across all programs</CardDescription>
                </div>
                <Button nativeButton={false} variant="ghost" size="sm" render={<Link to="/staff/review" />}>
                  View all
                  <ArrowRight aria-hidden="true" />
                </Button>
              </CardHeader>
              <CardContent className="space-y-3">
                {data.recent_applications.length === 0 ? (
                  <EmptyState icon={Inbox} title="No applications yet" description="New applications will appear here." />
                ) : (
                  data.recent_applications.map((application) => (
                    <div
                      key={application.id}
                      className="flex flex-col gap-2 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                      <div className="min-w-0 space-y-1">
                        <p className="truncate text-sm font-medium">{application.applicant?.name ?? `Applicant #${application.id}`}</p>
                        <p className="truncate text-xs text-muted-foreground">
                          {application.program_cycle?.name ?? `Application #${application.id}`}
                        </p>
                      </div>
                      <div className="flex shrink-0 items-center gap-2">
                        <ApplicationStatusBadge status={application.status} label={application.status_label} />
                        <Button
                          nativeButton={false}
                          variant="ghost"
                          size="icon-sm"
                          render={<Link to={`/staff/applications/${application.id}`} />}
                          aria-label="Open application"
                        >
                          <ArrowRight aria-hidden="true" />
                        </Button>
                      </div>
                    </div>
                  ))
                )}
              </CardContent>
            </Card>
          </div>
        </>
      ) : null}
    </div>
  )
}
