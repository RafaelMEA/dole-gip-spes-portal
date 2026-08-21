import { apiRequest, apiUpload, requestCsrfCookie } from "@/lib/api"
import type {
  ApiEnvelope,
  Application,
  ApplicationCompleteness,
  ApplicationDocument,
  ApplicationSubmissionResponse,
  Paginated,
  AuditEvent,
  StudentDashboardData,
  StudentProfile,
} from "@/types/api"

async function unwrap<T>(response: ApiEnvelope<T>): Promise<T> {
  return response.data
}

export async function fetchStudentDashboard(): Promise<StudentDashboardData> {
  const response = await apiRequest<ApiEnvelope<StudentDashboardData>>("/api/student/dashboard")
  return unwrap(response)
}

export async function fetchMyApplications(): Promise<Application[]> {
  const response = await apiRequest<ApiEnvelope<Application[]>>("/api/student/applications")
  return response.data
}

export async function fetchApplication(id: number): Promise<Application> {
  const response = await apiRequest<ApiEnvelope<Application>>(`/api/student/applications/${id}`)
  return unwrap(response)
}

export async function fetchApplicationHistory(
  id: number,
  page = 1,
  perPage = 25,
): Promise<Paginated<AuditEvent>> {
  const response = await apiRequest<Paginated<AuditEvent>>(
    `/api/student/applications/${id}/history?page=${page}&per_page=${perPage}`,
  )
  return response
}

export async function fetchApplicationCompleteness(id: number): Promise<ApplicationCompleteness> {
  const response = await apiRequest<ApiEnvelope<ApplicationCompleteness>>(
    `/api/student/applications/${id}/completeness`,
  )
  return unwrap(response)
}

export async function createApplication(programCycleId: number): Promise<Application> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<Application>>("/api/student/applications", {
    method: "POST",
    body: JSON.stringify({ program_cycle_id: programCycleId }),
  })
  return unwrap(response)
}

export interface ApplicationPayload {
  remarks?: string | null
}

export async function updateApplication(
  id: number,
  payload: ApplicationPayload,
): Promise<Application> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<Application>>(
    `/api/student/applications/${id}`,
    { method: "PATCH", body: JSON.stringify(payload) },
  )
  return unwrap(response)
}

export async function submitApplication(
  id: number,
  remarks?: string,
): Promise<ApplicationSubmissionResponse> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<ApplicationSubmissionResponse>>(
    `/api/student/applications/${id}/submit`,
    { method: "POST", body: JSON.stringify({ remarks }) },
  )
  return unwrap(response)
}

export async function withdrawApplication(id: number, remarks?: string): Promise<Application> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<Application>>(
    `/api/student/applications/${id}/withdraw`,
    { method: "POST", body: JSON.stringify({ remarks }) },
  )
  return unwrap(response)
}

export async function deleteApplication(id: number): Promise<void> {
  await requestCsrfCookie()
  await apiRequest<void>(`/api/student/applications/${id}`, { method: "DELETE" })
}

export async function uploadDocument(
  applicationId: number,
  requirementId: number | null,
  file: File,
  onProgress?: (percent: number) => void,
): Promise<ApplicationDocument> {
  await requestCsrfCookie()
  const form = new FormData()
  form.append("file", file)
  if (requirementId !== null) {
    form.append("requirement_id", String(requirementId))
  }
  const response = await apiUpload<ApiEnvelope<ApplicationDocument>>(
    `/api/student/applications/${applicationId}/documents`,
    form,
    onProgress,
  )
  return response.data
}

export async function deleteDocument(applicationId: number, documentId: number): Promise<void> {
  await requestCsrfCookie()
  await apiRequest<void>(`/api/student/applications/${applicationId}/documents/${documentId}`, {
    method: "DELETE",
  })
}

export async function fetchProfile(): Promise<StudentProfile> {
  const response = await apiRequest<ApiEnvelope<StudentProfile>>("/api/student/profile")
  return unwrap(response)
}

export interface ProfilePayload {
  name: string
  school_name: string
  course: string
  year_level: string
  gwa?: string | null
  address?: string | null
  birthplace?: string | null
  birthdate?: string | null
  sex?: "male" | "female" | null
  is_indigent?: boolean
  is_4ps_member?: boolean
}

export async function updateProfile(payload: ProfilePayload): Promise<StudentProfile> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<StudentProfile>>("/api/student/profile", {
    method: "PUT",
    body: JSON.stringify(payload),
  })
  return unwrap(response)
}
