import { apiRequest } from "@/lib/api"
import type { ApiEnvelope, Program, Requirement } from "@/types/api"

export async function fetchPrograms(): Promise<Program[]> {
  const response = await apiRequest<ApiEnvelope<Program[]>>("/api/programs")
  return response.data
}

export async function fetchProgram(id: number): Promise<Program> {
  const response = await apiRequest<ApiEnvelope<Program>>(`/api/programs/${id}`)
  return response.data
}

export async function fetchCycleRequirements(cycleId: number): Promise<Requirement[]> {
  const response = await apiRequest<ApiEnvelope<Requirement[]>>(
    `/api/program-cycles/${cycleId}/requirements`,
  )
  return response.data
}
