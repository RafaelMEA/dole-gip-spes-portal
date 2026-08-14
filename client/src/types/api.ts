export type ApplicationStatus =
  | "draft"
  | "submitted"
  | "under_review"
  | "documents_incomplete"
  | "documents_verified"
  | "approved"
  | "for_deployment"
  | "deployed"
  | "completed"
  | "rejected"
  | "withdrawn"

export type ProgramCycleStatus = "draft" | "upcoming" | "open" | "closed" | "archived"

export type DocumentVerificationStatus = "pending" | "verified" | "rejected"

export type DeploymentStatus = "scheduled" | "active" | "completed" | "cancelled"

export interface Requirement {
  id: number
  name: string
  description: string | null
  slug: string
  is_active: boolean
  is_required?: boolean
  allowed_file_types?: string[] | null
  max_file_size?: number | null
}

export interface ProgramCycle {
  id: number
  program_id: number
  name: string
  description: string | null
  total_slots: number
  slots_remaining: number
  application_start: string | null
  application_deadline: string | null
  deployment_start: string | null
  deployment_end: string | null
  status: ProgramCycleStatus
  status_label: string
  is_published: boolean
  is_accepting_applications: boolean
  program?: Program
  requirements?: Requirement[]
}

export interface Program {
  id: number
  name: string
  slug: string
  description: string | null
  is_active: boolean
  cycles?: ProgramCycle[]
}

export interface UserSummary {
  id: number
  name: string
  email: string
  role: "student" | "staff"
  created_at?: string
}

export interface StatusHistoryItem {
  id: number
  status: ApplicationStatus
  status_label: string
  remarks: string | null
  changed_at: string
  changed_by: string | null
}

export interface HostAgency {
  id: number
  name: string
  address: string | null
  contact_person: string | null
  contact_number: string | null
  email: string | null
  is_active: boolean
  active_assignments: number
}

export interface DeploymentSite {
  id: number
  name: string
  address: string | null
  city: string | null
  region: string | null
  is_active: boolean
}

export interface DeploymentAssignment {
  id: number
  application_id: number
  position: string | null
  start_date: string | null
  end_date: string | null
  status: DeploymentStatus
  status_label: string
  remarks: string | null
  assigned_at: string | null
  host_agency: HostAgency | null
  deployment_site: DeploymentSite | null
  applicant?: UserSummary | null
}

export interface DocumentRequirement {
  id: number
  name: string
  description: string | null
  allowed_file_types?: string[] | null
  max_file_size?: number | null
}

export interface ApplicationDocument {
  id: number
  application_id: number
  requirement_id: number | null
  requirement: DocumentRequirement | null
  file_name: string
  mime_type: string | null
  file_size: number
  verification_status: DocumentVerificationStatus
  verification_label: string
  rejection_reason: string | null
  uploaded_at: string | null
  verified_at: string | null
  verified_by: string | null
  download_url: string
}

export interface Application {
  id: number
  status: ApplicationStatus
  status_label: string
  remarks: string | null
  submitted_at: string | null
  approved_at: string | null
  created_at: string
  updated_at: string
  missing_required_documents: string[]
  program_cycle?: ProgramCycle
  documents?: ApplicationDocument[]
  status_history?: StatusHistoryItem[]
  assignment?: DeploymentAssignment | null
  applicant?: UserSummary
}

export interface StudentDetails {
  school_name: string | null
  course: string | null
  year_level: string | null
  gwa: number | null
  address: string | null
  birthplace: string | null
  birthdate: string | null
  sex: "male" | "female" | null
  is_indigent: boolean
  is_4ps_member: boolean
}

export interface StudentProfile {
  id: number
  name: string
  email: string
  student_details: StudentDetails | null
}

export interface Paginated<T> {
  data: T[]
  current_page: number
  first_page_url: string | null
  from: number | null
  last_page: number
  last_page_url: string | null
  next_page_url: string | null
  path: string
  per_page: number
  prev_page_url: string | null
  to: number | null
  total: number
}

export interface StudentDashboardData {
  stats: {
    total_applications: number
    draft_applications: number
    active_applications: number
  }
  applications: Application[]
  open_cycles: ProgramCycle[]
}

export interface StaffDashboardData {
  stats: {
    total_applications: number
    pending_review: number
    documents_pending: number
    approved: number
    deployed: number
    active_assignments: number
    open_cycles: number
  }
  recent_applications: Application[]
  review_queue: Application[]
}

export interface ApiEnvelope<T> {
  data: T
}
