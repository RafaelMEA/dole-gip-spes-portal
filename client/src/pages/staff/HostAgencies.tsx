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
  createHostAgency,
  fetchHostAgencies,
  type HostAgencyPayload,
} from "@/api/staff"
import { useAsync } from "@/lib/useAsync"
import { ApiError } from "@/lib/api"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from "@/components/ui/dialog"
import { Skeleton } from "@/components/ui/skeleton"
import { EmptyState } from "@/components/EmptyState"
import { PageHeader } from "@/components/PageHeader"
import { useToast } from "@/toast/useToast"
import type { HostAgencyFilters, HostAgencyType } from "@/types/api"
import { formatDate } from "@/lib/format"
import { cn } from "@/lib/utils"

const AGENCY_TYPES: { value: HostAgencyType; label: string }[] = [
  { value: "government", label: "Government" },
  { value: "private", label: "Private" },
  { value: "ngo", label: "NGO" },
  { value: "other", label: "Other" },
]

const emptyForm: HostAgencyPayload = {
  name: "",
  agency_type: "other",
  address: "",
  contact_person: "",
  contact_number: "",
  email: "",
  is_active: true,
}

export function StaffHostAgenciesPage() {
  const { toast } = useToast()
  const [searchParams, setSearchParams] = useSearchParams()

  const [filters, setFilters] = useState<HostAgencyFilters>({
    search: searchParams.get("search") || undefined,
    status: (searchParams.get("status") as HostAgencyFilters["status"]) || "all",
    page: Number(searchParams.get("page")) || 1,
    per_page: 20,
  })

  const [searchInput, setSearchInput] = useState(filters.search || "")
  const [modalOpen, setModalOpen] = useState(false)
  const [form, setForm] = useState<HostAgencyPayload>(emptyForm)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const fetcher = useCallback(() => fetchHostAgencies(filters), [filters])
  const { data: page, loading, error: fetchError, reload } = useAsync(fetcher)

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
    if (filters.page && filters.page > 1) params.set("page", String(filters.page))
    setSearchParams(params, { replace: true })
  }, [filters, setSearchParams])

  function updateFilter<K extends keyof HostAgencyFilters>(key: K, value: HostAgencyFilters[K]) {
    setFilters((current) => ({ ...current, [key]: value, page: 1 }))
  }

  function openCreate() {
    setForm(emptyForm)
    setError(null)
    setModalOpen(true)
  }

  async function handleSave() {
    if (!form.name.trim()) {
      setError("Agency name is required.")
      return
    }
    setSaving(true)
    setError(null)
    try {
      await createHostAgency(form)
      toast({ title: "Host agency created.", variant: "success" })
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

  const agencies = page?.data ?? []
  const totalPages = page?.last_page ?? 1
  const total = page?.total ?? 0
  const currentPage = page?.current_page ?? 1

  return (
    <div className="space-y-6">
      <PageHeader
        title="Host Agencies"
        description="Manage organizations and establishments available for GIP/SPES deployment."
      >
        <Button onClick={openCreate}>
          <Plus className="mr-1.5 size-4" aria-hidden="true" />
          Add Host Agency
        </Button>
      </PageHeader>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
          <Input
            placeholder="Search agencies..."
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            className="pl-9"
          />
        </div>
        <div className="w-full sm:w-40">
          <Select
            value={filters.status ?? "all"}
            onValueChange={(value) => updateFilter("status", value as HostAgencyFilters["status"])}
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
              <th scope="col" className="px-4 py-3">Agency</th>
              <th scope="col" className="hidden px-4 py-3 sm:table-cell">Type</th>
              <th scope="col" className="px-4 py-3">Status</th>
              <th scope="col" className="hidden px-4 py-3 md:table-cell">Created</th>
            </tr>
          </thead>
          <tbody>
            {loading && !page
              ? Array.from({ length: 5 }).map((_, i) => (
                  <tr key={i} className="border-b">
                    <td className="px-4 py-3"><Skeleton className="h-4 w-48" /></td>
                    <td className="hidden px-4 py-3 sm:table-cell"><Skeleton className="h-4 w-20" /></td>
                    <td className="px-4 py-3"><Skeleton className="h-5 w-16 rounded-full" /></td>
                    <td className="hidden px-4 py-3 md:table-cell"><Skeleton className="h-4 w-24" /></td>
                  </tr>
                ))
              : null}

            {!loading && agencies.length === 0 ? (
              <tr>
                <td colSpan={4}>
                  <EmptyState
                    title={filters.search ? "No host agencies match your search." : "No host agencies found."}
                    description={filters.search ? "Try changing your search or filters." : "Add an agency that will host GIP/SPES interns."}
                  >
                    {!filters.search ? (
                      <Button size="sm" onClick={openCreate}>
                        <Plus className="mr-1.5 size-4" aria-hidden="true" />
                        Add Host Agency
                      </Button>
                    ) : null}
                  </EmptyState>
                </td>
              </tr>
            ) : null}

            {!loading && agencies.map((agency) => (
              <tr key={agency.id} className="border-b transition-colors last:border-b-0 hover:bg-muted/20">
                <td className="px-4 py-3">
                  <Link
                    to={`/staff/host-agencies/${agency.id}`}
                    className="font-medium text-foreground hover:underline"
                  >
                    {agency.name}
                  </Link>
                  {agency.contact_person ? (
                    <p className="text-xs text-muted-foreground">{agency.contact_person}</p>
                  ) : null}
                </td>
                <td className="hidden px-4 py-3 text-muted-foreground sm:table-cell">
                  {agency.agency_type_label}
                </td>
                <td className="px-4 py-3">
                  <span
                    className={cn(
                      "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium",
                      agency.is_active
                        ? "bg-emerald-100 text-emerald-700"
                        : "bg-zinc-100 text-zinc-600",
                    )}
                  >
                    {agency.is_active ? "Active" : "Inactive"}
                  </span>
                </td>
                <td className="hidden px-4 py-3 text-muted-foreground md:table-cell">
                  {formatDate(agency.created_at)}
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
            –{Math.min(currentPage * (page?.per_page ?? 20), total)} of {total} agencies
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
            <DialogTitle>Add host agency</DialogTitle>
            <DialogDescription>
              Add a new organization for GIP/SPES deployment.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            {error ? (
              <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {error}
              </p>
            ) : null}
            <div className="space-y-2">
              <Label htmlFor="agency-name">Agency Name *</Label>
              <Input
                id="agency-name"
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="agency-type">Agency Type</Label>
              <Select
                value={form.agency_type ?? "other"}
                onValueChange={(value) => setForm({ ...form, agency_type: value as HostAgencyType })}
              >
                <SelectTrigger id="agency-type">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {AGENCY_TYPES.map((type) => (
                    <SelectItem key={type.value} value={type.value}>{type.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="agency-address">Address</Label>
              <Input
                id="agency-address"
                value={form.address ?? ""}
                onChange={(e) => setForm({ ...form, address: e.target.value })}
              />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="agency-contact">Contact Person</Label>
                <Input
                  id="agency-contact"
                  value={form.contact_person ?? ""}
                  onChange={(e) => setForm({ ...form, contact_person: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="agency-number">Contact Number</Label>
                <Input
                  id="agency-number"
                  value={form.contact_number ?? ""}
                  onChange={(e) => setForm({ ...form, contact_number: e.target.value })}
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="agency-email">Email</Label>
              <Input
                id="agency-email"
                type="email"
                value={form.email ?? ""}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setModalOpen(false)} disabled={saving}>Cancel</Button>
            <Button onClick={handleSave} disabled={saving}>
              {saving ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              Create Agency
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
