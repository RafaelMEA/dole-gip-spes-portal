import { useCallback, useEffect, useState } from "react"
import { Link, useSearchParams } from "react-router-dom"
import {
  ChevronLeft,
  ChevronRight,
  Loader2,
  Plus,
  Search,
} from "lucide-react"
import {
  createDeploymentSlot,
  fetchDeploymentSlots,
  fetchCyclesCatalog,
  fetchHostAgencies,
  fetchDeploymentSitesAll,
  type DeploymentSlotPayload,
} from "@/api/staff"
import { useAsync } from "@/lib/useAsync"
import { ApiError } from "@/lib/api"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from "@/components/ui/dialog"
import { Skeleton } from "@/components/ui/skeleton"
import { EmptyState } from "@/components/EmptyState"
import { PageHeader } from "@/components/PageHeader"
import { useToast } from "@/toast/useToast"
import type { DeploymentSlotFilters } from "@/types/api"
import { cn } from "@/lib/utils"

const emptyForm: DeploymentSlotPayload = {
  program_cycle_id: 0,
  deployment_site_id: 0,
  title: "",
  description: "",
  capacity: 1,
}

export function StaffDeploymentSlotsPage() {
  const { toast } = useToast()
  const [searchParams, setSearchParams] = useSearchParams()

  const [filters, setFilters] = useState<DeploymentSlotFilters>({
    search: searchParams.get("search") || undefined,
    program_cycle_id: searchParams.get("program_cycle_id") ? Number(searchParams.get("program_cycle_id")) : undefined,
    host_agency_id: searchParams.get("host_agency_id") ? Number(searchParams.get("host_agency_id")) : undefined,
    deployment_site_id: searchParams.get("deployment_site_id") ? Number(searchParams.get("deployment_site_id")) : undefined,
    status: (searchParams.get("status") as DeploymentSlotFilters["status"]) || "all",
    page: Number(searchParams.get("page")) || 1,
    per_page: 20,
  })

  const [searchInput, setSearchInput] = useState(filters.search || "")
  const [modalOpen, setModalOpen] = useState(false)
  const [form, setForm] = useState<DeploymentSlotPayload>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [formAgencyId, setFormAgencyId] = useState<number>(0)

  const fetcher = useCallback(() => fetchDeploymentSlots(filters), [filters])
  const { data: page, loading, error: fetchError, reload } = useAsync(fetcher)

  const fetcherCycles = useCallback(() => fetchCyclesCatalog(), [])
  const { data: cycles } = useAsync(fetcherCycles)

  const fetcherAgencies = useCallback(() => fetchHostAgencies({ per_page: 100 }), [])
  const { data: agenciesPage } = useAsync(fetcherAgencies)
  const agencies = agenciesPage?.data ?? []

  const fetcherSites = useCallback(() => fetchDeploymentSitesAll(), [])
  const { data: allSites } = useAsync(fetcherSites)

  const filteredSites = formAgencyId
    ? (allSites ?? []).filter((s) => s.host_agency_id === formAgencyId)
    : (allSites ?? [])

  useEffect(() => {
    const timer = setTimeout(() => {
      setFilters((current) => ({
        ...current,
        search: searchInput.trim() || undefined,
        page: 1,
      }))
    }, 400)
    return () => clearTimeout(timer)
  }, [searchInput])

  useEffect(() => {
    const params = new URLSearchParams()
    if (filters.search) params.set("search", filters.search)
    if (filters.program_cycle_id) params.set("program_cycle_id", String(filters.program_cycle_id))
    if (filters.host_agency_id) params.set("host_agency_id", String(filters.host_agency_id))
    if (filters.deployment_site_id) params.set("deployment_site_id", String(filters.deployment_site_id))
    if (filters.status && filters.status !== "all") params.set("status", filters.status)
    if (filters.page && filters.page > 1) params.set("page", String(filters.page))
    setSearchParams(params, { replace: true })
  }, [filters, setSearchParams])

  function updateFilter<K extends keyof DeploymentSlotFilters>(key: K, value: DeploymentSlotFilters[K]) {
    setFilters((current) => ({ ...current, [key]: value, page: 1 }))
  }

  function openCreate() {
    setForm(emptyForm)
    setFormAgencyId(0)
    setError(null)
    setModalOpen(true)
  }

  async function handleSave() {
    if (!form.title.trim()) {
      setError("Position/title is required.")
      return
    }
    if (!form.program_cycle_id) {
      setError("Program cycle is required.")
      return
    }
    if (!form.deployment_site_id) {
      setError("Deployment site is required.")
      return
    }
    if (!form.capacity || form.capacity < 1) {
      setError("Capacity must be at least 1.")
      return
    }
    setSaving(true)
    setError(null)
    try {
      await createDeploymentSlot(form)
      toast({ title: "Deployment slot created.", variant: "success" })
      setModalOpen(false)
      reload()
    } catch (err) {
      const message = err instanceof ApiError ? err.message : "An unexpected error occurred."
      setError(message)
      toast({ title: "Error", description: message, variant: "error" })
    } finally {
      setSaving(false)
    }
  }

  const slots = page?.data ?? []
  const totalPages = page?.last_page ?? 1
  const total = page?.total ?? 0
  const currentPage = page?.current_page ?? 1

  return (
    <div className="space-y-6">
      <PageHeader
        title="Deployment Slots"
        description="Manage available positions and capacity for GIP/SPES deployment."
      >
        <Button onClick={openCreate}>
          <Plus className="mr-1.5 size-4" aria-hidden="true" />
          Add Slot
        </Button>
      </PageHeader>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
          <Input
            placeholder="Search slots..."
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            className="pl-9"
          />
        </div>
        <div className="w-full sm:w-48">
          <Select
            value={filters.program_cycle_id ? String(filters.program_cycle_id) : "all"}
            onValueChange={(value) => updateFilter("program_cycle_id", value === "all" ? undefined : Number(value))}
          >
            <SelectTrigger>
              <SelectValue placeholder="All cycles" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All cycles</SelectItem>
              {(cycles ?? []).map((cycle) => (
                <SelectItem key={cycle.id} value={String(cycle.id)}>{cycle.name}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="w-full sm:w-48">
          <Select
            value={filters.host_agency_id ? String(filters.host_agency_id) : "all"}
            onValueChange={(value) => {
              const agencyId = value === "all" ? undefined : Number(value)
              updateFilter("host_agency_id", agencyId)
              updateFilter("deployment_site_id", undefined)
            }}
          >
            <SelectTrigger>
              <SelectValue placeholder="All agencies" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All agencies</SelectItem>
              {agencies.map((agency) => (
                <SelectItem key={agency.id} value={String(agency.id)}>{agency.name}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="w-full sm:w-48">
          <Select
            value={filters.deployment_site_id ? String(filters.deployment_site_id) : "all"}
            onValueChange={(value) => updateFilter("deployment_site_id", value === "all" ? undefined : Number(value))}
          >
            <SelectTrigger>
              <SelectValue placeholder="All sites" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All sites</SelectItem>
              {(allSites ?? [])
                .filter((s) => !filters.host_agency_id || s.host_agency_id === filters.host_agency_id)
                .map((site) => (
                  <SelectItem key={site.id} value={String(site.id)}>{site.name}</SelectItem>
                ))}
            </SelectContent>
          </Select>
        </div>
        <div className="w-full sm:w-40">
          <Select
            value={filters.status ?? "all"}
            onValueChange={(value) => updateFilter("status", value as DeploymentSlotFilters["status"])}
          >
            <SelectTrigger>
              <SelectValue placeholder="All statuses" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All statuses</SelectItem>
              <SelectItem value="active">Active</SelectItem>
              <SelectItem value="inactive">Inactive</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      {fetchError ? (
        <div className="rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
          {fetchError.message}
        </div>
      ) : null}

      <div className="overflow-x-auto rounded-lg border">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b bg-muted/30 text-left text-xs font-medium uppercase tracking-wide text-muted-foreground">
              <th scope="col" className="px-4 py-3">Position</th>
              <th scope="col" className="hidden px-4 py-3 sm:table-cell">Site</th>
              <th scope="col" className="hidden px-4 py-3 md:table-cell">Cycle</th>
              <th scope="col" className="px-4 py-3 text-center">Capacity</th>
              <th scope="col" className="px-4 py-3 text-center">Available</th>
              <th scope="col" className="px-4 py-3">Status</th>
            </tr>
          </thead>
          <tbody>
            {loading && !page
              ? Array.from({ length: 5 }).map((_, i) => (
                  <tr key={i} className="border-b">
                    <td className="px-4 py-3"><Skeleton className="h-4 w-48" /></td>
                    <td className="hidden px-4 py-3 sm:table-cell"><Skeleton className="h-4 w-32" /></td>
                    <td className="hidden px-4 py-3 md:table-cell"><Skeleton className="h-4 w-32" /></td>
                    <td className="px-4 py-3 text-center"><Skeleton className="h-4 w-8 mx-auto" /></td>
                    <td className="px-4 py-3 text-center"><Skeleton className="h-4 w-8 mx-auto" /></td>
                    <td className="px-4 py-3"><Skeleton className="h-5 w-16 rounded-full" /></td>
                  </tr>
                ))
              : null}

            {!loading && slots.length === 0 ? (
              <tr>
                <td colSpan={6}>
                  <EmptyState
                    title={
                      filters.search || filters.program_cycle_id || filters.host_agency_id || filters.deployment_site_id || (filters.status && filters.status !== "all")
                        ? "No deployment slots match your search."
                        : "No deployment slots found."
                    }
                    description={
                      filters.search || filters.program_cycle_id || filters.host_agency_id || filters.deployment_site_id || (filters.status && filters.status !== "all")
                        ? "Try changing your search or filters."
                        : "Add a deployment slot to configure capacity."
                    }
                  >
                    {!filters.search && !filters.program_cycle_id && !filters.host_agency_id && !filters.deployment_site_id ? (
                      <Button size="sm" onClick={openCreate}>
                        <Plus className="mr-1.5 size-4" aria-hidden="true" />
                        Add Slot
                      </Button>
                    ) : null}
                  </EmptyState>
                </td>
              </tr>
            ) : null}

            {!loading && slots.map((slot) => (
              <tr key={slot.id} className="border-b transition-colors last:border-b-0 hover:bg-muted/20">
                <td className="px-4 py-3">
                  <Link
                    to={`/staff/deployment-slots/${slot.id}`}
                    className="font-medium text-foreground hover:underline"
                  >
                    {slot.title}
                  </Link>
                  {slot.description ? (
                    <p className="text-xs text-muted-foreground line-clamp-1">{slot.description}</p>
                  ) : null}
                </td>
                <td className="hidden px-4 py-3 text-muted-foreground sm:table-cell">
                  {slot.deployment_site?.name ?? "—"}
                </td>
                <td className="hidden px-4 py-3 text-muted-foreground md:table-cell">
                  {slot.program_cycle?.name ?? "—"}
                </td>
                <td className="px-4 py-3 text-center font-medium">{slot.capacity}</td>
                <td className="px-4 py-3 text-center">
                  <span
                    className={cn(
                      "font-medium",
                      slot.available_count === 0 ? "text-destructive" : "text-emerald-600",
                    )}
                  >
                    {slot.available_count}
                  </span>
                </td>
                <td className="px-4 py-3">
                  <span
                    className={cn(
                      "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium",
                      slot.status === "active"
                        ? "bg-emerald-100 text-emerald-700"
                        : "bg-zinc-100 text-zinc-600",
                    )}
                  >
                    {slot.status_label}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {total > 0 ? (
        <div className="flex items-center justify-between text-sm text-muted-foreground">
          <p>
            Showing {((currentPage - 1) * (page?.per_page ?? 20)) + 1}
            –{Math.min(currentPage * (page?.per_page ?? 20), total)} of {total} slots
          </p>
          <div className="flex items-center gap-1">
            <Button
              variant="outline"
              size="sm"
              disabled={currentPage <= 1}
              onClick={() => setFilters((c) => ({ ...c, page: currentPage - 1 }))}
            >
              <ChevronLeft className="size-4" aria-hidden="true" />
              Previous
            </Button>
            <span className="px-2 text-xs">Page {currentPage} of {totalPages}</span>
            <Button
              variant="outline"
              size="sm"
              disabled={currentPage >= totalPages}
              onClick={() => setFilters((c) => ({ ...c, page: currentPage + 1 }))}
            >
              Next
              <ChevronRight className="size-4" aria-hidden="true" />
            </Button>
          </div>
        </div>
      ) : null}

      <Dialog open={modalOpen} onOpenChange={(open) => !open && setModalOpen(false)}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Add deployment slot</DialogTitle>
            <DialogDescription>
              Define a position and capacity at a deployment site for a program cycle.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            {error ? (
              <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {error}
              </p>
            ) : null}
            <div className="space-y-2">
              <Label htmlFor="slot-cycle">Program Cycle *</Label>
              <Select
                value={form.program_cycle_id ? String(form.program_cycle_id) : ""}
                onValueChange={(value) => setForm({ ...form, program_cycle_id: Number(value) })}
              >
                <SelectTrigger id="slot-cycle">
                  <SelectValue placeholder="Select program cycle" />
                </SelectTrigger>
                <SelectContent>
                  {(cycles ?? []).map((cycle) => (
                    <SelectItem key={cycle.id} value={String(cycle.id)}>{cycle.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="slot-agency">Host Agency</Label>
              <Select
                value={formAgencyId ? String(formAgencyId) : ""}
                onValueChange={(value) => {
                  const id = value ? Number(value) : 0
                  setFormAgencyId(id)
                  if (id) setForm({ ...form, deployment_site_id: 0 })
                }}
              >
                <SelectTrigger id="slot-agency">
                  <SelectValue placeholder="Select host agency" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="0">All agencies</SelectItem>
                  {agencies.map((agency) => (
                    <SelectItem key={agency.id} value={String(agency.id)}>{agency.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="slot-site">Deployment Site *</Label>
              <Select
                value={form.deployment_site_id ? String(form.deployment_site_id) : ""}
                onValueChange={(value) => setForm({ ...form, deployment_site_id: Number(value) })}
              >
                <SelectTrigger id="slot-site">
                  <SelectValue placeholder={formAgencyId ? "Select site" : "Select an agency first"} />
                </SelectTrigger>
                <SelectContent>
                  {filteredSites.map((site) => (
                    <SelectItem key={site.id} value={String(site.id)}>{site.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="slot-title">Position / Slot Title *</Label>
              <Input
                id="slot-title"
                value={form.title}
                onChange={(e) => setForm({ ...form, title: e.target.value })}
                placeholder="e.g., Administrative Assistant"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="slot-description">Description</Label>
              <Textarea
                id="slot-description"
                value={form.description ?? ""}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="slot-capacity">Capacity *</Label>
              <Input
                id="slot-capacity"
                type="number"
                min={1}
                value={form.capacity}
                onChange={(e) => setForm({ ...form, capacity: Number(e.target.value) })}
              />
              <p className="text-xs text-muted-foreground">Maximum number of students for this position.</p>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setModalOpen(false)} disabled={saving}>Cancel</Button>
            <Button onClick={handleSave} disabled={saving}>
              {saving ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              Create Slot
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
