import { useMemo, useState } from "react"
import {
  Building2,
  CalendarRange,
  ChevronDown,
  ChevronUp,
  Loader2,
  MapPin,
  Pencil,
  Plus,
  Rocket,
  RotateCcw,
  Search,
  ShieldCheck,
  Trash2,
  Users,
} from "lucide-react"
import {
  createCycle,
  createDeploymentSite,
  createHostAgency,
  createProgram,
  createRequirement,
  deleteCycle,
  deleteRequirement,
  fetchCyclesCatalog,
  fetchDeploymentSites,
  fetchHostAgencies,
  fetchProgramsCatalog,
  fetchRequirementsCatalog,
  publishCycle,
  unpublishCycle,
  updateCycle,
  updateDeploymentSite,
  updateHostAgency,
  updateProgram,
  updateRequirement,
} from "@/api/staff"
import type { CatalogPayload, CyclePayload, RequirementPayload, HostAgencyPayload } from "@/api/staff"
import { useAsync } from "@/lib/useAsync"
import { ApiError } from "@/lib/api"
import { formatDate, formatMaxUploadSize } from "@/lib/format"
import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"
import { Switch } from "@/components/ui/switch"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Card, CardContent } from "@/components/ui/card"
import { PageHeader } from "@/components/PageHeader"
import { EmptyState } from "@/components/EmptyState"
import { ConfirmDialog } from "@/components/ConfirmDialog"
import { CycleStatusBadge } from "@/components/StatusBadge"
import { useToast } from "@/toast/useToast"
import type { DeploymentSite, HostAgency, ProgramCycle, Requirement } from "@/types/api"
import { CycleRequirementsDialog } from "./CycleRequirementsDialog"

type TabValue = "programs" | "cycles" | "requirements" | "agencies" | "sites"
type StatusFilter = "all" | ProgramCycle["status"]

const STATUS_FILTERS: { value: StatusFilter; label: string }[] = [
  { value: "all", label: "All statuses" },
  { value: "draft", label: "Draft" },
  { value: "upcoming", label: "Upcoming" },
  { value: "open", label: "Open" },
  { value: "closed", label: "Closed" },
  { value: "archived", label: "Archived" },
]

function EmptyCatalog({ title, description, onAdd }: { title: string; description: string; onAdd: () => void }) {
  return (
    <EmptyState title={title} description={description}>
      <Button onClick={onAdd}>
        <Plus aria-hidden="true" />
        Add
      </Button>
    </EmptyState>
  )
}

interface ProgramForm extends CatalogPayload {
  id?: number
}
interface RequirementForm {
  id?: number
  name: string
  slug: string
  description: string
  is_active: boolean
  allowed_file_types: string
  max_file_size: string
}
interface AgencyForm extends Partial<HostAgency> {
  id?: number
}
interface SiteForm extends Partial<DeploymentSite> {
  id?: number
}
interface CycleForm extends CyclePayload {
  id?: number
  requirements: number[]
}

const emptyProgram: ProgramForm = { name: "", slug: "", description: "", is_active: true }
const emptyRequirement: RequirementForm = {
  name: "",
  slug: "",
  description: "",
  is_active: true,
  allowed_file_types: "",
  max_file_size: "",
}
const emptyAgency: AgencyForm = { name: "", address: "", contact_person: "", contact_number: "", email: "", is_active: true }
const emptySite: SiteForm = { name: "", address: "", city: "", region: "", is_active: true }

function emptyCycle(): CycleForm {
  return {
    program_id: 0,
    name: "",
    description: "",
    total_slots: 0,
    application_start: "",
    application_deadline: "",
    deployment_start: "",
    deployment_end: "",
    requirements: [],
  }
}

function cycleToForm(cycle: ProgramCycle): CycleForm {
  return {
    id: cycle.id,
    program_id: cycle.program_id,
    name: cycle.name,
    description: cycle.description ?? "",
    total_slots: cycle.total_slots,
    application_start: cycle.application_start ?? "",
    application_deadline: cycle.application_deadline ?? "",
    deployment_start: cycle.deployment_start ?? "",
    deployment_end: cycle.deployment_end ?? "",
    requirements: (cycle.requirements ?? []).map((requirement) => requirement.id),
  }
}

export function StaffCatalogPage() {
  const { toast } = useToast()
  const [tab, setTab] = useState<TabValue>("programs")

  const { data: programs, loading: programsLoading, error: programsError, reload: reloadPrograms } = useAsync(fetchProgramsCatalog)
  const { data: cycles, loading: cyclesLoading, error: cyclesError, reload: reloadCycles } = useAsync(fetchCyclesCatalog)
  const { data: requirements, loading: requirementsLoading, error: requirementsError, reload: reloadRequirements } = useAsync(fetchRequirementsCatalog)
  const { data: agenciesPage, loading: agenciesLoading, error: agenciesError, reload: reloadAgencies } = useAsync(() => fetchHostAgencies({ per_page: 100 }))
  const { data: sites, loading: sitesLoading, error: sitesError, reload: reloadSites } = useAsync(fetchDeploymentSites)

  const [programModal, setProgramModal] = useState<{ open: boolean; form: ProgramForm }>({ open: false, form: emptyProgram })
  const [requirementModal, setRequirementModal] = useState<{ open: boolean; form: RequirementForm }>({ open: false, form: emptyRequirement })
  const [agencyModal, setAgencyModal] = useState<{ open: boolean; form: AgencyForm }>({ open: false, form: emptyAgency })
  const [siteModal, setSiteModal] = useState<{ open: boolean; form: SiteForm }>({ open: false, form: emptySite })
  const [cycleModal, setCycleModal] = useState<{ open: boolean; form: CycleForm }>({ open: false, form: emptyCycle() })
  const [saving, setSaving] = useState(false)
  const [modalError, setModalError] = useState<string | null>(null)
  const [actionId, setActionId] = useState<number | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<ProgramCycle | null>(null)
  const [deleting, setDeleting] = useState(false)
  const [deleteRequirementTarget, setDeleteRequirementTarget] = useState<Requirement | null>(null)
  const [deletingRequirement, setDeletingRequirement] = useState(false)
  const [expanded, setExpanded] = useState<Set<number>>(new Set())
  const [requirementsCycle, setRequirementsCycle] = useState<ProgramCycle | null>(null)

  const [programSearch, setProgramSearch] = useState("")
  const [cycleSearch, setCycleSearch] = useState("")
  const [cycleStatus, setCycleStatus] = useState<StatusFilter>("all")

  const filteredPrograms = useMemo(() => {
    const query = programSearch.trim().toLowerCase()
    if (!query) return programs ?? []
    return (programs ?? []).filter((program) =>
      [program.name, program.slug, program.description ?? ""].some((field) =>
        field.toLowerCase().includes(query),
      ),
    )
  }, [programs, programSearch])

  const filteredCycles = useMemo(() => {
    const query = cycleSearch.trim().toLowerCase()
    return (cycles ?? []).filter((cycle) => {
      if (cycleStatus !== "all" && cycle.status !== cycleStatus) return false
      if (!query) return true
      return [cycle.name, cycle.program?.name ?? ""].some((field) =>
        field.toLowerCase().includes(query),
      )
    })
  }, [cycles, cycleSearch, cycleStatus])

  function closeModal() {
    setProgramModal((m) => ({ ...m, open: false }))
    setRequirementModal((m) => ({ ...m, open: false }))
    setAgencyModal((m) => ({ ...m, open: false }))
    setSiteModal((m) => ({ ...m, open: false }))
    setCycleModal((m) => ({ ...m, open: false }))
  }

  async function run(action: () => Promise<unknown>, successMessage: string) {
    setSaving(true)
    setModalError(null)
    try {
      await action()
      toast({ title: "Saved", description: successMessage, variant: "success" })
      closeModal()
      await Promise.all([reloadPrograms(), reloadCycles(), reloadRequirements(), reloadAgencies(), reloadSites()])
    } catch (err) {
      const message = err instanceof ApiError ? err.message : "Unable to save changes."
      setModalError(message)
      toast({ title: "Unable to save", description: message, variant: "error" })
    } finally {
      setSaving(false)
    }
  }

  function saveProgram() {
    const { id, ...payload } = programModal.form
    void run(
      () => (id ? updateProgram(id, payload) : createProgram(payload)),
      id ? "Program updated." : "Program created.",
    )
  }

  function saveRequirement() {
    const form = requirementModal.form
    const { id, ...rest } = form
    const payload: RequirementPayload = {
      name: rest.name,
      slug: rest.slug,
      description: rest.description || undefined,
      is_active: rest.is_active,
      allowed_file_types: rest.allowed_file_types
        .split(",")
        .map((value) => value.trim().toLowerCase())
        .filter(Boolean),
      max_file_size: rest.max_file_size ? Number(rest.max_file_size) : null,
    }
    void run(
      () => (id ? updateRequirement(id, payload) : createRequirement(payload)),
      id ? "Requirement updated." : "Requirement created.",
    )
  }

  function saveAgency() {
    const { id, agency_type_label, created_at, active_assignments, ...rest } = agencyModal.form
    const payload = Object.fromEntries(
      Object.entries(rest).map(([key, value]) => [key, value === null ? undefined : value]),
    ) as unknown as HostAgencyPayload
    void run(
      () => (id ? updateHostAgency(id, payload) : createHostAgency(payload)),
      id ? "Host agency updated." : "Host agency created.",
    )
  }

  function saveSite() {
    const { id, ...payload } = siteModal.form
    void run(
      () => (id ? updateDeploymentSite(id, payload) : createDeploymentSite(payload)),
      id ? "Deployment site updated." : "Deployment site created.",
    )
  }

  function saveCycle() {
    const form = cycleModal.form
    if (!form.program_id || !form.name || !form.total_slots || !form.application_start || !form.application_deadline) {
      setModalError("Program, name, total slots, and application dates are required.")
      return
    }
    const payload: CyclePayload = {
      program_id: Number(form.program_id),
      name: form.name,
      description: form.description || undefined,
      total_slots: Number(form.total_slots),
      application_start: form.application_start,
      application_deadline: form.application_deadline,
      deployment_start: form.deployment_start || undefined,
      deployment_end: form.deployment_end || undefined,
      requirements: form.requirements.length ? form.requirements : undefined,
    }
    void run(
      () => (form.id ? updateCycle(form.id, payload) : createCycle(payload)),
      form.id ? "Program cycle updated." : "Program cycle created.",
    )
  }

  async function toggleCyclePublish(cycle: ProgramCycle) {
    setActionId(cycle.id)
    try {
      if (cycle.status === "draft") {
        await publishCycle(cycle.id)
        toast({ title: "Cycle published", description: `${cycle.name} is now visible to students.`, variant: "success" })
      } else {
        await unpublishCycle(cycle.id)
        toast({ title: "Cycle unpublished", description: `${cycle.name} is now a draft and hidden from students.` })
      }
      await Promise.all([reloadPrograms(), reloadCycles()])
    } catch (err) {
      const message = err instanceof ApiError ? err.message : "Unable to update the cycle status."
      toast({ title: "Unable to update cycle", description: message, variant: "error" })
    } finally {
      setActionId(null)
    }
  }

  async function confirmDeleteCycle() {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      await deleteCycle(deleteTarget.id)
      toast({ title: "Cycle deleted", description: `${deleteTarget.name} was removed.`, variant: "success" })
      setDeleteTarget(null)
      await Promise.all([reloadPrograms(), reloadCycles()])
    } catch (err) {
      const message = err instanceof ApiError ? err.message : "Unable to delete the cycle."
      toast({ title: "Unable to delete", description: message, variant: "error" })
    } finally {
      setDeleting(false)
    }
  }

  async function confirmDeleteRequirement() {
    if (!deleteRequirementTarget) return
    setDeletingRequirement(true)
    try {
      await deleteRequirement(deleteRequirementTarget.id)
      toast({ title: "Requirement deleted", description: `${deleteRequirementTarget.name} was removed.`, variant: "success" })
      setDeleteRequirementTarget(null)
      await Promise.all([reloadRequirements(), reloadPrograms(), reloadCycles()])
    } catch (err) {
      const message = err instanceof ApiError ? err.message : "Unable to delete the requirement."
      toast({ title: "Unable to delete", description: message, variant: "error" })
    } finally {
      setDeletingRequirement(false)
    }
  }

  function toggleExpand(id: number) {
    setExpanded((current) => {
      const next = new Set(current)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  const errorFor = (error: ApiError | null) =>
    error ? (
      <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
        {error.message}
      </p>
    ) : null

  return (
    <div className="space-y-6">
      <PageHeader title="Catalog" description="Manage programs, cycles, requirements, agencies, and sites." />

      <Tabs value={tab} onValueChange={(value) => setTab(value as TabValue)}>
        <TabsList className="flex-wrap">
          <TabsTrigger value="programs">Programs</TabsTrigger>
          <TabsTrigger value="cycles">Cycles</TabsTrigger>
          <TabsTrigger value="requirements">Requirements</TabsTrigger>
          <TabsTrigger value="agencies">Host agencies</TabsTrigger>
          <TabsTrigger value="sites">Sites</TabsTrigger>
        </TabsList>

        <TabsContent value="programs" className="mt-4 space-y-4">
          {errorFor(programsError)}
          <Toolbar
            placeholder="Search programs..."
            value={programSearch}
            onChange={setProgramSearch}
            action={
              <Button onClick={() => setProgramModal({ open: true, form: emptyProgram })}>
                <Plus aria-hidden="true" />
                New program
              </Button>
            }
          />
          {programsLoading && !programs ? (
            <Loader2 className="animate-spin" aria-hidden="true" />
          ) : null}
          {programs && programs.length === 0 ? (
            <EmptyCatalog title="No programs" description="Create your first program." onAdd={() => setProgramModal({ open: true, form: emptyProgram })} />
          ) : null}
          {programs && programs.length > 0 && filteredPrograms.length === 0 ? (
            <EmptyState icon={Search} title="No matching programs" description="Try a different search term." />
          ) : null}
          {filteredPrograms.length > 0 ? (
            <div className="space-y-3">
              {filteredPrograms.map((program) => {
                const isExpanded = expanded.has(program.id)
                const programCycles = program.cycles ?? []
                const publishedCount = programCycles.filter((cycle) => cycle.is_published).length
                return (
                  <Card key={program.id}>
                    <CardContent className="p-4">
                      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="min-w-0 space-y-1">
                          <div className="flex flex-wrap items-center gap-2">
                            <p className="text-sm font-semibold">{program.name}</p>
                            <span className="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">{program.slug}</span>
                            <span className={cn("rounded-full px-2 py-0.5 text-xs font-medium", program.is_active ? "bg-emerald-100 text-emerald-700" : "bg-zinc-100 text-zinc-500")}>
                              {program.is_active ? "Active" : "Inactive"}
                            </span>
                          </div>
                          <p className="line-clamp-2 text-sm text-muted-foreground">{program.description ?? "No description"}</p>
                          <p className="text-xs text-muted-foreground">
                            {programCycles.length} cycle{programCycles.length === 1 ? "" : "s"} · {publishedCount} published
                          </p>
                        </div>
                        <div className="flex shrink-0 items-center gap-2">
                          <Button variant="outline" size="sm" onClick={() => toggleExpand(program.id)}>
                            {isExpanded ? <ChevronUp aria-hidden="true" /> : <ChevronDown aria-hidden="true" />}
                            {isExpanded ? "Hide cycles" : "View cycles"}
                          </Button>
                          <Button variant="outline" size="sm" onClick={() => setProgramModal({ open: true, form: { ...program, description: program.description ?? "" } })}>
                            <Pencil aria-hidden="true" />
                            Edit
                          </Button>
                        </div>
                      </div>

                      {isExpanded ? (
                        <div className="mt-4 space-y-3 border-t pt-4">
                          {programCycles.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                              No cycles yet.{" "}
                              <button
                                className="font-medium text-primary underline-offset-4 hover:underline"
                                onClick={() => setCycleModal({ open: true, form: { ...emptyCycle(), program_id: program.id } })}
                              >
                                Create the first cycle
                              </button>
                            </p>
                          ) : (
                            programCycles.map((cycle) => (
                              <CycleRow
                                key={cycle.id}
                                cycle={cycle}
                                busy={actionId === cycle.id}
                                onEdit={() => setCycleModal({ open: true, form: cycleToForm(cycle) })}
                                onManageRequirements={() => setRequirementsCycle(cycle)}
                                onTogglePublish={() => void toggleCyclePublish(cycle)}
                                onDelete={() => setDeleteTarget(cycle)}
                              />
                            ))
                          )}
                        </div>
                      ) : null}
                    </CardContent>
                  </Card>
                )
              })}
            </div>
          ) : null}
        </TabsContent>

        <TabsContent value="cycles" className="mt-4 space-y-4">
          {errorFor(cyclesError)}
          <Toolbar
            placeholder="Search cycles..."
            value={cycleSearch}
            onChange={setCycleSearch}
            filter={
              <Select value={cycleStatus} onValueChange={(value) => setCycleStatus(value as StatusFilter)}>
                <SelectTrigger className="w-full sm:w-40" aria-label="Filter by status">
                  <SelectValue placeholder="All statuses" />
                </SelectTrigger>
                <SelectContent>
                  {STATUS_FILTERS.map((filter) => (
                    <SelectItem key={filter.value} value={filter.value}>{filter.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            }
            action={
              <Button onClick={() => setCycleModal({ open: true, form: emptyCycle() })}>
                <Plus aria-hidden="true" />
                New cycle
              </Button>
            }
          />
          {cyclesLoading && !cycles ? (
            <Loader2 className="animate-spin" aria-hidden="true" />
          ) : null}
          {cycles && cycles.length === 0 ? (
            <EmptyCatalog title="No program cycles" description="Create a cycle to open applications." onAdd={() => setCycleModal({ open: true, form: emptyCycle() })} />
          ) : null}
          {cycles && cycles.length > 0 && filteredCycles.length === 0 ? (
            <EmptyState icon={Search} title="No matching cycles" description="Try adjusting your search or filters." />
          ) : null}
          {filteredCycles.length > 0 ? (
            <div className="space-y-3">
              {filteredCycles.map((cycle) => (
                <CycleRow
                  key={cycle.id}
                  cycle={cycle}
                  busy={actionId === cycle.id}
                  onEdit={() => setCycleModal({ open: true, form: cycleToForm(cycle) })}
                  onManageRequirements={() => setRequirementsCycle(cycle)}
                  onTogglePublish={() => void toggleCyclePublish(cycle)}
                  onDelete={() => setDeleteTarget(cycle)}
                />
              ))}
            </div>
          ) : null}
        </TabsContent>

        <TabsContent value="requirements" className="mt-4 space-y-4">
          {errorFor(requirementsError)}
          {requirementsLoading ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
          {requirements && requirements.length === 0 ? (
            <EmptyCatalog title="No requirements" description="Create document requirements students must submit." onAdd={() => setRequirementModal({ open: true, form: emptyRequirement })} />
          ) : null}
          {requirements && requirements.length > 0 ? (
            <div className="space-y-3">
              <div className="flex justify-end">
                <Button onClick={() => setRequirementModal({ open: true, form: emptyRequirement })}>
                  <Plus aria-hidden="true" />
                  New requirement
                </Button>
              </div>
              {requirements.map((requirement) => {
                const fileTypes = (requirement.allowed_file_types ?? []).join(", ")
                return (
                  <Card key={requirement.id}>
                    <CardContent className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                      <div className="min-w-0 space-y-0.5">
                        <p className="flex items-center gap-2 text-sm font-medium">
                          <ShieldCheck className="size-4 text-muted-foreground" aria-hidden="true" />
                          {requirement.name}
                          <span className="text-xs text-muted-foreground">({requirement.slug})</span>
                        </p>
                        <p className="text-xs text-muted-foreground">{requirement.description ?? "No description"} · {requirement.is_active ? "Active" : "Inactive"}</p>
                        <p className="flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
                          <span>{fileTypes ? `Allowed: ${fileTypes}` : "Any file type"}</span>
                          <span>Max {formatMaxUploadSize(requirement.max_file_size)}</span>
                        </p>
                      </div>
                      <div className="flex shrink-0 items-center gap-1.5">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() =>
                            setRequirementModal({
                              open: true,
                              form: {
                                ...requirement,
                                description: requirement.description ?? "",
                                allowed_file_types: (requirement.allowed_file_types ?? []).join(", "),
                                max_file_size: requirement.max_file_size ? String(requirement.max_file_size) : "",
                              },
                            })
                          }
                        >
                          <Pencil aria-hidden="true" />
                          Edit
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon-sm"
                          onClick={() => setDeleteRequirementTarget(requirement)}
                          aria-label={`Delete ${requirement.name}`}
                        >
                          <Trash2 className="text-destructive" aria-hidden="true" />
                        </Button>
                      </div>
                    </CardContent>
                  </Card>
                )
              })}
            </div>
          ) : null}
        </TabsContent>

        <TabsContent value="agencies" className="mt-4 space-y-4">
          {errorFor(agenciesError)}
          {agenciesLoading ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
          {agenciesPage && agenciesPage.data.length === 0 ? (
            <EmptyCatalog title="No host agencies" description="Add agencies that host interns." onAdd={() => setAgencyModal({ open: true, form: emptyAgency })} />
          ) : null}
          {agenciesPage && agenciesPage.data.length > 0 ? (
            <div className="space-y-3">
              <div className="flex justify-end">
                <Button onClick={() => setAgencyModal({ open: true, form: emptyAgency })}>
                  <Plus aria-hidden="true" />
                  New agency
                </Button>
              </div>
              {agenciesPage.data.map((agency) => (
                <Card key={agency.id}>
                  <CardContent className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0 space-y-0.5">
                      <p className="flex items-center gap-2 text-sm font-medium">
                        <Building2 className="size-4 text-muted-foreground" aria-hidden="true" />
                        {agency.name}
                      </p>
                      <p className="text-xs text-muted-foreground">
                        {agency.address ?? "No address"} · {agency.active_assignments} active assignment{agency.active_assignments === 1 ? "" : "s"}
                      </p>
                      <p className="text-xs text-muted-foreground">
                        {agency.contact_person ?? "No contact person"} {agency.contact_number ? `· ${agency.contact_number}` : ""}
                      </p>
                    </div>
                    <Button variant="outline" size="sm" onClick={() => setAgencyModal({ open: true, form: { ...agency } })}>
                      <Pencil aria-hidden="true" />
                      Edit
                    </Button>
                  </CardContent>
                </Card>
              ))}
            </div>
          ) : null}
        </TabsContent>

        <TabsContent value="sites" className="mt-4 space-y-4">
          {errorFor(sitesError)}
          {sitesLoading ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
          {sites && sites.length === 0 ? (
            <EmptyCatalog title="No deployment sites" description="Add deployment sites." onAdd={() => setSiteModal({ open: true, form: emptySite })} />
          ) : null}
          {sites && sites.length > 0 ? (
            <div className="space-y-3">
              <div className="flex justify-end">
                <Button onClick={() => setSiteModal({ open: true, form: emptySite })}>
                  <Plus aria-hidden="true" />
                  New site
                </Button>
              </div>
              {sites.map((site) => (
                <Card key={site.id}>
                  <CardContent className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0 space-y-0.5">
                      <p className="flex items-center gap-2 text-sm font-medium">
                        <MapPin className="size-4 text-muted-foreground" aria-hidden="true" />
                        {site.name}
                      </p>
                      <p className="text-xs text-muted-foreground">
                        {[site.address, site.city, site.region].filter(Boolean).join(" · ") || "No location"} · {site.is_active ? "Active" : "Inactive"}
                      </p>
                    </div>
                    <Button variant="outline" size="sm" onClick={() => setSiteModal({ open: true, form: { ...site } })}>
                      <Pencil aria-hidden="true" />
                      Edit
                    </Button>
                  </CardContent>
                </Card>
              ))}
            </div>
          ) : null}
        </TabsContent>
      </Tabs>

      <Dialog open={programModal.open} onOpenChange={(open) => !open && closeModal()}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{programModal.form.id ? "Edit program" : "New program"}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            {modalError ? <FormError message={modalError} /> : null}
            <div className="space-y-2">
              <Label htmlFor="program-name">Name</Label>
              <Input id="program-name" value={programModal.form.name ?? ""} onChange={(e) => setProgramModal({ ...programModal, form: { ...programModal.form, name: e.target.value } })} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="program-slug">Slug</Label>
              <Input id="program-slug" value={programModal.form.slug ?? ""} onChange={(e) => setProgramModal({ ...programModal, form: { ...programModal.form, slug: e.target.value } })} placeholder="gip" />
            </div>
            <div className="space-y-2">
              <Label htmlFor="program-desc">Description (optional)</Label>
              <Textarea id="program-desc" value={programModal.form.description ?? ""} onChange={(e) => setProgramModal({ ...programModal, form: { ...programModal.form, description: e.target.value } })} />
            </div>
            <SwitchRow label="Active" description="Visible to students" checked={programModal.form.is_active ?? true} onChecked={(checked) => setProgramModal({ ...programModal, form: { ...programModal.form, is_active: checked } })} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={closeModal} disabled={saving}>Cancel</Button>
            <Button onClick={saveProgram} disabled={saving}>{saving ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}Save</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={requirementModal.open} onOpenChange={(open) => !open && closeModal()}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{requirementModal.form.id ? "Edit requirement" : "New requirement"}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            {modalError ? <FormError message={modalError} /> : null}
            <div className="space-y-2">
              <Label htmlFor="req-name">Name</Label>
              <Input id="req-name" value={requirementModal.form.name ?? ""} onChange={(e) => setRequirementModal({ ...requirementModal, form: { ...requirementModal.form, name: e.target.value } })} placeholder="Certificate of Enrollment" />
            </div>
            <div className="space-y-2">
              <Label htmlFor="req-slug">Slug</Label>
              <Input id="req-slug" value={requirementModal.form.slug ?? ""} onChange={(e) => setRequirementModal({ ...requirementModal, form: { ...requirementModal.form, slug: e.target.value } })} placeholder="certificate-of-enrollment" />
            </div>
            <div className="space-y-2">
              <Label htmlFor="req-desc">Description (optional)</Label>
              <Textarea id="req-desc" value={requirementModal.form.description ?? ""} onChange={(e) => setRequirementModal({ ...requirementModal, form: { ...requirementModal.form, description: e.target.value } })} />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="req-types">Allowed file types (optional)</Label>
                <Input id="req-types" value={requirementModal.form.allowed_file_types} onChange={(e) => setRequirementModal({ ...requirementModal, form: { ...requirementModal.form, allowed_file_types: e.target.value } })} placeholder="pdf, jpg, png" />
                <p className="text-xs text-muted-foreground">Comma-separated extensions. Leave blank for any type.</p>
              </div>
              <div className="space-y-2">
                <Label htmlFor="req-size">Max file size (KB, optional)</Label>
                <Input id="req-size" type="number" min={1} value={requirementModal.form.max_file_size} onChange={(e) => setRequirementModal({ ...requirementModal, form: { ...requirementModal.form, max_file_size: e.target.value } })} placeholder="5120" />
              </div>
            </div>
            <SwitchRow label="Active" description="Can be required in cycles" checked={requirementModal.form.is_active ?? true} onChecked={(checked) => setRequirementModal({ ...requirementModal, form: { ...requirementModal.form, is_active: checked } })} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={closeModal} disabled={saving}>Cancel</Button>
            <Button onClick={saveRequirement} disabled={saving}>{saving ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}Save</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={agencyModal.open} onOpenChange={(open) => !open && closeModal()}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{agencyModal.form.id ? "Edit host agency" : "New host agency"}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            {modalError ? <FormError message={modalError} /> : null}
            <div className="space-y-2">
              <Label htmlFor="agency-name">Name</Label>
              <Input id="agency-name" value={agencyModal.form.name ?? ""} onChange={(e) => setAgencyModal({ ...agencyModal, form: { ...agencyModal.form, name: e.target.value } })} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="agency-address">Address (optional)</Label>
              <Input id="agency-address" value={agencyModal.form.address ?? ""} onChange={(e) => setAgencyModal({ ...agencyModal, form: { ...agencyModal.form, address: e.target.value } })} />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="agency-contact">Contact person (optional)</Label>
                <Input id="agency-contact" value={agencyModal.form.contact_person ?? ""} onChange={(e) => setAgencyModal({ ...agencyModal, form: { ...agencyModal.form, contact_person: e.target.value } })} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="agency-number">Contact number (optional)</Label>
                <Input id="agency-number" value={agencyModal.form.contact_number ?? ""} onChange={(e) => setAgencyModal({ ...agencyModal, form: { ...agencyModal.form, contact_number: e.target.value } })} />
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="agency-email">Email (optional)</Label>
              <Input id="agency-email" type="email" value={agencyModal.form.email ?? ""} onChange={(e) => setAgencyModal({ ...agencyModal, form: { ...agencyModal.form, email: e.target.value } })} />
            </div>
            <SwitchRow label="Active" description="Available for deployment" checked={agencyModal.form.is_active ?? true} onChecked={(checked) => setAgencyModal({ ...agencyModal, form: { ...agencyModal.form, is_active: checked } })} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={closeModal} disabled={saving}>Cancel</Button>
            <Button onClick={saveAgency} disabled={saving}>{saving ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}Save</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={siteModal.open} onOpenChange={(open) => !open && closeModal()}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{siteModal.form.id ? "Edit deployment site" : "New deployment site"}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            {modalError ? <FormError message={modalError} /> : null}
            <div className="space-y-2">
              <Label htmlFor="site-name">Name</Label>
              <Input id="site-name" value={siteModal.form.name ?? ""} onChange={(e) => setSiteModal({ ...siteModal, form: { ...siteModal.form, name: e.target.value } })} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="site-address">Address (optional)</Label>
              <Input id="site-address" value={siteModal.form.address ?? ""} onChange={(e) => setSiteModal({ ...siteModal, form: { ...siteModal.form, address: e.target.value } })} />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="site-city">City (optional)</Label>
                <Input id="site-city" value={siteModal.form.city ?? ""} onChange={(e) => setSiteModal({ ...siteModal, form: { ...siteModal.form, city: e.target.value } })} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="site-region">Region (optional)</Label>
                <Input id="site-region" value={siteModal.form.region ?? ""} onChange={(e) => setSiteModal({ ...siteModal, form: { ...siteModal.form, region: e.target.value } })} />
              </div>
            </div>
            <SwitchRow label="Active" description="Available for deployment" checked={siteModal.form.is_active ?? true} onChecked={(checked) => setSiteModal({ ...siteModal, form: { ...siteModal.form, is_active: checked } })} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={closeModal} disabled={saving}>Cancel</Button>
            <Button onClick={saveSite} disabled={saving}>{saving ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}Save</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={cycleModal.open} onOpenChange={(open) => !open && closeModal()}>
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{cycleModal.form.id ? "Edit program cycle" : "New program cycle"}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            {modalError ? <FormError message={modalError} /> : null}
            <div className="space-y-2">
              <Label htmlFor="cycle-program">Program</Label>
              <Select value={cycleModal.form.program_id ? String(cycleModal.form.program_id) : ""} onValueChange={(value) => setCycleModal({ ...cycleModal, form: { ...cycleModal.form, program_id: Number(value) } })}>
                <SelectTrigger id="cycle-program" className="w-full">
                  <SelectValue placeholder="Select program" />
                </SelectTrigger>
                <SelectContent>
                  {(programs ?? []).map((program) => (
                    <SelectItem key={program.id} value={String(program.id)}>{program.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="cycle-name">Cycle name</Label>
              <Input id="cycle-name" value={cycleModal.form.name} onChange={(e) => setCycleModal({ ...cycleModal, form: { ...cycleModal.form, name: e.target.value } })} placeholder="2026 Summer Batch" />
            </div>
            <div className="space-y-2">
              <Label htmlFor="cycle-desc">Description (optional)</Label>
              <Textarea id="cycle-desc" value={cycleModal.form.description ?? ""} onChange={(e) => setCycleModal({ ...cycleModal, form: { ...cycleModal.form, description: e.target.value } })} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="cycle-slots">Total slots</Label>
              <Input id="cycle-slots" type="number" min={1} value={cycleModal.form.total_slots || ""} onChange={(e) => setCycleModal({ ...cycleModal, form: { ...cycleModal.form, total_slots: Number(e.target.value) } })} />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="cycle-start">Application start</Label>
                <Input id="cycle-start" type="date" value={cycleModal.form.application_start} onChange={(e) => setCycleModal({ ...cycleModal, form: { ...cycleModal.form, application_start: e.target.value } })} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="cycle-deadline">Application deadline</Label>
                <Input id="cycle-deadline" type="date" value={cycleModal.form.application_deadline} onChange={(e) => setCycleModal({ ...cycleModal, form: { ...cycleModal.form, application_deadline: e.target.value } })} />
              </div>
            </div>
            <div className="rounded-lg border bg-muted/30 p-3">
              <p className="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">Deployment period (optional)</p>
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="cycle-deploy-start">Deployment start</Label>
                  <Input id="cycle-deploy-start" type="date" value={cycleModal.form.deployment_start ?? ""} onChange={(e) => setCycleModal({ ...cycleModal, form: { ...cycleModal.form, deployment_start: e.target.value } })} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="cycle-deploy-end">Deployment end</Label>
                  <Input id="cycle-deploy-end" type="date" value={cycleModal.form.deployment_end ?? ""} onChange={(e) => setCycleModal({ ...cycleModal, form: { ...cycleModal.form, deployment_end: e.target.value } })} />
                </div>
              </div>
            </div>
            <div className="space-y-2">
              <Label>Required documents</Label>
              {(requirements ?? []).length === 0 ? (
                <p className="text-sm text-muted-foreground">No requirements exist yet. Create them in the Requirements tab.</p>
              ) : (
                <div className="grid gap-2 sm:grid-cols-2">
                  {(requirements ?? []).map((requirement) => {
                    const checked = cycleModal.form.requirements.includes(requirement.id)
                    return (
                      <label
                        key={requirement.id}
                        className={`flex cursor-pointer items-center gap-2 rounded-md border p-2.5 text-sm transition-colors ${checked ? "border-primary bg-primary/5" : "hover:bg-muted"}`}
                      >
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={(e) =>
                            setCycleModal({
                              ...cycleModal,
                              form: {
                                ...cycleModal.form,
                                requirements: e.target.checked
                                  ? [...cycleModal.form.requirements, requirement.id]
                                  : cycleModal.form.requirements.filter((id) => id !== requirement.id),
                              },
                            })
                          }
                          className="size-4 accent-primary"
                        />
                        {requirement.name}
                      </label>
                    )
                  })}
                </div>
              )}
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={closeModal} disabled={saving}>Cancel</Button>
            <Button onClick={saveCycle} disabled={saving}>{saving ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}{cycleModal.form.id ? "Save" : "Create"}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <ConfirmDialog
        open={deleteTarget !== null}
        onOpenChange={(open) => !open && setDeleteTarget(null)}
        title="Delete program cycle?"
        description={
          deleteTarget
            ? `${deleteTarget.name} will be permanently removed. This cannot be undone.`
            : undefined
        }
        confirmLabel="Delete cycle"
        cancelLabel="Cancel"
        destructive
        icon={Trash2}
        loading={deleting}
        onConfirm={() => void confirmDeleteCycle()}
      />

      <ConfirmDialog
        open={deleteRequirementTarget !== null}
        onOpenChange={(open) => !open && setDeleteRequirementTarget(null)}
        title="Delete requirement?"
        description={
          deleteRequirementTarget
            ? `${deleteRequirementTarget.name} will be permanently removed from the catalog. This cannot be undone.`
            : undefined
        }
        confirmLabel="Delete requirement"
        cancelLabel="Cancel"
        destructive
        icon={Trash2}
        loading={deletingRequirement}
        onConfirm={() => void confirmDeleteRequirement()}
      />

      {requirementsCycle ? (
        <CycleRequirementsDialog
          cycle={requirementsCycle}
          open
          onOpenChange={(open) => !open && setRequirementsCycle(null)}
          onChanged={() => void Promise.all([reloadPrograms(), reloadCycles()])}
        />
      ) : null}
    </div>
  )
}

function Toolbar({
  placeholder,
  value,
  onChange,
  filter,
  action,
}: {
  placeholder: string
  value: string
  onChange: (value: string) => void
  filter?: React.ReactNode
  action?: React.ReactNode
}) {
  return (
    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
      <div className="relative flex-1">
        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
        <Input
          value={value}
          onChange={(event) => onChange(event.target.value)}
          placeholder={placeholder}
          className="pl-8"
          aria-label={placeholder}
        />
      </div>
      {filter}
      {action ? <div className="shrink-0">{action}</div> : null}
    </div>
  )
}

function CycleRow({
  cycle,
  busy,
  onEdit,
  onManageRequirements,
  onTogglePublish,
  onDelete,
}: {
  cycle: ProgramCycle
  busy: boolean
  onEdit: () => void
  onManageRequirements: () => void
  onTogglePublish: () => void
  onDelete: () => void
}) {
  const isDraft = cycle.status === "draft"
  return (
    <div className="rounded-lg border p-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="min-w-0 space-y-1.5">
          <div className="flex flex-wrap items-center gap-2">
            <p className="text-sm font-semibold">{cycle.name}</p>
            <CycleStatusBadge status={cycle.status} label={cycle.status_label} />
            {cycle.program ? (
              <span className="text-xs text-muted-foreground">· {cycle.program.name}</span>
            ) : null}
          </div>
          {cycle.description ? (
            <p className="line-clamp-2 text-sm text-muted-foreground">{cycle.description}</p>
          ) : null}
          <div className="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-muted-foreground">
            <span className="inline-flex items-center gap-1">
              <CalendarRange className="size-3.5" aria-hidden="true" />
              Applications: {formatDate(cycle.application_start)} – {formatDate(cycle.application_deadline)}
            </span>
            <span className="inline-flex items-center gap-1">
              <Building2 className="size-3.5" aria-hidden="true" />
              Deployment: {cycle.deployment_start || cycle.deployment_end ? `${formatDate(cycle.deployment_start)} – ${formatDate(cycle.deployment_end)}` : "Not set"}
            </span>
            <span className="inline-flex items-center gap-1">
              <Users className="size-3.5" aria-hidden="true" />
              {cycle.slots_remaining} of {cycle.total_slots} slots left
            </span>
            {cycle.requirements && cycle.requirements.length > 0 ? (
              <span>
                {cycle.requirements.length} required document{cycle.requirements.length === 1 ? "" : "s"}
              </span>
            ) : null}
          </div>
        </div>
        <div className="flex shrink-0 items-center gap-1.5">
          <Button variant="outline" size="sm" onClick={onManageRequirements}>
            <ShieldCheck aria-hidden="true" />
            Requirements
          </Button>
          <Button variant="outline" size="sm" onClick={onEdit}>
            <Pencil aria-hidden="true" />
            Edit
          </Button>
          <Button variant="outline" size="sm" onClick={onTogglePublish} disabled={busy}>
            {busy ? <Loader2 className="animate-spin" aria-hidden="true" /> : isDraft ? <Rocket aria-hidden="true" /> : <RotateCcw aria-hidden="true" />}
            {isDraft ? "Publish" : "Unpublish"}
          </Button>
          <Button variant="ghost" size="icon-sm" onClick={onDelete} disabled={busy} aria-label={`Delete ${cycle.name}`}>
            <Trash2 className="text-destructive" aria-hidden="true" />
          </Button>
        </div>
      </div>
    </div>
  )
}

function SwitchRow({
  label,
  description,
  checked,
  onChecked,
}: {
  label: string
  description: string
  checked: boolean
  onChecked: (checked: boolean) => void
}) {
  return (
    <div className="flex items-center justify-between rounded-lg border p-3">
      <div className="space-y-0.5">
        <Label className="text-sm font-medium">{label}</Label>
        <p className="text-xs text-muted-foreground">{description}</p>
      </div>
      <Switch checked={checked} onCheckedChange={onChecked} />
    </div>
  )
}

function FormError({ message }: { message: string }) {
  return (
    <p role="alert" className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
      {message}
    </p>
  )
}
