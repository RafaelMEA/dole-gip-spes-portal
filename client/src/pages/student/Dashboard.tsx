import { Link } from "react-router-dom"
import { ArrowRight, CalendarRange, ClipboardList, FileText, Inbox } from "lucide-react"
import { useAuth } from "@/auth/useAuth"
import { fetchStudentDashboard } from "@/api/student"
import { useAsync } from "@/lib/useAsync"
import { formatDate, formatRelative } from "@/lib/format"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { PageHeader } from "@/components/PageHeader"
import { EmptyState } from "@/components/EmptyState"
import { ApplicationStatusBadge, CycleStatusBadge } from "@/components/StatusBadge"
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
          <p className="text-sm text-muted-foreground">{label}</p>
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

export function StudentDashboardPage() {
  const { user } = useAuth()
  const { data, loading, error } = useAsync(fetchStudentDashboard)

  if (loading && !data) return <FullPageLoader />

  return (
    <div className="space-y-6">
      <PageHeader
        title={`Welcome back, ${user?.name?.split(" ")[0] ?? "student"}`}
        description="Track your applications and browse open programs."
      >
        <Button nativeButton={false} render={<Link to="/student/programs" />}>
          <CalendarRange aria-hidden="true" />
          Browse programs
        </Button>
      </PageHeader>

      {error ? (
        <p
          role="alert"
          className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {error instanceof ApiError
            ? error.message
            : "Failed to load your dashboard. Please try again."}
        </p>
      ) : null}

      {data ? (
        <>
          <div className="grid gap-4 sm:grid-cols-3">
            <StatCard
              icon={ClipboardList}
              label="Total applications"
              value={data.stats.total_applications}
              to="/student/applications"
            />
            <StatCard
              icon={FileText}
              label="Draft applications"
              value={data.stats.draft_applications}
              to="/student/applications"
            />
            <StatCard
              icon={ArrowRight}
              label="Active applications"
              value={data.stats.active_applications}
              to="/student/applications"
            />
          </div>

          <div className="grid gap-6 lg:grid-cols-2">
            <Card>
              <CardHeader className="flex-row items-center justify-between space-y-0">
                <div className="space-y-1">
                  <CardTitle>Open programs</CardTitle>
                  <CardDescription>Programs currently accepting applications</CardDescription>
                </div>
                <Button nativeButton={false} variant="ghost" size="sm" render={<Link to="/student/programs" />}>
                  View all
                  <ArrowRight aria-hidden="true" />
                </Button>
              </CardHeader>
              <CardContent className="space-y-3">
                {data.open_cycles.length === 0 ? (
                  <EmptyState
                    icon={CalendarRange}
                    title="No open programs"
                    description="New program cycles will appear here once applications open."
                  />
                ) : (
                  data.open_cycles.slice(0, 4).map((cycle) => (
                    <div
                      key={cycle.id}
                      className="flex flex-col gap-2 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                      <div className="min-w-0 space-y-1">
                        <p className="truncate text-sm font-medium">
                          {cycle.program?.name ?? cycle.name}
                        </p>
                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                          <CycleStatusBadge status={cycle.status} />
                          <span className="inline-flex items-center gap-1">
                            <CalendarRange className="size-3" aria-hidden="true" />
                            Deadline {formatRelative(cycle.application_deadline)}
                          </span>
                        </div>
                      </div>
                      <Button nativeButton={false} size="sm" render={<Link to="/student/programs" />}>
                        Apply
                      </Button>
                    </div>
                  ))
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex-row items-center justify-between space-y-0">
                <div className="space-y-1">
                  <CardTitle>Recent applications</CardTitle>
                  <CardDescription>Your latest applications and their status</CardDescription>
                </div>
                <Button nativeButton={false} variant="ghost" size="sm" render={<Link to="/student/applications" />}>
                  View all
                  <ArrowRight aria-hidden="true" />
                </Button>
              </CardHeader>
              <CardContent className="space-y-3">
                {data.applications.length === 0 ? (
                  <EmptyState
                    icon={Inbox}
                    title="No applications yet"
                    description="Apply to an open program to get started."
                  />
                ) : (
                  data.applications.slice(0, 4).map((application) => (
                    <div
                      key={application.id}
                      className="flex flex-col gap-2 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                      <div className="min-w-0 space-y-1">
                        <p className="truncate text-sm font-medium">
                          {application.program_cycle?.name ?? `Application #${application.id}`}
                        </p>
                        <p className="text-xs text-muted-foreground">
                          Submitted {formatDate(application.submitted_at ?? application.created_at)}
                        </p>
                      </div>
                      <div className="flex shrink-0 items-center gap-2">
                        <ApplicationStatusBadge status={application.status} />
                        <Button
                          nativeButton={false}
                          variant="ghost"
                          size="icon-sm"
                          render={<Link to={`/student/applications/${application.id}`} />}
                          aria-label="View application"
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
