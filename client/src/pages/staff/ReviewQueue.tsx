import { useCallback, useEffect, useState } from "react"
import { Link } from "react-router-dom"
import { ArrowRight, Inbox, Search } from "lucide-react"
import { fetchCyclesCatalog, fetchReviewQueue } from "@/api/staff"
import type { ReviewQuery } from "@/api/staff"
import { useAsync } from "@/lib/useAsync"
import { ApiError } from "@/lib/api"
import { formatDateTime } from "@/lib/format"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Card, CardContent } from "@/components/ui/card"
import { PageHeader } from "@/components/PageHeader"
import { EmptyState } from "@/components/EmptyState"
import { ApplicationStatusBadge } from "@/components/StatusBadge"
import { FullPageLoader } from "@/components/FullPageLoader"
import { useToast } from "@/toast/useToast"
import type { ApplicationStatus } from "@/types/api"

const STATUS_OPTIONS: { value: string; label: string }[] = [
  { value: "submitted", label: "Submitted" },
  { value: "under_review", label: "Under review" },
  { value: "documents_incomplete", label: "Documents incomplete" },
  { value: "documents_verified", label: "Documents verified" },
  { value: "approved", label: "Approved" },
  { value: "for_deployment", label: "For deployment" },
  { value: "deployed", label: "Deployed" },
  { value: "completed", label: "Completed" },
  { value: "rejected", label: "Rejected" },
  { value: "withdrawn", label: "Withdrawn" },
]

export function StaffReviewQueuePage() {
  const { toast } = useToast()
  const [query, setQuery] = useState<ReviewQuery>({})
  const [searchInput, setSearchInput] = useState("")

  const { data: cycles } = useAsync(fetchCyclesCatalog)

  const fetcher = useCallback(() => fetchReviewQueue(query), [query])
  const { data: page, loading, error } = useAsync(fetcher)

  function applySearch() {
    setQuery((current) => ({ ...current, search: searchInput.trim() || undefined, page: 1 }))
  }

  useEffect(() => {
    if (error) {
      toast({
        title: "Unable to load applications",
        description: error instanceof ApiError ? error.message : "Please try again.",
        variant: "error",
      })
    }
  }, [error, toast])

  function goToPage(pageNumber: number) {
    setQuery((current) => ({ ...current, page: pageNumber }))
    window.scrollTo({ top: 0, behavior: "smooth" })
  }

  if (loading && !page) return <FullPageLoader />

  return (
    <div className="space-y-6">
      <PageHeader
        title="Review applications"
        description="Filter and review student applications across all programs."
      />

      <div className="grid gap-3 rounded-lg border bg-muted/30 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div className="space-y-1.5">
          <label className="text-xs font-medium text-muted-foreground" htmlFor="status-filter">
            Status
          </label>
          <Select
            value={query.status ?? "all"}
            onValueChange={(value) =>
              setQuery((current) => ({
                ...current,
                status: value && value !== "all" ? (value as ApplicationStatus) : undefined,
                page: 1,
              }))
            }
          >
            <SelectTrigger id="status-filter" className="w-full">
              <SelectValue placeholder="All statuses" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All statuses</SelectItem>
              {STATUS_OPTIONS.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-1.5">
          <label className="text-xs font-medium text-muted-foreground" htmlFor="cycle-filter">
            Program cycle
          </label>
          <Select
            value={query.program_cycle_id ?? "all"}
            onValueChange={(value) =>
              setQuery((current) => ({
                ...current,
                program_cycle_id: value && value !== "all" ? value : undefined,
                page: 1,
              }))
            }
          >
            <SelectTrigger id="cycle-filter" className="w-full">
              <SelectValue placeholder="All cycles" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All cycles</SelectItem>
              {(cycles ?? []).map((cycle) => (
                <SelectItem key={cycle.id} value={String(cycle.id)}>
                  {cycle.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-1.5">
          <label className="text-xs font-medium text-muted-foreground" htmlFor="search">
            Search
          </label>
          <div className="relative">
            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
            <Input
              id="search"
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === "Enter") applySearch()
              }}
              placeholder="Applicant name..."
              className="pl-8"
            />
          </div>
        </div>

        <div className="flex items-end">
          <Button onClick={applySearch} className="w-full">
            Apply filters
          </Button>
        </div>
      </div>

      {page ? (
        <>
          <p className="text-sm text-muted-foreground">
            Showing {page.from ?? 0}–{page.to ?? 0} of {page.total} applications
          </p>

          {page.data.length === 0 ? (
            <EmptyState
              icon={Inbox}
              title="No applications found"
              description="Try adjusting your filters or search term."
            />
          ) : (
            <div className="space-y-3">
              {page.data.map((application) => (
                <Card key={application.id} className="transition-shadow hover:shadow-sm">
                  <CardContent className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0 space-y-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <p className="text-sm font-medium">{application.applicant?.name ?? `Applicant #${application.id}`}</p>
                        <ApplicationStatusBadge status={application.status} label={application.status_label} />
                      </div>
                      <p className="text-xs text-muted-foreground">
                        {application.program_cycle?.program?.name} · {application.program_cycle?.name}
                      </p>
                      <p className="text-xs text-muted-foreground">
                        {application.applicant?.email}
                        {application.submitted_at ? ` · Submitted ${formatDateTime(application.submitted_at)}` : ""}
                      </p>
                      {application.missing_required_documents.length > 0 ? (
                        <p className="text-xs text-amber-600">
                          {application.missing_required_documents.length} missing document
                          {application.missing_required_documents.length === 1 ? "" : "s"}
                        </p>
                      ) : null}
                    </div>
                    <Button
                      nativeButton={false}
                      size="sm"
                      render={<Link to={`/staff/applications/${application.id}`} />}
                      className="shrink-0"
                    >
                      Review
                      <ArrowRight aria-hidden="true" />
                    </Button>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}

          {page.last_page > 1 ? (
            <div className="flex items-center justify-between">
              <Button variant="outline" size="sm" disabled={page.current_page <= 1} onClick={() => goToPage(page.current_page - 1)}>
                Previous
              </Button>
              <p className="text-sm text-muted-foreground">
                Page {page.current_page} of {page.last_page}
              </p>
              <Button variant="outline" size="sm" disabled={page.current_page >= page.last_page} onClick={() => goToPage(page.current_page + 1)}>
                Next
              </Button>
            </div>
          ) : null}
        </>
      ) : null}
    </div>
  )
}
