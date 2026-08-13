import { useCallback, useMemo, useState } from "react"
import { FileUp, Link2, Loader2, Pencil, Plus, ShieldCheck, Trash2 } from "lucide-react"
import {
  createCycleRequirement,
  fetchCycleRequirements,
  fetchRequirementsCatalog,
  removeCycleRequirement,
  updateCycleRequirement,
} from "@/api/staff"
import type { RequirementPayload } from "@/api/staff"
import type { ProgramCycle, Requirement } from "@/types/api"
import { useToast } from "@/toast/useToast"
import { ApiError } from "@/lib/api"
import { useAsync } from "@/lib/useAsync"
import { formatMaxUploadSize } from "@/lib/format"
import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Switch } from "@/components/ui/switch"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
import { ConfirmDialog } from "@/components/ConfirmDialog"

interface RequirementFormState {
  id?: number
  name: string
  slug: string
  description: string
  is_required: boolean
  allowed_file_types: string
  max_file_size: string
}

const EMPTY_FORM: RequirementFormState = {
  name: "",
  slug: "",
  description: "",
  is_required: true,
  allowed_file_types: "",
  max_file_size: "",
}

function formFromRequirement(requirement: Requirement): RequirementFormState {
  return {
    id: requirement.id,
    name: requirement.name,
    slug: requirement.slug,
    description: requirement.description ?? "",
    is_required: requirement.is_required ?? true,
    allowed_file_types: (requirement.allowed_file_types ?? []).join(", "),
    max_file_size: requirement.max_file_size ? String(requirement.max_file_size) : "",
  }
}

function parseFileTypes(raw: string): string[] {
  return raw
    .split(",")
    .map((value) => value.trim().toLowerCase())
    .filter(Boolean)
}

function fileTypesLabel(requirement: Requirement): string {
  const types = requirement.allowed_file_types ?? []
  return types.length ? types.join(", ") : "Any file type"
}

export function CycleRequirementsDialog({
  cycle,
  open,
  onOpenChange,
  onChanged,
}: {
  cycle: ProgramCycle
  open: boolean
  onOpenChange: (open: boolean) => void
  onChanged: () => void
}) {
  const { toast } = useToast()
  const [attachId, setAttachId] = useState("")
  const [attaching, setAttaching] = useState(false)
  const [togglingId, setTogglingId] = useState<number | null>(null)

  const [formOpen, setFormOpen] = useState(false)
  const [form, setForm] = useState<RequirementFormState>(EMPTY_FORM)
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState<string | null>(null)

  const [removeTarget, setRemoveTarget] = useState<Requirement | null>(null)
  const [removing, setRemoving] = useState(false)

  const loadCycleRequirements = useCallback(() => fetchCycleRequirements(cycle.id), [cycle.id])
  const {
    data: cycleRequirements,
    loading: loadingCycleRequirements,
    error: cycleRequirementsError,
    reload: reloadCycleRequirements,
  } = useAsync(loadCycleRequirements)
  const {
    data: catalogData,
    loading: loadingCatalog,
    error: catalogError,
    reload: reloadCatalog,
  } = useAsync(fetchRequirementsCatalog)

  const requirements = useMemo(() => cycleRequirements ?? [], [cycleRequirements])
  const catalog = useMemo(() => catalogData ?? [], [catalogData])
  const loading = loadingCycleRequirements || loadingCatalog
  const error = cycleRequirementsError?.message ?? catalogError?.message ?? null

  const attachable = useMemo(() => {
    const attachedIds = new Set(requirements.map((requirement) => requirement.id))
    return catalog.filter((requirement) => !attachedIds.has(requirement.id))
  }, [catalog, requirements])

  function openNewForm() {
    setForm({ ...EMPTY_FORM })
    setFormError(null)
    setFormOpen(true)
  }

  function openEditForm(requirement: Requirement) {
    setForm(formFromRequirement(requirement))
    setFormError(null)
    setFormOpen(true)
  }

  function closeForm() {
    setFormOpen(false)
    setForm(EMPTY_FORM)
  }

  function buildPayload(): RequirementPayload {
    return {
      name: form.name.trim(),
      slug: form.slug.trim(),
      description: form.description.trim() || null,
      is_active: true,
      is_required: form.is_required,
      allowed_file_types: parseFileTypes(form.allowed_file_types),
      max_file_size: form.max_file_size ? Number(form.max_file_size) : null,
    }
  }

  async function saveForm() {
    if (!form.name.trim() || !form.slug.trim()) {
      setFormError("Name and slug are required.")
      return
    }
    setSaving(true)
    setFormError(null)
    try {
      if (form.id) {
        await updateCycleRequirement(cycle.id, form.id, buildPayload())
        toast({ title: "Requirement updated", description: "Changes apply to this cycle.", variant: "success" })
      } else {
        await createCycleRequirement(cycle.id, buildPayload())
        toast({ title: "Requirement added", description: "Added to this cycle.", variant: "success" })
      }
      closeForm()
      await Promise.all([reloadCycleRequirements(), reloadCatalog()])
      onChanged()
    } catch (err) {
      const message = err instanceof ApiError ? err.message : "Unable to save requirement."
      setFormError(message)
      toast({ title: "Unable to save", description: message, variant: "error" })
    } finally {
      setSaving(false)
    }
  }

  async function attachExisting() {
    if (!attachId) return
    setAttaching(true)
    try {
      await createCycleRequirement(cycle.id, { requirement_id: Number(attachId), is_required: true })
      toast({ title: "Requirement added", description: "Attached to this cycle.", variant: "success" })
      setAttachId("")
      await Promise.all([reloadCycleRequirements(), reloadCatalog()])
      onChanged()
    } catch (err) {
      const message = err instanceof ApiError ? err.message : "Unable to attach the requirement."
      toast({ title: "Unable to attach", description: message, variant: "error" })
    } finally {
      setAttaching(false)
    }
  }

  async function toggleRequired(requirement: Requirement) {
    setTogglingId(requirement.id)
    try {
      await updateCycleRequirement(cycle.id, requirement.id, {
        name: requirement.name,
        slug: requirement.slug,
        description: requirement.description ?? null,
        is_required: !(requirement.is_required ?? true),
        allowed_file_types: requirement.allowed_file_types ?? [],
        max_file_size: requirement.max_file_size ?? null,
      })
      toast({ title: "Updated", description: "Requirement updated for this cycle.", variant: "success" })
      await Promise.all([reloadCycleRequirements(), reloadCatalog()])
      onChanged()
    } catch (err) {
      const message = err instanceof ApiError ? err.message : "Unable to update the requirement."
      toast({ title: "Unable to update", description: message, variant: "error" })
    } finally {
      setTogglingId(null)
    }
  }

  async function confirmRemove() {
    if (!removeTarget) return
    setRemoving(true)
    try {
      await removeCycleRequirement(cycle.id, removeTarget.id)
      toast({ title: "Requirement removed", description: "Removed from this cycle.", variant: "success" })
      setRemoveTarget(null)
      await Promise.all([reloadCycleRequirements(), reloadCatalog()])
      onChanged()
    } catch (err) {
      const message = err instanceof ApiError ? err.message : "Unable to remove the requirement."
      toast({ title: "Unable to remove", description: message, variant: "error" })
    } finally {
      setRemoving(false)
    }
  }

  return (
    <>
      <Dialog
        open={open}
        onOpenChange={(next) => {
          if (!next) setAttachId("")
          onOpenChange(next)
        }}
      >
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Requirements — {cycle.name}</DialogTitle>
            <DialogDescription>
              Choose which documents students must (or may) submit for this cycle.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            {error ? (
              <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {error}
              </p>
            ) : null}

            {loading ? (
              <div className="flex justify-center py-8">
                <Loader2 className="animate-spin" aria-hidden="true" />
              </div>
            ) : requirements.length === 0 ? (
              <p className="rounded-md border border-dashed px-3 py-6 text-center text-sm text-muted-foreground">
                No requirements attached yet. Add one below.
              </p>
            ) : (
              <div className="space-y-3">
                {requirements.map((requirement) => (
                  <div key={requirement.id} className="rounded-lg border p-3">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                      <div className="min-w-0 space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                          <p className="flex items-center gap-1.5 text-sm font-medium">
                            <ShieldCheck className="size-4 text-muted-foreground" aria-hidden="true" />
                            {requirement.name}
                          </p>
                          <span
                            className={cn(
                              "rounded-full px-2 py-0.5 text-xs font-medium",
                              requirement.is_required
                                ? "bg-amber-100 text-amber-700"
                                : "bg-muted text-muted-foreground",
                            )}
                          >
                            {requirement.is_required ? "Required" : "Optional"}
                          </span>
                        </div>
                        {requirement.description ? (
                          <p className="line-clamp-2 text-xs text-muted-foreground">{requirement.description}</p>
                        ) : null}
                        <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                          <span className="inline-flex items-center gap-1">
                            <FileUp className="size-3.5" aria-hidden="true" />
                            {fileTypesLabel(requirement)}
                          </span>
                          <span>Max {formatMaxUploadSize(requirement.max_file_size)}</span>
                        </p>
                      </div>
                      <div className="flex shrink-0 items-center gap-1.5">
                        <Switch
                          aria-label={`Mark ${requirement.name} as required`}
                          checked={requirement.is_required ?? true}
                          disabled={togglingId === requirement.id}
                          onCheckedChange={() => void toggleRequired(requirement)}
                        />
                        <Button variant="outline" size="sm" onClick={() => openEditForm(requirement)}>
                          <Pencil aria-hidden="true" />
                          Edit
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon-sm"
                          onClick={() => setRemoveTarget(requirement)}
                          aria-label={`Remove ${requirement.name}`}
                        >
                          <Trash2 className="text-destructive" aria-hidden="true" />
                        </Button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}

            <div className="space-y-3 rounded-lg border bg-muted/30 p-3">
              <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Add a requirement</p>

              {attachable.length > 0 ? (
                <div className="flex flex-col gap-2 sm:flex-row">
                  <Select value={attachId} onValueChange={(value) => value !== null && setAttachId(value)}>
                    <SelectTrigger className="w-full sm:flex-1" aria-label="Attach an existing requirement">
                      <SelectValue placeholder="Attach an existing requirement" />
                    </SelectTrigger>
                    <SelectContent>
                      {attachable.map((requirement) => (
                        <SelectItem key={requirement.id} value={String(requirement.id)}>
                          {requirement.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <Button variant="outline" onClick={() => void attachExisting()} disabled={!attachId || attaching}>
                    {attaching ? <Loader2 className="animate-spin" aria-hidden="true" /> : <Link2 aria-hidden="true" />}
                    Attach
                  </Button>
                </div>
              ) : (
                <p className="text-sm text-muted-foreground">
                  All catalog requirements are already attached to this cycle.
                </p>
              )}

              <div className="flex items-center gap-3 text-xs text-muted-foreground">
                <span className="h-px flex-1 bg-border" aria-hidden="true" />
                or
                <span className="h-px flex-1 bg-border" aria-hidden="true" />
              </div>

              <Button variant="outline" className="w-full" onClick={openNewForm}>
                <Plus aria-hidden="true" />
                Create a new requirement
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      <Dialog open={formOpen} onOpenChange={(open) => !open && closeForm()}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{form.id ? "Edit requirement" : "New requirement"}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            {formError ? (
              <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {formError}
              </p>
            ) : null}
            <div className="space-y-2">
              <Label htmlFor="cycle-req-name">Name</Label>
              <Input
                id="cycle-req-name"
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                placeholder="Certificate of Enrollment"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="cycle-req-slug">Slug</Label>
              <Input
                id="cycle-req-slug"
                value={form.slug}
                onChange={(e) => setForm({ ...form, slug: e.target.value })}
                placeholder="certificate-of-enrollment"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="cycle-req-desc">Description (optional)</Label>
              <Textarea
                id="cycle-req-desc"
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
              />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="cycle-req-types">Allowed file types</Label>
                <Input
                  id="cycle-req-types"
                  value={form.allowed_file_types}
                  onChange={(e) => setForm({ ...form, allowed_file_types: e.target.value })}
                  placeholder="pdf, jpg, png"
                />
                <p className="text-xs text-muted-foreground">Comma-separated extensions. Leave blank for any type.</p>
              </div>
              <div className="space-y-2">
                <Label htmlFor="cycle-req-size">Max file size (KB)</Label>
                <Input
                  id="cycle-req-size"
                  type="number"
                  min={1}
                  value={form.max_file_size}
                  onChange={(e) => setForm({ ...form, max_file_size: e.target.value })}
                  placeholder="5120"
                />
              </div>
            </div>
            <div className="flex items-center justify-between rounded-lg border p-3">
              <div className="space-y-0.5">
                <Label className="text-sm font-medium">Required</Label>
                <p className="text-xs text-muted-foreground">Students must upload this to apply.</p>
              </div>
              <Switch
                checked={form.is_required}
                onCheckedChange={(checked) => setForm({ ...form, is_required: checked })}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={closeForm} disabled={saving}>Cancel</Button>
            <Button onClick={() => void saveForm()} disabled={saving}>
              {saving ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              {form.id ? "Save" : "Add"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <ConfirmDialog
        open={removeTarget !== null}
        onOpenChange={(open) => !open && setRemoveTarget(null)}
        title="Remove requirement?"
        description={
          removeTarget
            ? `${removeTarget.name} will be removed from ${cycle.name}. This cannot be undone.`
            : undefined
        }
        confirmLabel="Remove"
        cancelLabel="Cancel"
        destructive
        icon={Trash2}
        loading={removing}
        onConfirm={() => void confirmRemove()}
      />
    </>
  )
}
