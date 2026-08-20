import { apiRequest, requestCsrfCookie } from "@/lib/api"
import type {
  ApiEnvelope,
  Application,
  ApplicationDocument,
  AssignmentFilters,
  DeploymentAssignment,
  DeploymentOptions,
  DeploymentSite,
  DeploymentSiteFilters,
  DeploymentSlot,
  DeploymentSlotFilters,
  DocumentVerificationAction,
  DocumentVerificationRequest,
  HostAgency,
  HostAgencyFilters,
  HostAgencyType,
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
  | "return_for_correction"
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
  status: DocumentVerificationAction,
  rejectionReason?: string,
): Promise<ApplicationDocument> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<ApplicationDocument>>(
    `/api/staff/applications/${applicationId}/documents/${documentId}/verification`,
    {
      method: "PATCH",
      body: JSON.stringify({
        verification_status: status,
        ...(status === "rejected" ? { rejection_reason: rejectionReason } : {}),
      } satisfies DocumentVerificationRequest),
    },
  )
  return unwrap(response)
}

export async function fetchDeployments(page = 1, filters: AssignmentFilters = {}): Promise<Paginated<DeploymentAssignment>> {
  const params = new URLSearchParams()
  params.set("page", String(page))
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== null && value !== "" && value !== "all") {
      params.set(key, String(value))
    }
  }
  const qs = params.toString()
  const response = await apiRequest<Paginated<DeploymentAssignment>>(
    `/api/staff/deployments?${qs}`,
  )
  return response
}

export async function fetchDeployment(id: number): Promise<DeploymentAssignment> {
  const response = await apiRequest<ApiEnvelope<DeploymentAssignment>>(`/api/staff/deployments/${id}`)
  return unwrap(response)
}

export async function fetchDeploymentOptions(applicationId: number): Promise<DeploymentOptions> {
  const response = await apiRequest<ApiEnvelope<DeploymentOptions>>(
    `/api/staff/applications/${applicationId}/deployment-options`,
  )
  return unwrap(response)
}

export async function assignDeployment(applicationId: number, deploymentSlotId: number): Promise<DeploymentAssignment> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<DeploymentAssignment>>(
    `/api/staff/applications/${applicationId}/assign`,
    {
      method: "POST",
      body: JSON.stringify({ deployment_slot_id: deploymentSlotId }),
    },
  )
  return unwrap(response)
}

export async function cancelDeployment(assignmentId: number, remarks?: string): Promise<DeploymentAssignment> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<DeploymentAssignment>>(
    `/api/staff/deployments/${assignmentId}/cancel`,
    {
      method: "PATCH",
      body: JSON.stringify({ remarks }),
    },
  )
  return unwrap(response)
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

export async function fetchHostAgencies(filters: HostAgencyFilters = {}): Promise<Paginated<HostAgency>> {
  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== null && value !== "") {
      params.set(key, String(value))
    }
  }
  const qs = params.toString()
  const response = await apiRequest<Paginated<HostAgency>>(
    `/api/staff/catalog/host-agencies${qs ? `?${qs}` : ""}`,
  )
  return response
}

export async function fetchHostAgency(id: number): Promise<HostAgency> {
  const response = await apiRequest<ApiEnvelope<HostAgency>>(`/api/staff/catalog/host-agencies/${id}`)
  return unwrap(response)
}

export async function fetchDeploymentSites(filters: DeploymentSiteFilters = {}): Promise<Paginated<DeploymentSite>> {
  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== null && value !== "") {
      params.set(key, String(value))
    }
  }
  const qs = params.toString()
  const response = await apiRequest<Paginated<DeploymentSite>>(
    `/api/staff/catalog/deployment-sites${qs ? `?${qs}` : ""}`,
  )
  return response
}

export async function fetchDeploymentSitesAll(): Promise<DeploymentSite[]> {
  const response = await apiRequest<Paginated<DeploymentSite>>("/api/staff/catalog/deployment-sites?per_page=100")
  return response.data
}

export async function fetchDeploymentSite(id: number): Promise<DeploymentSite> {
  const response = await apiRequest<ApiEnvelope<DeploymentSite>>(`/api/staff/catalog/deployment-sites/${id}`)
  return unwrap(response)
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

export interface HostAgencyPayload {
  name: string
  agency_type?: HostAgencyType
  address?: string
  contact_person?: string
  contact_number?: string
  email?: string
  is_active?: boolean
}

export async function createHostAgency(payload: HostAgencyPayload): Promise<HostAgency> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<HostAgency>>("/api/staff/catalog/host-agencies", {
    method: "POST",
    body: JSON.stringify(payload),
  })
  return unwrap(response)
}

export async function updateHostAgency(
  id: number,
  payload: Partial<HostAgencyPayload>,
): Promise<HostAgency> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<HostAgency>>(
    `/api/staff/catalog/host-agencies/${id}`,
    { method: "PUT", body: JSON.stringify(payload) },
  )
  return unwrap(response)
}

export async function updateHostAgencyStatus(id: number, isActive: boolean): Promise<HostAgency> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<HostAgency>>(
    `/api/staff/catalog/host-agencies/${id}/status`,
    { method: "PATCH", body: JSON.stringify({ is_active: isActive }) },
  )
  return unwrap(response)
}

export interface DeploymentSitePayload {
  host_agency_id: number
  name: string
  address?: string
  city?: string
  region?: string
  contact_person?: string
  contact_number?: string
  email?: string
  description?: string
  is_active?: boolean
}

export async function createDeploymentSite(payload: DeploymentSitePayload): Promise<DeploymentSite> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<DeploymentSite>>(
    "/api/staff/catalog/deployment-sites",
    { method: "POST", body: JSON.stringify(payload) },
  )
  return unwrap(response)
}

export async function updateDeploymentSite(
  id: number,
  payload: Partial<DeploymentSitePayload>,
): Promise<DeploymentSite> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<DeploymentSite>>(
    `/api/staff/catalog/deployment-sites/${id}`,
    { method: "PUT", body: JSON.stringify(payload) },
  )
  return unwrap(response)
}

export async function updateDeploymentSiteStatus(id: number, isActive: boolean): Promise<DeploymentSite> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<DeploymentSite>>(
    `/api/staff/catalog/deployment-sites/${id}/status`,
    { method: "PATCH", body: JSON.stringify({ is_active: isActive }) },
  )
  return unwrap(response)
}

export async function fetchDeploymentSlots(filters: DeploymentSlotFilters = {}): Promise<Paginated<DeploymentSlot>> {
  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== null && value !== "") {
      params.set(key, String(value))
    }
  }
  const qs = params.toString()
  const response = await apiRequest<Paginated<DeploymentSlot>>(
    `/api/staff/deployment-slots${qs ? `?${qs}` : ""}`,
  )
  return response
}

export async function fetchDeploymentSlot(id: number): Promise<DeploymentSlot> {
  const response = await apiRequest<ApiEnvelope<DeploymentSlot>>(`/api/staff/deployment-slots/${id}`)
  return unwrap(response)
}

export interface DeploymentSlotPayload {
  program_cycle_id: number
  deployment_site_id: number
  title: string
  description?: string
  capacity: number
  status?: "active" | "inactive"
}

export async function createDeploymentSlot(payload: DeploymentSlotPayload): Promise<DeploymentSlot> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<DeploymentSlot>>("/api/staff/deployment-slots", {
    method: "POST",
    body: JSON.stringify(payload),
  })
  return unwrap(response)
}

export async function updateDeploymentSlot(
  id: number,
  payload: Partial<DeploymentSlotPayload>,
): Promise<DeploymentSlot> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<DeploymentSlot>>(
    `/api/staff/deployment-slots/${id}`,
    { method: "PUT", body: JSON.stringify(payload) },
  )
  return unwrap(response)
}

export async function updateDeploymentSlotStatus(
  id: number,
  status: "active" | "inactive",
): Promise<DeploymentSlot> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<DeploymentSlot>>(
    `/api/staff/deployment-slots/${id}/status`,
    { method: "PATCH", body: JSON.stringify({ status }) },
  )
  return unwrap(response)
}
