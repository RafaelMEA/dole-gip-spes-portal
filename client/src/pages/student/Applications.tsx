import { useState } from "react"
import { Link, useNavigate } from "react-router-dom"
import { ArrowRight, CalendarRange, ClipboardList, FileText, SearchX } from "lucide-react"
import { deleteApplication, fetchMyApplications } from "@/api/student"
import { useAsync } from "@/lib/useAsync"
import { ApiError } from "@/lib/api"
import { formatDate } from "@/lib/format"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Card, CardContent } from "@/components/ui/card"
import { PageHeader } from "@/components/PageHeader"
import { EmptyState } from "@/components/EmptyState"
import { ConfirmDialog } from "@/components/ConfirmDialog"
import { ApplicationStatusBadge, CycleStatusBadge } from "@/components/StatusBadge"
import { FullPageLoader } from "@/components/FullPageLoader"
import { useToast } from "@/toast/useToast"
import type { Application } from "@/types/api"

export function StudentApplicationsPage() {
  const { toast } = useToast()
  const navigate = useNavigate()
  const { data, loading, error, reload } = useAsync(fetchMyApplications)
  const [search, setSearch] = useState("")
  const [deleting, setDeleting] = useState<Application | null>(null)
  const [deletingId, setDeletingId] = useState<number | null>(null)

  const applications = (data ?? []).filter((application) => {
    if (!search.trim()) return true
    const term = search.trim().toLowerCase()
    const cycleName = application.program_cycle?.name?.toLowerCase() ?? ""
    const programName = application.program_cycle?.program?.name?.toLowerCase() ?? ""
    return (
      cycleName.includes(term) ||
      programName.includes(term) ||
      application.status_label.toLowerCase().includes(term)
    )
  })

  async function handleDelete() {
    if (!deleting) return
    setDeletingId(deleting.id)
    try {
      await deleteApplication(deleting.id)
      toast({ title: "Application deleted", description: "The draft application has been removed.", variant: "success" })
      setDeleting(null)
      await reload()
    } catch (err) {
      toast({
        title: "Unable to delete",
        description: err instanceof ApiError ? err.message : "Please try again.",
        variant: "error",
      })
    } finally {
      setDeletingId(null)
    }
  }

  if (loading && !data) return <FullPageLoader />

  return (
    <div className="space-y-6">
      <PageHeader title="My applications" description="Review and manage your DOLE program applications.">
        <Button nativeButton={false} render={<Link to="/student/programs" />}>
          <FileText aria-hidden="true" />
          Browse programs
        </Button>
      </PageHeader>

      {error ? (
        <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {error.message}
        </p>
      ) : null}

      <div className="relative max-w-sm">
        <SearchX className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
        <Input
          value={search}
          onChange={(event) => setSearch(event.target.value)}
          placeholder="Search by program or status..."
          className="pl-8"
          aria-label="Search applications"
        />
      </div>

      {applications.length === 0 ? (
        <EmptyState
          icon={ClipboardList}
          title={search.trim() ? "No matches found" : "No applications yet"}
          description={
            search.trim()
              ? "Try a different search term."
              : "Apply to an open program to create your first application."
          }
        >
          {!search.trim() ? (
            <Button nativeButton={false} render={<Link to="/student/programs" />}>
              Browse programs
            </Button>
          ) : null}
        </EmptyState>
      ) : (
        <div className="space-y-3">
          {applications.map((application) => (
            <Card key={application.id} className="transition-shadow hover:shadow-sm">
              <CardContent className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0 space-y-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <button
                      type="button"
                      onClick={() => navigate(`/student/applications/${application.id}`)}
                      className="text-left text-sm font-medium hover:underline"
                    >
                      {application.program_cycle?.program?.name ?? "Program"} ·{" "}
                      {application.program_cycle?.name ?? `Application #${application.id}`}
                    </button>
                    <ApplicationStatusBadge status={application.status} label={application.status_label} />
                  </div>
                  <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                    <span className="inline-flex items-center gap-1">
                      <CalendarRange className="size-3.5" aria-hidden="true" />
                      Created {formatDate(application.created_at)}
                    </span>
                    {application.program_cycle ? (
                      <CycleStatusBadge status={application.program_cycle.status} label={application.program_cycle.status_label} />
                    ) : null}
                    {application.missing_required_documents.length > 0 ? (
                      <span className="text-amber-600">
                        {application.missing_required_documents.length} missing document
                        {application.missing_required_documents.length === 1 ? "" : "s"}
                      </span>
                    ) : null}
                  </div>
                </div>

                <div className="flex shrink-0 items-center gap-2">
                  {application.status === "draft" ? (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => setDeleting(application)}
                      className="text-destructive hover:text-destructive"
                    >
                      Delete
                    </Button>
                  ) : null}
                  <Button
                    nativeButton={false}
                    size="sm"
                    render={<Link to={`/student/applications/${application.id}`} />}
                  >
                    Open
                    <ArrowRight aria-hidden="true" />
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <ConfirmDialog
        open={deleting !== null}
        onOpenChange={(open) => !open && setDeleting(null)}
        title="Delete this application?"
        description="This will permanently remove your draft application. This action cannot be undone."
        confirmLabel="Delete application"
        destructive
        icon={SearchX}
        loading={deletingId !== null}
        onConfirm={handleDelete}
      />
    </div>
  )
}
