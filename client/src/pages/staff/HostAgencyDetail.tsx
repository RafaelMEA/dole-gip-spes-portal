import { useCallback, useState } from "react"
import { useNavigate, useParams } from "react-router-dom"
import {
  ArrowLeft,
  Building,
  Calendar,
  Edit,
  Loader2,
  Mail,
  MapPin,
  Phone,
  ToggleLeft,
  ToggleRight,
  User,
} from "lucide-react"
import { fetchHostAgency, updateHostAgency, updateHostAgencyStatus, type HostAgencyPayload } from "@/api/staff"
import { useAsync } from "@/lib/useAsync"
import { ApiError } from "@/lib/api"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from "@/components/ui/dialog"
import { ConfirmDialog } from "@/components/ConfirmDialog"
import { PageHeader } from "@/components/PageHeader"
import { FullPageLoader } from "@/components/FullPageLoader"
import { useToast } from "@/toast/useToast"
import { formatDate } from "@/lib/format"
import { cn } from "@/lib/utils"
import type { HostAgencyType } from "@/types/api"

const AGENCY_TYPES: { value: HostAgencyType; label: string }[] = [
  { value: "government", label: "Government" },
  { value: "private", label: "Private" },
  { value: "ngo", label: "NGO" },
  { value: "other", label: "Other" },
]

export function StaffHostAgencyDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { toast } = useToast()

  const fetcher = useCallback(() => fetchHostAgency(Number(id)), [id])
  const { data: agency, loading, error, reload } = useAsync(fetcher)

  const [editOpen, setEditOpen] = useState(false)
  const [form, setForm] = useState<Partial<HostAgencyPayload>>({})
  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState<string | null>(null)

  const [statusDialogOpen, setStatusDialogOpen] = useState(false)
  const [toggling, setToggling] = useState(false)

  function openEdit() {
    if (!agency) return
    setForm({
      name: agency.name,
      agency_type: agency.agency_type,
      address: agency.address ?? "",
      contact_person: agency.contact_person ?? "",
      contact_number: agency.contact_number ?? "",
      email: agency.email ?? "",
    })
    setSaveError(null)
    setEditOpen(true)
  }

  async function handleSave() {
    if (!form.name?.trim()) {
      setSaveError("Agency name is required.")
      return
    }
    setSaving(true)
    setSaveError(null)
    try {
      await updateHostAgency(Number(id), form)
      toast({ title: "Host agency updated.", variant: "success" })
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
    if (!agency) return
    setToggling(true)
    try {
      await updateHostAgencyStatus(agency.id, !agency.is_active)
      toast({
        title: agency.is_active ? "Host agency deactivated." : "Host agency activated.",
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
        <Button variant="ghost" size="sm" onClick={() => navigate("/staff/host-agencies")}>
          <ArrowLeft className="mr-1.5 size-4" aria-hidden="true" />
          Back to agencies
        </Button>
        <div className="rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
          {error.message}
        </div>
      </div>
    )
  }
  if (!agency) return null

  const infoItems = [
    agency.agency_type_label ? { icon: Building, label: "Agency Type", value: agency.agency_type_label } : null,
    agency.address ? { icon: MapPin, label: "Address", value: agency.address } : null,
    agency.contact_person ? { icon: User, label: "Contact Person", value: agency.contact_person } : null,
    agency.contact_number ? { icon: Phone, label: "Contact Number", value: agency.contact_number } : null,
    agency.email ? { icon: Mail, label: "Email", value: agency.email } : null,
    agency.created_at ? { icon: Calendar, label: "Created", value: formatDate(agency.created_at) } : null,
  ].filter(Boolean) as { icon: typeof Building; label: string; value: string }[]

  return (
    <div className="space-y-6">
      <div>
        <Button variant="ghost" size="sm" onClick={() => navigate("/staff/host-agencies")}>
          <ArrowLeft className="mr-1.5 size-4" aria-hidden="true" />
          Back to agencies
        </Button>
      </div>

      <PageHeader title={agency.name} description={agency.address ?? undefined}>
        <div className="flex items-center gap-2">
          <Button variant="outline" onClick={openEdit}>
            <Edit className="mr-1.5 size-4" aria-hidden="true" />
            Edit
          </Button>
          <Button
            variant={agency.is_active ? "outline" : "default"}
            onClick={openStatusDialog}
          >
            {agency.is_active ? (
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
            agency.is_active
              ? "bg-emerald-100 text-emerald-700"
              : "bg-zinc-100 text-zinc-600",
          )}
        >
          {agency.is_active ? "ACTIVE" : "INACTIVE"}
        </span>
        {agency.active_assignments > 0 ? (
          <span className="text-sm text-muted-foreground">
            {agency.active_assignments} active assignment{agency.active_assignments === 1 ? "" : "s"}
          </span>
        ) : null}
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <Card>
            <CardContent className="p-6">
              <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Details</h3>
              <dl className="grid gap-4 sm:grid-cols-2">
                {infoItems.map((item) => (
                  <div key={item.label} className="flex items-start gap-3">
                    <item.icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <div>
                      <dt className="text-xs text-muted-foreground">{item.label}</dt>
                      <dd className="text-sm font-medium">{item.value}</dd>
                    </div>
                  </div>
                ))}
              </dl>
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardContent className="p-6">
              <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                Deployment Sites
              </h3>
              <p className="text-sm text-muted-foreground">
                No deployment sites configured yet.
              </p>
            </CardContent>
          </Card>
        </div>
      </div>

      <Dialog open={editOpen} onOpenChange={(open) => !open && setEditOpen(false)}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Edit host agency</DialogTitle>
            <DialogDescription>Update the agency details.</DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            {saveError ? (
              <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {saveError}
              </p>
            ) : null}
            <div className="space-y-2">
              <Label htmlFor="edit-name">Agency Name *</Label>
              <Input
                id="edit-name"
                value={form.name ?? ""}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-type">Agency Type</Label>
              <Select
                value={form.agency_type ?? "other"}
                onValueChange={(value) => setForm({ ...form, agency_type: value as HostAgencyType })}
              >
                <SelectTrigger id="edit-type">
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
              <Label htmlFor="edit-address">Address</Label>
              <Input
                id="edit-address"
                value={form.address ?? ""}
                onChange={(e) => setForm({ ...form, address: e.target.value })}
              />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="edit-contact">Contact Person</Label>
                <Input
                  id="edit-contact"
                  value={form.contact_person ?? ""}
                  onChange={(e) => setForm({ ...form, contact_person: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit-number">Contact Number</Label>
                <Input
                  id="edit-number"
                  value={form.contact_number ?? ""}
                  onChange={(e) => setForm({ ...form, contact_number: e.target.value })}
                />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-email">Email</Label>
              <Input
                id="edit-email"
                type="email"
                value={form.email ?? ""}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
              />
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
        title={agency.is_active ? "Deactivate Host Agency?" : "Activate Host Agency?"}
        description={
          agency.is_active
            ? "This agency will no longer be available for new deployment assignments."
            : "This agency will be available again for new deployment assignments."
        }
        confirmLabel={agency.is_active ? "Deactivate" : "Activate"}
        destructive={agency.is_active}
        loading={toggling}
        onConfirm={handleToggleStatus}
      />
    </div>
  )
}
