import { apiRequest, requestCsrfCookie } from "@/lib/api"
import type {
  ApiEnvelope,
  Application,
  ApplicationDocument,
  DeploymentAssignment,
  DeploymentSite,
  HostAgency,
  Paginated,
  Program,
  ProgramCycle,
  Requirement,
  StaffApplicationFilters,
  StaffApplicationListResponse,
  StaffDashboardData,
} from "@/types/api"

async function unwrap<T>(response: ApiEnvelope<T>): Promise<T> {
  return response.data
}

export async function fetchStaffDashboard(): Promise<StaffDashboardData> {
  const response = await apiRequest<ApiEnvelope<StaffDashboardData>>("/api/staff/dashboard")
  return unwrap(response)
}

function serializeQuery(query: StaffApplicationFilters): URLSearchParams {
  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== null && value !== "") {
      params.set(key, String(value))
    }
  }
  return params
}

export async function fetchReviewQueue(query: StaffApplicationFilters = {}): Promise<StaffApplicationListResponse> {
  const qs = serializeQuery(query).toString()
  const response = await apiRequest<StaffApplicationListResponse>(
    `/api/staff/applications${qs ? `?${qs}` : ""}`,
  )
  return response
}

export async function fetchStaffApplication(id: number): Promise<Application> {
  const response = await apiRequest<ApiEnvelope<Application>>(`/api/staff/applications/${id}`)
  return unwrap(response)
}

export type ReviewAction =
  | "start_review"
  | "request_documents"
  | "approve"
  | "reject"
  | "schedule_deployment"
  | "deploy"
  | "complete"

export async function reviewApplication(
  id: number,
  action: ReviewAction,
  remarks?: string,
): Promise<Application> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<Application>>(`/api/staff/applications/${id}/review`, {
    method: "POST",
    body: JSON.stringify({ action, remarks }),
  })
  return unwrap(response)
}

export async function verifyDocument(
  applicationId: number,
  documentId: number,
  status: "verified" | "rejected",
  rejectionReason?: string,
): Promise<ApplicationDocument> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<ApplicationDocument>>(
    `/api/staff/applications/${applicationId}/documents/${documentId}/verify`,
    {
      method: "PUT",
      body: JSON.stringify({
        verification_status: status,
        ...(status === "rejected" ? { rejection_reason: rejectionReason } : {}),
      }),
    },
  )
  return unwrap(response)
}

export async function fetchDeployments(page = 1): Promise<Paginated<DeploymentAssignment>> {
  const response = await apiRequest<Paginated<DeploymentAssignment>>(
    `/api/staff/deployments?page=${page}`,
  )
  return response
}

export interface DeploymentPayload {
  application_id: number
  host_agency_id: number
  deployment_site_id?: number | null
  position?: string
  start_date: string
  end_date?: string | null
  status?: "scheduled" | "active" | "completed" | "cancelled"
  remarks?: string
}

export async function createDeployment(payload: DeploymentPayload): Promise<DeploymentAssignment> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<DeploymentAssignment>>("/api/staff/deployments", {
    method: "POST",
    body: JSON.stringify(payload),
  })
  return unwrap(response)
}

export async function updateDeploymentStatus(
  id: number,
  status: "scheduled" | "active" | "completed" | "cancelled",
): Promise<DeploymentAssignment> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<DeploymentAssignment>>(
    `/api/staff/deployments/${id}`,
    { method: "PATCH", body: JSON.stringify({ status }) },
  )
  return unwrap(response)
}

export async function fetchProgramsCatalog(): Promise<Program[]> {
  const response = await apiRequest<ApiEnvelope<Program[]>>("/api/staff/catalog/programs")
  return response.data
}

export async function fetchCyclesCatalog(): Promise<ProgramCycle[]> {
  const response = await apiRequest<ApiEnvelope<ProgramCycle[]>>("/api/staff/catalog/cycles")
  return response.data
}

export async function fetchRequirementsCatalog(): Promise<Requirement[]> {
  const response = await apiRequest<ApiEnvelope<Requirement[]>>("/api/staff/catalog/requirements")
  return response.data
}

export async function fetchHostAgencies(): Promise<HostAgency[]> {
  const response = await apiRequest<ApiEnvelope<HostAgency[]>>("/api/staff/catalog/host-agencies")
  return response.data
}

export async function fetchDeploymentSites(): Promise<DeploymentSite[]> {
  const response = await apiRequest<ApiEnvelope<DeploymentSite[]>>("/api/staff/catalog/deployment-sites")
  return response.data
}

export interface CatalogPayload {
  name: string
  description?: string
  slug?: string
  is_active?: boolean
}

export async function createProgram(payload: CatalogPayload): Promise<Program> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<Program>>("/api/staff/catalog/programs", {
    method: "POST",
    body: JSON.stringify(payload),
  })
  return unwrap(response)
}

export async function updateProgram(id: number, payload: CatalogPayload): Promise<Program> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<Program>>(`/api/staff/catalog/programs/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  })
  return unwrap(response)
}

export interface CyclePayload {
  program_id: number
  name: string
  description?: string
  total_slots: number
  application_start: string
  application_deadline: string
  deployment_start?: string | null
  deployment_end?: string | null
  requirements?: number[]
}

export async function createCycle(payload: CyclePayload): Promise<ProgramCycle> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<ProgramCycle>>("/api/staff/catalog/cycles", {
    method: "POST",
    body: JSON.stringify(payload),
  })
  return unwrap(response)
}

export async function fetchCycleCatalog(id: number): Promise<ProgramCycle> {
  const response = await apiRequest<ApiEnvelope<ProgramCycle>>(
    `/api/staff/catalog/cycles/${id}`,
  )
  return unwrap(response)
}

export async function updateCycle(id: number, payload: CyclePayload): Promise<ProgramCycle> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<ProgramCycle>>(
    `/api/staff/catalog/cycles/${id}`,
    { method: "PUT", body: JSON.stringify(payload) },
  )
  return unwrap(response)
}

export async function publishCycle(id: number): Promise<ProgramCycle> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<ProgramCycle>>(
    `/api/staff/catalog/cycles/${id}/publish`,
    { method: "POST" },
  )
  return unwrap(response)
}

export async function unpublishCycle(id: number): Promise<ProgramCycle> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<ProgramCycle>>(
    `/api/staff/catalog/cycles/${id}/unpublish`,
    { method: "POST" },
  )
  return unwrap(response)
}

export async function deleteCycle(id: number): Promise<void> {
  await requestCsrfCookie()
  await apiRequest(`/api/staff/catalog/cycles/${id}`, { method: "DELETE" })
}

export interface RequirementPayload {
  name: string
  slug: string
  description?: string | null
  is_active?: boolean
  is_required?: boolean
  allowed_file_types?: string[] | null
  max_file_size?: number | null
}

export async function createRequirement(payload: RequirementPayload): Promise<Requirement> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<Requirement>>("/api/staff/catalog/requirements", {
    method: "POST",
    body: JSON.stringify(payload),
  })
  return unwrap(response)
}

export async function updateRequirement(
  id: number,
  payload: RequirementPayload,
): Promise<Requirement> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<Requirement>>(
    `/api/staff/catalog/requirements/${id}`,
    { method: "PUT", body: JSON.stringify(payload) },
  )
  return unwrap(response)
}

export async function deleteRequirement(id: number): Promise<void> {
  await requestCsrfCookie()
  await apiRequest(`/api/staff/catalog/requirements/${id}`, { method: "DELETE" })
}

export async function fetchCycleRequirements(cycleId: number): Promise<Requirement[]> {
  const response = await apiRequest<ApiEnvelope<Requirement[]>>(
    `/api/staff/catalog/cycles/${cycleId}/requirements`,
  )
  return response.data
}

export interface CycleRequirementPayload {
  requirement_id?: number
  name?: string
  slug?: string
  description?: string | null
  is_active?: boolean
  is_required?: boolean
  allowed_file_types?: string[] | null
  max_file_size?: number | null
}

export async function createCycleRequirement(
  cycleId: number,
  payload: CycleRequirementPayload,
): Promise<Requirement> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<Requirement>>(
    `/api/staff/catalog/cycles/${cycleId}/requirements`,
    { method: "POST", body: JSON.stringify(payload) },
  )
  return unwrap(response)
}

export async function updateCycleRequirement(
  cycleId: number,
  requirementId: number,
  payload: RequirementPayload,
): Promise<Requirement> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<Requirement>>(
    `/api/staff/catalog/cycles/${cycleId}/requirements/${requirementId}`,
    { method: "PUT", body: JSON.stringify(payload) },
  )
  return unwrap(response)
}

export async function removeCycleRequirement(
  cycleId: number,
  requirementId: number,
): Promise<void> {
  await requestCsrfCookie()
  await apiRequest(`/api/staff/catalog/cycles/${cycleId}/requirements/${requirementId}`, {
    method: "DELETE",
  })
}

export async function createHostAgency(payload: Partial<HostAgency>): Promise<HostAgency> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<HostAgency>>("/api/staff/catalog/host-agencies", {
    method: "POST",
    body: JSON.stringify(payload),
  })
  return unwrap(response)
}

export async function updateHostAgency(
  id: number,
  payload: Partial<HostAgency>,
): Promise<HostAgency> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<HostAgency>>(
    `/api/staff/catalog/host-agencies/${id}`,
    { method: "PUT", body: JSON.stringify(payload) },
  )
  return unwrap(response)
}

export async function createDeploymentSite(payload: Partial<DeploymentSite>): Promise<DeploymentSite> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<DeploymentSite>>(
    "/api/staff/catalog/deployment-sites",
    { method: "POST", body: JSON.stringify(payload) },
  )
  return unwrap(response)
}

export async function updateDeploymentSite(
  id: number,
  payload: Partial<DeploymentSite>,
): Promise<DeploymentSite> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<DeploymentSite>>(
    `/api/staff/catalog/deployment-sites/${id}`,
    { method: "PUT", body: JSON.stringify(payload) },
  )
  return unwrap(response)
}
