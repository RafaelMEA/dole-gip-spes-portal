import { apiRequest, requestCsrfCookie } from "@/lib/api"
import type {
  ApiEnvelope,
  Application,
  ApplicationDocument,
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

export async function createApplication(programCycleId: number): Promise<Application> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<Application>>("/api/student/applications", {
    method: "POST",
    body: JSON.stringify({ program_cycle_id: programCycleId }),
  })
  return unwrap(response)
}

export async function submitApplication(id: number, remarks?: string): Promise<Application> {
  await requestCsrfCookie()
  const response = await apiRequest<ApiEnvelope<Application>>(
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
): Promise<ApplicationDocument> {
  await requestCsrfCookie()
  const form = new FormData()
  form.append("file", file)
  if (requirementId !== null) {
    form.append("requirement_id", String(requirementId))
  }
  const response = await apiRequest<ApiEnvelope<ApplicationDocument>>(
    `/api/student/applications/${applicationId}/documents`,
    { method: "POST", body: form },
  )
  return unwrap(response)
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
