import { useCallback, useState } from "react"
import { Link, useNavigate, useParams } from "react-router-dom"
import {
  ArrowLeft,
  Building,
  Calendar,
  Edit,
  Loader2,
  Mail,
  MapPin,
  Phone,
  Rows3,
  ToggleLeft,
  ToggleRight,
  User,
} from "lucide-react"
import {
  fetchDeploymentSite,
  fetchHostAgencies,
  updateDeploymentSite,
  updateDeploymentSiteStatus,
  type DeploymentSitePayload,
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

export function StaffDeploymentSiteDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { toast } = useToast()

  const fetcher = useCallback(() => fetchDeploymentSite(Number(id)), [id])
  const { data: site, loading, error, reload } = useAsync(fetcher)

  const fetcherAgencies = useCallback(() => fetchHostAgencies({ per_page: 100 }), [])
  const { data: agenciesPage } = useAsync(fetcherAgencies)
  const agencies = agenciesPage?.data ?? []

  const [editOpen, setEditOpen] = useState(false)
  const [form, setForm] = useState<Partial<DeploymentSitePayload>>({})
  const [saving, setSaving] = useState(false)
  const [saveError, setSaveError] = useState<string | null>(null)

  const [statusDialogOpen, setStatusDialogOpen] = useState(false)
  const [toggling, setToggling] = useState(false)

  function openEdit() {
    if (!site) return
    setForm({
      host_agency_id: site.host_agency_id,
      name: site.name,
      address: site.address ?? "",
      city: site.city ?? "",
      region: site.region ?? "",
      contact_person: site.contact_person ?? "",
      contact_number: site.contact_number ?? "",
      email: site.email ?? "",
      description: site.description ?? "",
    })
    setSaveError(null)
    setEditOpen(true)
  }

  async function handleSave() {
    if (!form.name?.trim()) {
      setSaveError("Site name is required.")
      return
    }
    if (!form.host_agency_id) {
      setSaveError("Host agency is required.")
      return
    }
    setSaving(true)
    setSaveError(null)
    try {
      await updateDeploymentSite(Number(id), form)
      toast({ title: "Deployment site updated.", variant: "success" })
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
    if (!site) return
    setToggling(true)
    try {
      await updateDeploymentSiteStatus(site.id, !site.is_active)
      toast({
        title: site.is_active ? "Deployment site deactivated." : "Deployment site activated.",
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
        <Button variant="ghost" size="sm" onClick={() => navigate("/staff/deployment-sites")}>
          <ArrowLeft className="mr-1.5 size-4" aria-hidden="true" />
          Back to sites
        </Button>
        <div className="rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive">
          {error.message}
        </div>
      </div>
    )
  }
  if (!site) return null

  const infoItems = [
    site.host_agency ? { icon: Building, label: "Host Agency", value: site.host_agency.name } : null,
    site.address ? { icon: MapPin, label: "Address", value: site.address } : null,
    [site.city, site.region].filter(Boolean).length > 0
      ? { icon: MapPin, label: "Location", value: [site.city, site.region].filter(Boolean).join(", ") }
      : null,
    site.contact_person ? { icon: User, label: "Contact Person", value: site.contact_person } : null,
    site.contact_number ? { icon: Phone, label: "Contact Number", value: site.contact_number } : null,
    site.email ? { icon: Mail, label: "Email", value: site.email } : null,
    site.description ? { icon: null, label: "Description", value: site.description } : null,
    site.created_at ? { icon: Calendar, label: "Created", value: formatDate(site.created_at) } : null,
  ].filter(Boolean) as { icon: typeof Building | null; label: string; value: string }[]

  return (
    <div className="space-y-6">
      <div>
        <Button variant="ghost" size="sm" onClick={() => navigate("/staff/deployment-sites")}>
          <ArrowLeft className="mr-1.5 size-4" aria-hidden="true" />
          Back to sites
        </Button>
      </div>

      <PageHeader title={site.name} description={site.host_agency?.name ?? undefined}>
        <div className="flex items-center gap-2">
          <Button variant="outline" onClick={openEdit}>
            <Edit className="mr-1.5 size-4" aria-hidden="true" />
            Edit
          </Button>
          <Button
            variant={site.is_active ? "outline" : "default"}
            onClick={openStatusDialog}
          >
            {site.is_active ? (
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
            site.is_active
              ? "bg-emerald-100 text-emerald-700"
              : "bg-zinc-100 text-zinc-600",
          )}
        >
          {site.is_active ? "ACTIVE" : "INACTIVE"}
        </span>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <Card>
            <CardContent className="p-6">
              <h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Details</h3>
              <dl className="grid gap-4 sm:grid-cols-2">
                {infoItems.map((item) => (
                  <div key={item.label} className={cn("flex items-start gap-3", item.label === "Description" && "sm:col-span-2")}>
                    {item.icon ? <item.icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" /> : null}
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
                Deployment Slots
              </h3>
              <div className="flex items-center gap-2 text-muted-foreground">
                <Rows3 className="size-4" aria-hidden="true" />
                <Link
                  to={`/staff/deployment-slots?deployment_site_id=${site.id}`}
                  className="text-sm font-medium text-foreground hover:underline"
                >
                  View all slots for this site
                </Link>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <Dialog open={editOpen} onOpenChange={(open) => !open && setEditOpen(false)}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Edit deployment site</DialogTitle>
            <DialogDescription>Update the site details.</DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            {saveError ? (
              <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {saveError}
              </p>
            ) : null}
            <div className="space-y-2">
              <Label htmlFor="edit-agency">Host Agency *</Label>
              <Select
                value={form.host_agency_id ? String(form.host_agency_id) : ""}
                onValueChange={(value) => setForm({ ...form, host_agency_id: Number(value) })}
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
              <Label htmlFor="edit-name">Site Name *</Label>
              <Input
                id="edit-name"
                value={form.name ?? ""}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
              />
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
                <Label htmlFor="edit-city">City</Label>
                <Input
                  id="edit-city"
                  value={form.city ?? ""}
                  onChange={(e) => setForm({ ...form, city: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="edit-region">Region</Label>
                <Input
                  id="edit-region"
                  value={form.region ?? ""}
                  onChange={(e) => setForm({ ...form, region: e.target.value })}
                />
              </div>
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
            <div className="space-y-2">
              <Label htmlFor="edit-description">Description</Label>
              <Textarea
                id="edit-description"
                value={form.description ?? ""}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
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
        title={site.is_active ? "Deactivate Deployment Site?" : "Activate Deployment Site?"}
        description={
          site.is_active
            ? "This site will no longer be available for new deployment assignments."
            : "This site will be available again for new deployment assignments."
        }
        confirmLabel={site.is_active ? "Deactivate" : "Activate"}
        destructive={site.is_active}
        loading={toggling}
        onConfirm={handleToggleStatus}
      />
    </div>
  )
}
