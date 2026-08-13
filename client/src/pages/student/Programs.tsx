import { useMemo, useState } from "react"
import { useNavigate } from "react-router-dom"
import {
  CalendarRange,
  Clock,
  FileText,
  Loader2,
  Rocket,
  Users,
} from "lucide-react"
import { fetchPrograms } from "@/api/programs"
import { createApplication } from "@/api/student"
import { useAsync } from "@/lib/useAsync"
import { ApiError } from "@/lib/api"
import { formatDate, formatMaxUploadSize } from "@/lib/format"
import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Badge } from "@/components/ui/badge"
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
import { EmptyState } from "@/components/EmptyState"
import { CycleStatusBadge } from "@/components/StatusBadge"
import { FullPageLoader } from "@/components/FullPageLoader"
import { useToast } from "@/toast/useToast"
import type { Program, ProgramCycle } from "@/types/api"

function AvailabilityBadge({ cycle }: { cycle: ProgramCycle }) {
  if (cycle.is_accepting_applications) {
    return (
      <Badge className="border-transparent bg-emerald-100 text-emerald-700">
        Applications open
      </Badge>
    )
  }
  if (cycle.status === "upcoming") {
    return (
      <Badge className="border-transparent bg-amber-100 text-amber-800">
        Not yet open
      </Badge>
    )
  }
  return (
    <Badge className="border-transparent bg-zinc-100 text-zinc-500">
      Applications closed
    </Badge>
  )
}

function SlotBar({ cycle }: { cycle: ProgramCycle }) {
  const used = cycle.total_slots - cycle.slots_remaining
  const pct = cycle.total_slots > 0 ? Math.round((used / cycle.total_slots) * 100) : 0
  return (
    <div className="space-y-1">
      <div className="flex items-center justify-between text-xs text-muted-foreground">
        <span className="inline-flex items-center gap-1">
          <Users className="size-3.5" aria-hidden="true" />
          {cycle.slots_remaining} of {cycle.total_slots} slots left
        </span>
        <span>{pct}% filled</span>
      </div>
      <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
        <div
          className={cn(
            "h-full rounded-full transition-all",
            pct >= 100 ? "bg-red-500" : "bg-primary",
          )}
          style={{ width: `${Math.min(pct, 100)}%` }}
        />
      </div>
    </div>
  )
}

function CycleCard({
  cycle,
  onApply,
}: {
  cycle: ProgramCycle
  onApply: () => void
}) {
  const accepting = cycle.is_accepting_applications
  const upcoming = cycle.status === "upcoming"
  return (
    <div className="flex flex-col gap-3 rounded-lg border p-4">
      <div className="flex flex-wrap items-center gap-2">
        <p className="text-sm font-semibold">{cycle.name}</p>
        <CycleStatusBadge status={cycle.status} label={cycle.status_label} />
        <span className="ml-auto">
          <AvailabilityBadge cycle={cycle} />
        </span>
      </div>
      {cycle.description ? (
        <p className="line-clamp-2 text-sm text-muted-foreground">{cycle.description}</p>
      ) : null}
      <div className="space-y-2 text-xs text-muted-foreground">
        <p className="inline-flex items-center gap-1.5">
          <CalendarRange className="size-3.5 shrink-0" aria-hidden="true" />
          <span>
            Applications: {formatDate(cycle.application_start)} –{" "}
            {formatDate(cycle.application_deadline)}
          </span>
        </p>
        <p className="inline-flex items-center gap-1.5">
          <Rocket className="size-3.5 shrink-0" aria-hidden="true" />
          <span>
            Deployment: {formatDate(cycle.deployment_start)} –{" "}
            {formatDate(cycle.deployment_end)}
          </span>
        </p>
        <SlotBar cycle={cycle} />
        {cycle.requirements && cycle.requirements.length > 0 ? (
          <p className="inline-flex items-center gap-1.5">
            <FileText className="size-3.5 shrink-0" aria-hidden="true" />
            {cycle.requirements.length} required document
            {cycle.requirements.length === 1 ? "" : "s"}
          </p>
        ) : null}
      </div>
      <Button
        onClick={onApply}
        disabled={!accepting}
        className="mt-auto w-full"
      >
        {accepting
          ? "Apply now"
          : upcoming
            ? "Not yet open"
            : "Applications closed"}
      </Button>
    </div>
  )
}

export function StudentProgramsPage() {
  const { toast } = useToast()
  const navigate = useNavigate()
  const { data: programs, loading, error } = useAsync(fetchPrograms)
  const [selected, setSelected] = useState<{ program: Program; cycle: ProgramCycle } | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState<string | null>(null)

  const openPrograms = useMemo(
    () => (programs ?? []).filter((program) => program.is_active),
    [programs],
  )

  async function handleApply() {
    if (!selected) return
    setSubmitting(true)
    setSubmitError(null)
    try {
      const application = await createApplication(selected.cycle.id)
      toast({
        title: "Application started",
        description: "Your draft application has been created.",
        variant: "success",
      })
      setSelected(null)
      navigate(`/student/applications/${application.id}`)
    } catch (err) {
      const message =
        err instanceof ApiError
          ? err.message
          : "Unable to start your application. Please try again."
      setSubmitError(message)
      toast({ title: "Unable to apply", description: message, variant: "error" })
    } finally {
      setSubmitting(false)
    }
  }

  if (loading && !programs) return <FullPageLoader />

  return (
    <div className="space-y-6">
      <PageHeader
        title="Browse programs"
        description="Explore DOLE internship and employment programs that are open for applications."
      />

      {error ? (
        <p
          role="alert"
          className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {error.message}
        </p>
      ) : null}

      {openPrograms.length === 0 ? (
        <EmptyState
          icon={FileText}
          title="No programs available"
          description="There are no open programs right now. Please check back later."
        />
      ) : (
        <div className="grid gap-4 md:grid-cols-2">
          {openPrograms.map((program) => {
            const cycles = program.cycles ?? []
            return (
              <Card key={program.id} className="flex flex-col">
                <CardHeader>
                  <div className="flex items-start justify-between gap-3">
                    <div className="space-y-1">
                      <CardTitle>{program.name}</CardTitle>
                      <CardDescription>
                        {program.description ?? "No description provided."}
                      </CardDescription>
                    </div>
                    <span className="shrink-0 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
                      {cycles.length} cycle{cycles.length === 1 ? "" : "s"}
                    </span>
                  </div>
                </CardHeader>
                <CardContent className="flex flex-1 flex-col gap-3">
                  {cycles.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                      No cycles available for this program yet.
                    </p>
                  ) : (
                    cycles.map((cycle) => (
                      <CycleCard
                        key={cycle.id}
                        cycle={cycle}
                        onApply={() => setSelected({ program, cycle })}
                      />
                    ))
                  )}
                </CardContent>
              </Card>
            )
          })}
        </div>
      )}

      <Dialog open={selected !== null} onOpenChange={(open) => !open && setSelected(null)}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Start your application</DialogTitle>
            <DialogDescription>
              {selected?.program.name} – {selected?.cycle.name}
            </DialogDescription>
          </DialogHeader>

          {selected ? (
            <div className="space-y-4">
              <div className="grid gap-3 rounded-lg border bg-muted/40 p-4 text-sm sm:grid-cols-2">
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Application window
                  </p>
                  <p className="mt-0.5">
                    {formatDate(selected.cycle.application_start)} –{" "}
                    {formatDate(selected.cycle.application_deadline)}
                  </p>
                </div>
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Available slots
                  </p>
                  <p className="mt-0.5">
                    {selected.cycle.slots_remaining} of {selected.cycle.total_slots}
                  </p>
                </div>
              </div>

              <div className="grid gap-3 rounded-lg border bg-muted/40 p-4 text-sm">
                <p className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                  <Clock className="size-3.5" aria-hidden="true" />
                  Deployment period
                </p>
                <p>
                  {formatDate(selected.cycle.deployment_start)} –{" "}
                  {formatDate(selected.cycle.deployment_end)}
                </p>
              </div>

              <div className="space-y-2">
                <p className="flex items-center gap-1.5 text-sm font-medium">
                  <FileText className="size-4" aria-hidden="true" />
                  Requirements
                </p>
                {selected.cycle.requirements && selected.cycle.requirements.length > 0 ? (
                  <ul className="grid gap-2">
                    {selected.cycle.requirements.map((requirement) => {
                      const fileTypes = (requirement.allowed_file_types ?? []).join(", ")
                      return (
                        <li key={requirement.id} className="rounded-md border px-2.5 py-2">
                          <div className="flex items-center justify-between gap-2">
                            <p className="text-sm font-medium">{requirement.name}</p>
                            <span
                              className={cn(
                                "shrink-0 rounded-full px-2 py-0.5 text-xs font-medium",
                                requirement.is_required
                                  ? "bg-amber-100 text-amber-700"
                                  : "bg-muted text-muted-foreground",
                              )}
                            >
                              {requirement.is_required ? "Required" : "Optional"}
                            </span>
                          </div>
                          <p className="mt-1 text-xs text-muted-foreground">
                            {fileTypes ? `Allowed: ${fileTypes}` : "Any file type"} · Max{" "}
                            {formatMaxUploadSize(requirement.max_file_size)}
                          </p>
                        </li>
                      )
                    })}
                  </ul>
                ) : (
                  <p className="text-sm text-muted-foreground">
                    No documents required for this cycle.
                  </p>
                )}
              </div>

              {submitError ? (
                <p
                  role="alert"
                  className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                >
                  {submitError}
                </p>
              ) : null}
            </div>
          ) : null}

          <DialogFooter>
            <Button variant="outline" onClick={() => setSelected(null)} disabled={submitting}>
              Cancel
            </Button>
            <Button onClick={handleApply} disabled={submitting}>
              {submitting ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
              {submitting ? "Starting..." : "Start application"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
