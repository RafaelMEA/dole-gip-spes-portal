import { useCallback, useState } from "react"
import {
  Building2,
  Cake,
  GraduationCap,
  HeartPulse,
  Home,
  Loader2,
  Mail,
  MapPin,
  Pencil,
  User,
} from "lucide-react"
import { fetchProfile, updateProfile } from "@/api/student"
import type { ProfilePayload } from "@/api/student"
import { useAsync } from "@/lib/useAsync"
import { ApiError } from "@/lib/api"
import { formatDate } from "@/lib/format"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Switch } from "@/components/ui/switch"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { PageHeader } from "@/components/PageHeader"
import { FullPageLoader } from "@/components/FullPageLoader"
import { useToast } from "@/toast/useToast"
import type { StudentDetails } from "@/types/api"

function Field({ icon: Icon, label, value }: { icon: typeof User; label: string; value: string }) {
  return (
    <div className="flex items-start gap-3">
      <div className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
        <Icon className="size-4" aria-hidden="true" />
      </div>
      <div className="min-w-0">
        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
        <p className="mt-0.5 text-sm">{value}</p>
      </div>
    </div>
  )
}

const YEAR_LEVELS = ["1", "2", "3", "4"]

type ProfileFormState = Omit<ProfilePayload, "sex"> & { sex: string }

export function StudentProfilePage() {
  const { toast } = useToast()
  const fetcher = useCallback(() => fetchProfile(), [])
  const { data: profile, loading, error, reload } = useAsync(fetcher)

  const [editOpen, setEditOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)
  const [form, setForm] = useState<ProfileFormState>({
    name: "",
    school_name: "",
    course: "",
    year_level: "",
    gwa: "",
    address: "",
    birthplace: "",
    birthdate: "",
    sex: "",
    is_indigent: false,
    is_4ps_member: false,
  })

  function openEdit() {
    if (!profile) return
    const detail = profile.student_details ?? ({} as StudentDetails)
    setForm({
      name: profile.name,
      school_name: detail.school_name ?? "",
      course: detail.course ?? "",
      year_level: detail.year_level ?? "",
      gwa: detail.gwa != null ? String(detail.gwa) : "",
      address: detail.address ?? "",
      birthplace: detail.birthplace ?? "",
      birthdate: detail.birthdate ?? "",
      sex: detail.sex ?? "",
      is_indigent: detail.is_indigent,
      is_4ps_member: detail.is_4ps_member,
    })
    setFormError(null)
    setEditOpen(true)
  }

  async function handleSave() {
    setSaving(true)
    setFormError(null)
    try {
      const payload: ProfilePayload = {
        ...form,
        sex: form.sex ? (form.sex as "male" | "female") : null,
        gwa: form.gwa || null,
      }
      const updated = await updateProfile(payload)
      toast({ title: "Profile updated", description: "Your profile has been saved.", variant: "success" })
      setEditOpen(false)
      await reload()
      if (updated) setForm((current) => ({ ...current, name: updated.name }))
    } catch (err) {
      const message = err instanceof ApiError ? err.message : "Unable to save your profile."
      setFormError(message)
      toast({ title: "Unable to save", description: message, variant: "error" })
    } finally {
      setSaving(false)
    }
  }

  if (loading && !profile) return <FullPageLoader />

  if (error && !profile) {
    return (
      <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
        {error.message}
      </p>
    )
  }

  if (!profile) return null

  const detail = profile.student_details

  return (
    <div className="space-y-6">
      <PageHeader title="My profile" description="Your personal and academic information.">
        <Button onClick={openEdit}>
          <Pencil aria-hidden="true" />
          Edit profile
        </Button>
      </PageHeader>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Personal information</CardTitle>
            <CardDescription>Basic account and contact details</CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-2">
            <Field icon={User} label="Full name" value={profile.name} />
            <Field icon={Mail} label="Email" value={profile.email} />
            <Field icon={Home} label="Address" value={detail?.address ?? "—"} />
            <Field icon={Cake} label="Birthdate" value={formatDate(detail?.birthdate ?? null)} />
            <Field icon={HeartPulse} label="Sex" value={detail?.sex ? detail.sex[0].toUpperCase() + detail.sex.slice(1) : "—"} />
            <Field icon={MapPin} label="Birthplace" value={detail?.birthplace ?? "—"} />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Academic information</CardTitle>
            <CardDescription>School and program eligibility details</CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-2">
            <Field icon={Building2} label="School" value={detail?.school_name ?? "—"} />
            <Field icon={GraduationCap} label="Course" value={detail?.course ?? "—"} />
            <Field
              icon={GraduationCap}
              label="Year level"
              value={detail?.year_level ? `Year ${detail.year_level}` : "—"}
            />
            <Field icon={HeartPulse} label="GWA" value={detail?.gwa != null ? String(detail.gwa) : "—"} />
            <Field icon={Home} label="Indigent" value={detail?.is_indigent ? "Yes" : "No"} />
            <Field icon={Home} label="4Ps member" value={detail?.is_4ps_member ? "Yes" : "No"} />
          </CardContent>
        </Card>
      </div>

      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Edit profile</DialogTitle>
            <DialogDescription>Update your personal and academic information.</DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            {formError ? (
              <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {formError}
              </p>
            ) : null}

            <div className="space-y-2">
              <Label htmlFor="name">Full name</Label>
              <Input id="name" value={form.name ?? ""} onChange={(e) => setForm({ ...form, name: e.target.value })} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="school_name">School</Label>
                <Input id="school_name" value={form.school_name ?? ""} onChange={(e) => setForm({ ...form, school_name: e.target.value })} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="course">Course</Label>
                <Input id="course" value={form.course ?? ""} onChange={(e) => setForm({ ...form, course: e.target.value })} />
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="year_level">Year level</Label>
                <Select value={form.year_level ?? ""} onValueChange={(value) => setForm({ ...form, year_level: value ?? "" })}>
                  <SelectTrigger id="year_level" className="w-full">
                    <SelectValue placeholder="Select year level" />
                  </SelectTrigger>
                  <SelectContent>
                    {YEAR_LEVELS.map((level) => (
                      <SelectItem key={level} value={level}>
                        Year {level}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label htmlFor="gwa">GWA</Label>
                <Input
                  id="gwa"
                  type="number"
                  min={1}
                  max={5}
                  step={0.01}
                  value={form.gwa ?? ""}
                  onChange={(e) => setForm({ ...form, gwa: e.target.value })}
                  placeholder="e.g. 1.75"
                />
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="birthdate">Birthdate</Label>
                <Input
                  id="birthdate"
                  type="date"
                  value={form.birthdate ?? ""}
                  onChange={(e) => setForm({ ...form, birthdate: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="sex">Sex</Label>
                <Select value={form.sex ?? ""} onValueChange={(value) => setForm({ ...form, sex: value as "male" | "female" })}>
                  <SelectTrigger id="sex" className="w-full">
                    <SelectValue placeholder="Select sex" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="male">Male</SelectItem>
                    <SelectItem value="female">Female</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="address">Address</Label>
              <Input id="address" value={form.address ?? ""} onChange={(e) => setForm({ ...form, address: e.target.value })} />
            </div>

            <div className="space-y-2">
              <Label htmlFor="birthplace">Birthplace</Label>
              <Input id="birthplace" value={form.birthplace ?? ""} onChange={(e) => setForm({ ...form, birthplace: e.target.value })} />
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
              <div className="flex items-center justify-between rounded-lg border p-3">
                <div className="space-y-0.5">
                  <Label htmlFor="is_indigent" className="text-sm font-medium">Indigent</Label>
                  <p className="text-xs text-muted-foreground">Belongs to an indigent family</p>
                </div>
                <Switch id="is_indigent" checked={form.is_indigent ?? false} onCheckedChange={(checked) => setForm({ ...form, is_indigent: checked })} />
              </div>
              <div className="flex items-center justify-between rounded-lg border p-3">
                <div className="space-y-0.5">
                  <Label htmlFor="is_4ps_member" className="text-sm font-medium">4Ps member</Label>
                  <p className="text-xs text-muted-foreground">Member of Pantawid Pamilya</p>
                </div>
                <Switch id="is_4ps_member" checked={form.is_4ps_member ?? false} onCheckedChange={(checked) => setForm({ ...form, is_4ps_member: checked })} />
              </div>
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setEditOpen(false)} disabled={saving}>
              Cancel
            </Button>
            <Button onClick={handleSave} disabled={saving}>
              {saving ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              Save changes
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
