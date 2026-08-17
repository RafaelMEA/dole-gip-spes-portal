import { Badge } from "@/components/ui/badge"
import type {
  ApplicationStatus,
  DeploymentStatus,
  DocumentVerificationStatus,
  ProgramCycleStatus,
} from "@/types/api"

const APPLICATION_STYLES: Record<ApplicationStatus, string> = {
  draft: "bg-zinc-100 text-zinc-700 border-transparent",
  submitted: "bg-sky-100 text-sky-700 border-transparent",
  under_review: "bg-indigo-100 text-indigo-700 border-transparent",
  documents_incomplete: "bg-amber-100 text-amber-800 border-transparent",
  documents_verified: "bg-teal-100 text-teal-700 border-transparent",
  returned_for_correction: "bg-amber-100 text-amber-800 border-transparent",
  approved: "bg-emerald-100 text-emerald-700 border-transparent",
  for_deployment: "bg-violet-100 text-violet-700 border-transparent",
  deployed: "bg-blue-100 text-blue-700 border-transparent",
  completed: "bg-emerald-100 text-emerald-700 border-transparent",
  rejected: "bg-red-100 text-red-700 border-transparent",
  withdrawn: "bg-zinc-100 text-zinc-500 border-transparent",
}

const CYCLE_STYLES: Record<ProgramCycleStatus, string> = {
  draft: "bg-zinc-100 text-zinc-600 border-transparent",
  upcoming: "bg-amber-100 text-amber-800 border-transparent",
  open: "bg-emerald-100 text-emerald-700 border-transparent",
  closed: "bg-zinc-100 text-zinc-500 border-transparent",
  archived: "bg-zinc-200 text-zinc-600 border-transparent",
}

const DOCUMENT_STYLES: Record<DocumentVerificationStatus, string> = {
  pending: "bg-amber-100 text-amber-800 border-transparent",
  verified: "bg-emerald-100 text-emerald-700 border-transparent",
  rejected: "bg-red-100 text-red-700 border-transparent",
}

const DEPLOYMENT_STYLES: Record<DeploymentStatus, string> = {
  scheduled: "bg-amber-100 text-amber-800 border-transparent",
  active: "bg-blue-100 text-blue-700 border-transparent",
  completed: "bg-emerald-100 text-emerald-700 border-transparent",
  cancelled: "bg-red-100 text-red-700 border-transparent",
}

export function ApplicationStatusBadge({
  status,
  label,
}: {
  status: ApplicationStatus
  label?: string
}) {
  return <Badge className={APPLICATION_STYLES[status]}>{label ?? status.replaceAll("_", " ")}</Badge>
}

export function CycleStatusBadge({ status, label }: { status: ProgramCycleStatus; label?: string }) {
  return <Badge className={CYCLE_STYLES[status]}>{label ?? status}</Badge>
}

export function DocumentStatusBadge({
  status,
  label,
}: {
  status: DocumentVerificationStatus
  label?: string
}) {
  return <Badge className={DOCUMENT_STYLES[status]}>{label ?? status}</Badge>
}

export function DeploymentStatusBadge({
  status,
  label,
}: {
  status: DeploymentStatus
  label?: string
}) {
  return <Badge className={DEPLOYMENT_STYLES[status]}>{label ?? status}</Badge>
}
