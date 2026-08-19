import { useCallback, useState } from "react"
import { useNavigate, useParams } from "react-router-dom"
import {
  ArrowLeft,
  Building,
  Calendar,
  Edit,
  Loader2,
  MapPin,
  ToggleLeft,
  ToggleRight,
  Users,
} from "lucide-react"
import {
  fetchDeploymentSlot,
  fetchHostAgencies,
  fetchDeploymentSitesAll,
  updateDeploymentSlot,
  updateDeploymentSlotStatus,
  type DeploymentSlotPayload,
} from "@/api/staff"
import { useAsync } from "@/lib/useAsync"
import { ApiError } from "@/lib/api"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from "@/components/ui/dialog"
import { ConfirmDialog } from "@/components/ConfirmDialog"
import { PageHeader } from "@/components/PageHeader"
import { FullPageLoader } from "@/components/FullPageLoader"
import { useToast } from "@/toast/useToast"
import { formatDate } from "@/lib/format"
import { cn } from "@/lib/utils"

export function StaffDeploymentSlotDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { toast } = useToast()

  const fetcher = useCallback(() => fetchDeploymentSlot(Number(id)), [id])
  const { data: slot, loading, error, reload } = useAsync(fetcher)

  const fetcherAgencies = useCallback(() => fetchHostAgencies({ per_page: 100 }), [])
  const { data: agenciesPage } = useAsync(fetcherAgencies)
  const agencies = agenciesPage?.data ?? []

  const fetcherSites = useCallback(() => fetchDeploymentSitesAll(), [])
  const { data: allSites } = useAsync(fetcherSites)

  const [editOpen, setEditOpen] = useState(false)
  const [form, setForm] = useState<Partial<DeploymentSlotPayload>>({})
  const [formAgencyId, setFormAgencyId] = useState<number>(0)
  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState<string | null>(null)

  const [statusDialogOpen, setStatusDialogOpen] = useState(false)
  const [toggling, setToggling] = useState(false)

  const filteredSites = formAgencyId
    ? (allSites ?? []).filter((s) => s.host_agency_id === formAgencyId)
    : (allSites ?? [])

  function openEdit() {
    if (!slot) return
    const siteAgencyId = slot.deployment_site?.host_agency_id ?? 0
    setFormAgencyId(siteAgencyId)
    setForm({
      deployment_site_id: slot.deployment_site_id,
      title: slot.title,
      description: slot.description ?? "",
      capacity: slot.capacity,
    })
    setSaveError(null)
    setEditOpen(true)
  }

  async function handleSave() {
    if (!form.title?.trim()) {
      setSaveError("Position/title is required.")
      return
    }
    if (!form.deployment_site_id) {
      setSaveError("Deployment site is required.")
      return
    }
    if (!form.capacity || form.capacity < 1) {
      setSaveError("Capacity must be at least 1.")
      return
    }
    setSaving(true)
    setSaveError(null)
    try {
      await updateDeploymentSlot(Number(id), form)
      toast({ title: "Deployment slot updated.", variant: "success" })
      setEditOpen(false)
      reload()
    } catch (err) {
      const message = err instanceof ApiError ? err.message : "An unexpected error occurred."
      setSaveError(message)
      toast({ title: "Error", description: message, variant: "error" })
    } finally {
      setSaving(false)
    }
  }

  function openStatusDialog() {
    setStatusDialogOpen(true)
  }

  async function handleToggleStatus() {
    if (!slot) return
    setToggling(true)
    try {
      const newStatus = slot.status === "active" ? "inactive" : "active"
      await updateDeploymentSlotStatus(slot.id, newStatus)
      toast({
        title: slot.status === "active" ? "Slot deactivated." : "Slot activated.",
        variant: "success",
      })
      setStatusDialogOpen(false)
      reload()
    } catch (err) {
      const message = err instanceof ApiError ? err.message : "An unexpected error occurred."
      toast({ title: "Error", description: message, variant: "error" })
    } finally {
      setToggling(false)
    }
  }

  if (loading) return <FullPageLoader />
  if (error) {
    return (
      <div className="space-y-4">
        <Button variant="ghost" size="sm" onClick={() => navigate("/staff/deployment-slots")}>
          <ArrowLeft className="mr-1.5 size-4" aria-hidden="true" />
          Back to slots
        </Button>
        <div className="rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
          {error.message}
        </div>
      </div>
    )
  }
  if (!slot) return null

  return (
    <div className="space-y-6">
      <div>
        <Button variant="ghost" size="sm" onClick={() => navigate("/staff/deployment-slots")}>
          <ArrowLeft className="mr-1.5 size-4" aria-hidden="true" />
          Back to slots
        </Button>
      </div>

      <PageHeader title={slot.title} description={slot.deployment_site?.name ?? undefined}>
        <div className="flex items-center gap-2">
          <Button variant="outline" onClick={openEdit}>
            <Edit className="mr-1.5 size-4" aria-hidden="true" />
            Edit
          </Button>
          <Button
            variant={slot.status === "active" ? "outline" : "default"}
            onClick={openStatusDialog}
          >
            {slot.status === "active" ? (
              <>
                <ToggleRight className="mr-1.5 size-4" aria-hidden="true" />
                Deactivate
              </>
            ) : (
              <>
                <ToggleLeft className="mr-1.5 size-4" aria-hidden="true" />
                Activate
              </>
            )}
          </Button>
        </div>
      </PageHeader>

      <div className="flex items-center gap-2">
        <span
          className={cn(
            "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium",
            slot.status === "active"
              ? "bg-emerald-100 text-emerald-700"
              : "bg-zinc-100 text-zinc-600",
          )}
        >
          {slot.status_label}
        </span>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <Card>
            <CardContent className="p-6">
              <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Details</h3>
              <dl className="grid gap-4 sm:grid-cols-2">
                <div className="flex items-start gap-3">
                  <Building className="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                  <div>
                    <dt className="text-xs text-muted-foreground">Program Cycle</dt>
                    <dd className="text-sm font-medium">{slot.program_cycle?.name ?? "—"}</dd>
                  </div>
                </div>
                {slot.deployment_site?.host_agency ? (
                  <div className="flex items-start gap-3">
                    <Building className="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <div>
                      <dt className="text-xs text-muted-foreground">Host Agency</dt>
                      <dd className="text-sm font-medium">{slot.deployment_site.host_agency.name}</dd>
                    </div>
                  </div>
                ) : null}
                <div className="flex items-start gap-3">
                  <MapPin className="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                  <div>
                    <dt className="text-xs text-muted-foreground">Deployment Site</dt>
                    <dd className="text-sm font-medium">{slot.deployment_site?.name ?? "—"}</dd>
                  </div>
                </div>
                {slot.description ? (
                  <div className="flex items-start gap-3 sm:col-span-2">
                    <div className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <div>
                      <dt className="text-xs text-muted-foreground">Description</dt>
                      <dd className="text-sm font-medium">{slot.description}</dd>
                    </div>
                  </div>
                ) : null}
                <div className="flex items-start gap-3">
                  <Calendar className="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                  <div>
                    <dt className="text-xs text-muted-foreground">Created</dt>
                    <dd className="text-sm font-medium">{formatDate(slot.created_at)}</dd>
                  </div>
                </div>
              </dl>
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardContent className="p-6">
              <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                Capacity
              </h3>
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Total</span>
                  <span className="text-2xl font-semibold">{slot.capacity}</span>
                </div>
                <div className="h-px bg-border" />
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Assigned</span>
                  <span className="text-lg font-medium">{slot.assigned_count}</span>
                </div>
                <div className="h-px bg-border" />
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground">Available</span>
                  <span
                    className={cn(
                      "text-lg font-medium",
                      slot.available_count === 0 ? "text-destructive" : "text-emerald-600",
                    )}
                  >
                    {slot.available_count}
                  </span>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                Student Assignments
              </h3>
              <div className="flex items-center gap-2 text-muted-foreground">
                <Users className="size-4" aria-hidden="true" />
                <p className="text-sm">No students assigned yet.</p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <Dialog open={editOpen} onOpenChange={(open) => !open && setEditOpen(false)}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Edit deployment slot</DialogTitle>
            <DialogDescription>Update the slot details.</DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            {saveError ? (
              <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {saveError}
              </p>
            ) : null}
            <div className="space-y-2">
              <Label htmlFor="edit-agency">Host Agency</Label>
              <Select
                value={formAgencyId ? String(formAgencyId) : ""}
                onValueChange={(value) => {
                  const agencyId = value ? Number(value) : 0
                  setFormAgencyId(agencyId)
                  if (agencyId) setForm({ ...form, deployment_site_id: 0 })
                }}
              >
                <SelectTrigger id="edit-agency">
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
              <Label htmlFor="edit-site">Deployment Site *</Label>
              <Select
                value={form.deployment_site_id ? String(form.deployment_site_id) : ""}
                onValueChange={(value) => setForm({ ...form, deployment_site_id: Number(value) })}
              >
                <SelectTrigger id="edit-site">
                  <SelectValue placeholder="Select deployment site" />
                </SelectTrigger>
                <SelectContent>
                  {filteredSites.map((site) => (
                    <SelectItem key={site.id} value={String(site.id)}>{site.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-title">Position / Slot Title *</Label>
              <Input
                id="edit-title"
                value={form.title ?? ""}
                onChange={(e) => setForm({ ...form, title: e.target.value })}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-description">Description</Label>
              <Textarea
                id="edit-description"
                value={form.description ?? ""}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-capacity">Capacity *</Label>
              <Input
                id="edit-capacity"
                type="number"
                min={1}
                value={form.capacity ?? 1}
                onChange={(e) => setForm({ ...form, capacity: Number(e.target.value) })}
              />
              {slot.assigned_count > 0 ? (
                <p className="text-xs text-muted-foreground">
                  Currently {slot.assigned_count} student(s) assigned. Cannot reduce below {slot.assigned_count}.
                </p>
              ) : null}
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setEditOpen(false)} disabled={saving}>Cancel</Button>
            <Button onClick={handleSave} disabled={saving}>
              {saving ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              Save Changes
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <ConfirmDialog
        open={statusDialogOpen}
        onOpenChange={setStatusDialogOpen}
        title={slot.status === "active" ? "Deactivate Slot?" : "Activate Slot?"}
        description={
          slot.status === "active"
            ? "This slot will no longer be available for new student assignments."
            : "This slot will be available again for new student assignments."
        }
        confirmLabel={slot.status === "active" ? "Deactivate" : "Activate"}
        destructive={slot.status === "active"}
        loading={toggling}
        onConfirm={handleToggleStatus}
      />
    </div>
  )
}
