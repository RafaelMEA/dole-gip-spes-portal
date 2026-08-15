import { useCallback, useEffect, useMemo, useState } from "react"
import { Link } from "react-router-dom"
import { ArrowRight, ChevronLeft, ChevronRight, Inbox, Search } from "lucide-react"
import { fetchCyclesCatalog, fetchReviewQueue } from "@/api/staff"
import { useAsync } from "@/lib/useAsync"
import { ApiError } from "@/lib/api"
import { formatDateTime } from "@/lib/format"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Skeleton } from "@/components/ui/skeleton"
import { PageHeader } from "@/components/PageHeader"
import { EmptyState } from "@/components/EmptyState"
import { ApplicationStatusBadge } from "@/components/StatusBadge"
import type {
  ApplicationSortField,
  ApplicationStatus,
  SortDirection,
  StaffApplicationFilters,
} from "@/types/api"

const STATUS_OPTIONS: { value: ApplicationStatus; label: string }[] = [
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

const SORT_OPTIONS: { value: string; sort: ApplicationSortField; direction: SortDirection; label: string }[] = [
  { value: "submitted_at:desc", sort: "submitted_at", direction: "desc", label: "Newest submitted first" },
  { value: "submitted_at:asc", sort: "submitted_at", direction: "asc", label: "Oldest submitted first" },
  { value: "created_at:desc", sort: "created_at", direction: "desc", label: "Newest created first" },
  { value: "updated_at:desc", sort: "updated_at", direction: "desc", label: "Recently updated first" },
]

const PAGE_SIZE_OPTIONS = [10, 20, 50]

function paginationPages(current: number, last: number): (number | "ellipsis")[] {
  if (last <= 7) {
    return Array.from({ length: last }, (_, index) => index + 1)
  }
  const pages: (number | "ellipsis")[] = [1]
  const start = Math.max(2, current - 1)
  const end = Math.min(last - 1, current + 1)
  if (start > 2) pages.push("ellipsis")
  for (let index = start; index <= end; index++) pages.push(index)
  if (end < last - 1) pages.push("ellipsis")
  pages.push(last)
  return pages
}

function ErrorBanner({ error }: { error: ApiError }) {
  return (
    <p
      role="alert"
      className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
    >
      {error.status === 0
        ? "Unable to reach the server. Please check your connection and try again."
        : error.status === 422
          ? "One of the filters is invalid. Please review your search and filters."
          : error.message}
    </p>
  )
}

function FilterField({ label, htmlFor, children }: { label: string; htmlFor: string; children: React.ReactNode }) {
  return (
    <div className="space-y-1.5">
      <label className="text-xs font-medium text-muted-foreground" htmlFor={htmlFor}>
        {label}
      </label>
      {children}
    </div>
  )
}

export function StaffReviewQueuePage() {
  const [query, setQuery] = useState<StaffApplicationFilters>({ status: "submitted" })
  const [searchInput, setSearchInput] = useState("")

  const { data: cycles } = useAsync(fetchCyclesCatalog)

  const fetcher = useCallback(() => fetchReviewQueue(query), [query])
  const { data: page, loading, error } = useAsync(fetcher)

  const programs = useMemo(() => {
    const unique = new Map<number, { id: number; name: string }>()
    for (const cycle of cycles ?? []) {
      if (cycle.program) unique.set(cycle.program.id, { id: cycle.program.id, name: cycle.program.name })
    }
    return [...unique.values()].sort((a, b) => a.name.localeCompare(b.name))
  }, [cycles])

  useEffect(() => {
    const timer = setTimeout(() => {
      const nextSearch = searchInput.trim()
      setQuery((current) => {
        const currentSearch = current.search ?? ""
        if (currentSearch === nextSearch) return current
        return { ...current, search: nextSearch || undefined, page: 1 }
      })
    }, 400)
    return () => clearTimeout(timer)
  }, [searchInput])

  function updateFilter<K extends keyof StaffApplicationFilters>(key: K, value: StaffApplicationFilters[K]) {
    setQuery((current) => ({ ...current, [key]: value, page: 1 }))
  }

  function goToPage(pageNumber: number) {
    setQuery((current) => ({ ...current, page: pageNumber }))
    window.scrollTo({ top: 0, behavior: "smooth" })
  }

  const selectedSort = SORT_OPTIONS.find(
    (option) => option.sort === (query.sort ?? "submitted_at") && option.direction === (query.direction ?? "desc"),
  )?.value

  return (
    <div className="space-y-6">
      <PageHeader
        title="Review applications"
        description="Search, filter, and review student applications across all programs."
      />

      <div className="grid gap-3 rounded-lg border bg-muted/30 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <FilterField label="Status" htmlFor="status-filter">
          <Select
            value={query.status ?? "all"}
            onValueChange={(value) =>
              updateFilter("status", value && value !== "all" ? (value as ApplicationStatus) : "all")
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
        </FilterField>

        <FilterField label="Program" htmlFor="program-filter">
          <Select
            value={query.program_id !== undefined ? String(query.program_id) : "all"}
            onValueChange={(value) =>
              updateFilter("program_id", value && value !== "all" ? Number(value) : undefined)
            }
          >
            <SelectTrigger id="program-filter" className="w-full">
              <SelectValue placeholder="All programs" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All programs</SelectItem>
              {programs.map((program) => (
                <SelectItem key={program.id} value={String(program.id)}>
                  {program.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </FilterField>

        <FilterField label="Program cycle" htmlFor="cycle-filter">
          <Select
            value={query.program_cycle_id !== undefined ? String(query.program_cycle_id) : "all"}
            onValueChange={(value) =>
              updateFilter("program_cycle_id", value && value !== "all" ? Number(value) : undefined)
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
        </FilterField>

        <FilterField label="Search" htmlFor="search">
          <div className="relative">
            <Search
              className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
              aria-hidden="true"
            />
            <Input
              id="search"
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder="Name, email, or ID..."
              className="pl-8"
            />
          </div>
        </FilterField>

        <FilterField label="Submitted from" htmlFor="submitted-from">
          <Input
            id="submitted-from"
            type="date"
            value={query.submitted_from ?? ""}
            onChange={(event) => updateFilter("submitted_from", event.target.value || undefined)}
          />
        </FilterField>

        <FilterField label="Submitted to" htmlFor="submitted-to">
          <Input
            id="submitted-to"
            type="date"
            value={query.submitted_to ?? ""}
            onChange={(event) => updateFilter("submitted_to", event.target.value || undefined)}
          />
        </FilterField>

        <FilterField label="Sort" htmlFor="sort-filter">
          <Select
            value={selectedSort ?? "submitted_at:desc"}
            onValueChange={(value) => {
              const option = SORT_OPTIONS.find((item) => item.value === value)
              if (!option) return
              updateFilter("sort", option.sort)
              updateFilter("direction", option.direction)
            }}
          >
            <SelectTrigger id="sort-filter" className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {SORT_OPTIONS.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </FilterField>

        <FilterField label="Rows per page" htmlFor="per-page-filter">
          <Select
            value={String(query.per_page ?? 20)}
            onValueChange={(value) => updateFilter("per_page", Number(value))}
          >
            <SelectTrigger id="per-page-filter" className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {PAGE_SIZE_OPTIONS.map((size) => (
                <SelectItem key={size} value={String(size)}>
                  {size} per page
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </FilterField>
      </div>

      {error ? (
        <div className="flex items-start justify-between gap-3">
          <ErrorBanner error={error} />
        </div>
      ) : null}

      <div className="space-y-3">
        <p className="text-sm text-muted-foreground">
          {error
            ? "Unable to load applications."
            : page
              ? `Showing ${page.from ?? 0}–${page.to ?? 0} of ${page.total} applications`
              : "Loading applications..."}
        </p>

        <div className="overflow-x-auto rounded-lg border">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-muted/30 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">
                <th scope="col" className="px-4 py-3">
                  Applicant
                </th>
                <th scope="col" className="px-4 py-3">
                  Program / Cycle
                </th>
                <th scope="col" className="px-4 py-3">
                  Status
                </th>
                <th scope="col" className="px-4 py-3">
                  Submitted
                </th>
                <th scope="col" className="px-4 py-3 text-right">
                  Action
                </th>
              </tr>
            </thead>
            <tbody>
              {loading && !page
                ? Array.from({ length: 5 }, (_, index) => (
                    <tr key={`skeleton-${index}`} className="border-b last:border-b-0">
                      <td className="px-4 py-3">
                        <Skeleton className="h-4 w-40" />
                        <Skeleton className="mt-1.5 h-3 w-28" />
                      </td>
                      <td className="px-4 py-3">
                        <Skeleton className="h-4 w-32" />
                      </td>
                      <td className="px-4 py-3">
                        <Skeleton className="h-5 w-24 rounded-full" />
                      </td>
                      <td className="px-4 py-3">
                        <Skeleton className="h-4 w-24" />
                      </td>
                      <td className="px-4 py-3">
                        <Skeleton className="ml-auto h-8 w-20" />
                      </td>
                    </tr>
                  ))
                : null}

              {!loading && page && page.data.length === 0 ? (
                <tr>
                  <td colSpan={5} className="px-4 py-8">
                    <EmptyState
                      icon={Inbox}
                      title="No applications found"
                      description="Try adjusting your search term or filters."
                    />
                  </td>
                </tr>
              ) : null}

              {loading && page
                ? Array.from({ length: page.data.length }, (_, index) => (
                    <tr key={`refresh-${index}`} className="border-b last:border-b-0">
                      <td className="px-4 py-3">
                        <Skeleton className="h-4 w-40" />
                        <Skeleton className="mt-1.5 h-3 w-28" />
                      </td>
                      <td className="px-4 py-3">
                        <Skeleton className="h-4 w-32" />
                      </td>
                      <td className="px-4 py-3">
                        <Skeleton className="h-5 w-24 rounded-full" />
                      </td>
                      <td className="px-4 py-3">
                        <Skeleton className="h-4 w-24" />
                      </td>
                      <td className="px-4 py-3">
                        <Skeleton className="ml-auto h-8 w-20" />
                      </td>
                    </tr>
                  ))
                : null}

              {!loading && page
                ? page.data.map((application) => (
                    <tr key={application.id} className="border-b transition-colors last:border-b-0 hover:bg-muted/20">
                      <td className="px-4 py-3">
                        <p className="font-medium text-foreground">
                          {application.applicant?.name ?? `Applicant #${application.id}`}
                        </p>
                        <p className="text-xs text-muted-foreground">{application.applicant?.email ?? "—"}</p>
                      </td>
                      <td className="px-4 py-3">
                        <p className="text-foreground">{application.program_cycle?.program?.name ?? "—"}</p>
                        <p className="text-xs text-muted-foreground">{application.program_cycle?.name ?? "—"}</p>
                      </td>
                      <td className="px-4 py-3">
                        <ApplicationStatusBadge status={application.status} label={application.status_label} />
                      </td>
                      <td className="px-4 py-3 text-muted-foreground">
                        {formatDateTime(application.submitted_at ?? application.created_at)}
                      </td>
                      <td className="px-4 py-3 text-right">
                        <Button
                          nativeButton={false}
                          size="sm"
                          variant="outline"
                          render={<Link to={`/staff/applications/${application.id}`} />}
                        >
                          Review
                          <ArrowRight aria-hidden="true" />
                        </Button>
                      </td>
                    </tr>
                  ))
                : null}
            </tbody>
          </table>
        </div>

        {page && page.last_page > 1 ? (
          <div className="flex flex-wrap items-center justify-between gap-3">
            <Button
              variant="outline"
              size="sm"
              disabled={page.current_page <= 1}
              onClick={() => goToPage(page.current_page - 1)}
            >
              <ChevronLeft aria-hidden="true" />
              Previous
            </Button>
            <div className="flex flex-wrap items-center gap-1">
              {paginationPages(page.current_page, page.last_page).map((item, index) =>
                item === "ellipsis" ? (
                  <span key={`ellipsis-${index}`} className="px-1 text-sm text-muted-foreground">
                    …
                  </span>
                ) : (
                  <Button
                    key={item}
                    variant={item === page.current_page ? "default" : "outline"}
                    size="sm"
                    className="min-w-8"
                    onClick={() => goToPage(item)}
                  >
                    {item}
                  </Button>
                ),
              )}
            </div>
            <Button
              variant="outline"
              size="sm"
              disabled={page.current_page >= page.last_page}
              onClick={() => goToPage(page.current_page + 1)}
            >
              Next
              <ChevronRight aria-hidden="true" />
            </Button>
          </div>
        ) : null}
      </div>
    </div>
  )
}
