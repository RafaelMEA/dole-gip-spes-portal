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
  createDeploymentSite,
  fetchDeploymentSites,
  fetchHostAgencies,
  type DeploymentSitePayload,
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
import type { DeploymentSiteFilters } from "@/types/api"
import { formatDate } from "@/lib/format"
import { cn } from "@/lib/utils"

const emptyForm: DeploymentSitePayload = {
  host_agency_id: 0,
  name: "",
  address: "",
  city: "",
  region: "",
  contact_person: "",
  contact_number: "",
  email: "",
  description: "",
  is_active: true,
}

export function StaffDeploymentSitesPage() {
  const { toast } = useToast()
  const [searchParams, setSearchParams] = useSearchParams()

  const [filters, setFilters] = useState<DeploymentSiteFilters>({
    search: searchParams.get("search") || undefined,
    status: (searchParams.get("status") as DeploymentSiteFilters["status"]) || "all",
    host_agency_id: searchParams.get("host_agency_id") ? Number(searchParams.get("host_agency_id")) : undefined,
    page: Number(searchParams.get("page")) || 1,
    per_page: 20,
  })

  const [searchInput, setSearchInput] = useState(filters.search || "")
  const [modalOpen, setModalOpen] = useState(false)
  const [form, setForm] = useState<DeploymentSitePayload>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const fetcher = useCallback(() => fetchDeploymentSites(filters), [filters])
  const { data: page, loading, error: fetchError, reload } = useAsync(fetcher)

  const fetcherAgencies = useCallback(() => fetchHostAgencies({ per_page: 100 }), [])
  const { data: agenciesPage } = useAsync(fetcherAgencies)
  const agencies = agenciesPage?.data ?? []

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
    if (filters.status && filters.status !== "all") params.set("status", filters.status)
    if (filters.host_agency_id) params.set("host_agency_id", String(filters.host_agency_id))
    if (filters.page && filters.page > 1) params.set("page", String(filters.page))
    setSearchParams(params, { replace: true })
  }, [filters, setSearchParams])

  function updateFilter<K extends keyof DeploymentSiteFilters>(key: K, value: DeploymentSiteFilters[K]) {
    setFilters((current) => ({ ...current, [key]: value, page: 1 }))
  }

  function openCreate() {
    setForm(emptyForm)
    setError(null)
    setModalOpen(true)
  }

  async function handleSave() {
    if (!form.name.trim()) {
      setError("Site name is required.")
      return
    }
    if (!form.host_agency_id) {
      setError("Host agency is required.")
      return
    }
    setSaving(true)
    setError(null)
    try {
      await createDeploymentSite(form)
      toast({ title: "Deployment site created.", variant: "success" })
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

  const sites = page?.data ?? []
  const totalPages = page?.last_page ?? 1
  const total = page?.total ?? 0
  const currentPage = page?.current_page ?? 1

  return (
    <div className="space-y-6">
      <PageHeader
        title="Deployment Sites"
        description="Manage locations where GIP/SPES participants may eventually be deployed."
      >
        <Button onClick={openCreate}>
          <Plus className="mr-1.5 size-4" aria-hidden="true" />
          Add Site
        </Button>
      </PageHeader>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
          <Input
            placeholder="Search deployment sites..."
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            className="pl-9"
          />
        </div>
        <div className="w-full sm:w-48">
          <Select
            value={filters.host_agency_id ? String(filters.host_agency_id) : "all"}
            onValueChange={(value) => updateFilter("host_agency_id", value === "all" ? undefined : Number(value))}
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
        <div className="w-full sm:w-40">
          <Select
            value={filters.status ?? "all"}
            onValueChange={(value) => updateFilter("status", value as DeploymentSiteFilters["status"])}
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
              <th scope="col" className="px-4 py-3">Site</th>
              <th scope="col" className="hidden px-4 py-3 sm:table-cell">Host Agency</th>
              <th scope="col" className="px-4 py-3">Status</th>
              <th scope="col" className="hidden px-4 py-3 md:table-cell">Created</th>
            </tr>
          </thead>
          <tbody>
            {loading && !page
              ? Array.from({ length: 5 }).map((_, i) => (
                  <tr key={i} className="border-b">
                    <td className="px-4 py-3"><Skeleton className="h-4 w-48" /></td>
                    <td className="hidden px-4 py-3 sm:table-cell"><Skeleton className="h-4 w-32" /></td>
                    <td className="px-4 py-3"><Skeleton className="h-5 w-16 rounded-full" /></td>
                    <td className="hidden px-4 py-3 md:table-cell"><Skeleton className="h-4 w-24" /></td>
                  </tr>
                ))
              : null}

            {!loading && sites.length === 0 ? (
              <tr>
                <td colSpan={4}>
                  <EmptyState
                    title={filters.search || filters.host_agency_id || (filters.status && filters.status !== "all")
                      ? "No deployment sites match your search."
                      : "No deployment sites found."}
                    description={filters.search || filters.host_agency_id || (filters.status && filters.status !== "all")
                      ? "Try changing your search or filters."
                      : "Add a deployment site under a host agency."}
                  >
                    {!filters.search && !filters.host_agency_id ? (
                      <Button size="sm" onClick={openCreate}>
                        <Plus className="mr-1.5 size-4" aria-hidden="true" />
                        Add Site
                      </Button>
                    ) : null}
                  </EmptyState>
                </td>
              </tr>
            ) : null}

            {!loading && sites.map((site) => (
              <tr key={site.id} className="border-b transition-colors last:border-b-0 hover:bg-muted/20">
                <td className="px-4 py-3">
                  <Link
                    to={`/staff/deployment-sites/${site.id}`}
                    className="font-medium text-foreground hover:underline"
                  >
                    {site.name}
                  </Link>
                  {site.contact_person ? (
                    <p className="text-xs text-muted-foreground">{site.contact_person}</p>
                  ) : null}
                </td>
                <td className="hidden px-4 py-3 text-muted-foreground sm:table-cell">
                  {site.host_agency?.name ?? "—"}
                </td>
                <td className="px-4 py-3">
                  <span
                    className={cn(
                      "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium",
                      site.is_active
                        ? "bg-emerald-100 text-emerald-700"
                        : "bg-zinc-100 text-zinc-600",
                    )}
                  >
                    {site.is_active ? "Active" : "Inactive"}
                  </span>
                </td>
                <td className="hidden px-4 py-3 text-muted-foreground md:table-cell">
                  {formatDate(site.created_at)}
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
            –{Math.min(currentPage * (page?.per_page ?? 20), total)} of {total} sites
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
            <DialogTitle>Add deployment site</DialogTitle>
            <DialogDescription>
              Add a new location under a host agency for GIP/SPES deployment.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            {error ? (
              <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {error}
              </p>
            ) : null}
            <div className="space-y-2">
              <Label htmlFor="site-agency">Host Agency *</Label>
              <Select
                value={form.host_agency_id ? String(form.host_agency_id) : ""}
                onValueChange={(value) => setForm({ ...form, host_agency_id: Number(value) })}
              >
                <SelectTrigger id="site-agency">
                  <SelectValue placeholder="Select host agency" />
                </SelectTrigger>
                <SelectContent>
                  {agencies.map((agency) => (
                    <SelectItem key={agency.id} value={String(agency.id)}>{agency.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="site-name">Site Name *</Label>
              <Input
                id="site-name"
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="site-address">Address</Label>
              <Input
                id="site-address"
                value={form.address ?? ""}
                onChange={(e) => setForm({ ...form, address: e.target.value })}
              />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="site-city">City</Label>
                <Input
                  id="site-city"
                  value={form.city ?? ""}
                  onChange={(e) => setForm({ ...form, city: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="site-region">Region</Label>
                <Input
                  id="site-region"
                  value={form.region ?? ""}
                  onChange={(e) => setForm({ ...form, region: e.target.value })}
                />
              </div>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="site-contact">Contact Person</Label>
                <Input
                  id="site-contact"
                  value={form.contact_person ?? ""}
                  onChange={(e) => setForm({ ...form, contact_person: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="site-number">Contact Number</Label>
                <Input
                  id="site-number"
                  value={form.contact_number ?? ""}
                  onChange={(e) => setForm({ ...form, contact_number: e.target.value })}
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="site-email">Email</Label>
              <Input
                id="site-email"
                type="email"
                value={form.email ?? ""}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="site-description">Description</Label>
              <Textarea
                id="site-description"
                value={form.description ?? ""}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setModalOpen(false)} disabled={saving}>Cancel</Button>
            <Button onClick={handleSave} disabled={saving}>
              {saving ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              Create Site
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
