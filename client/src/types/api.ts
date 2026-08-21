export type ApplicationStatus =
  | "draft"
  | "submitted"
  | "under_review"
  | "documents_incomplete"
  | "documents_verified"
  | "returned_for_correction"
  | "approved"
  | "for_deployment"
  | "deployed"
  | "completed"
  | "rejected"
  | "withdrawn"

export type ProgramCycleStatus = "draft" | "upcoming" | "open" | "closed" | "archived"

export type DocumentVerificationStatus = "pending" | "verified" | "rejected"

export type DocumentVerificationAction = "verified" | "rejected"

export type HostAgencyType = "government" | "private" | "ngo" | "other"

export type HostAgencyStatusFilter = "all" | "active" | "inactive"

export type DeploymentSiteStatusFilter = "all" | "active" | "inactive"

export interface HostAgencyFilters {
  search?: string
  status?: HostAgencyStatusFilter
  sort?: string
  direction?: "asc" | "desc"
  page?: number
  per_page?: number
}

export interface DeploymentSiteFilters {
  search?: string
  status?: DeploymentSiteStatusFilter
  host_agency_id?: number
  sort?: string
  direction?: "asc" | "desc"
  page?: number
  per_page?: number
}

export interface DocumentVerificationRequest {
  verification_status: DocumentVerificationAction
  rejection_reason?: string
}

export interface DocumentRejectionRequest {
  verification_status: "rejected"
  rejection_reason: string
}

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

export interface StudentDetailSummary {
  school_name: string | null
  course: string | null
  year_level: string | number | null
}

export interface UserSummary {
  id: number
  name: string
  email: string
  role: "student" | "staff"
  created_at?: string
  student_detail?: StudentDetailSummary | null
}

export type ApplicationSortField = "submitted_at" | "created_at" | "updated_at"
export type SortDirection = "asc" | "desc"

export interface StaffApplicationFilters {
  status?: string
  program_id?: number
  program_cycle_id?: number
  search?: string
  submitted_from?: string
  submitted_to?: string
  sort?: ApplicationSortField
  direction?: SortDirection
  page?: number
  per_page?: number
}

export type StaffApplicationListResponse = Paginated<Application>

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
  agency_type: HostAgencyType
  agency_type_label: string
  address: string | null
  contact_person: string | null
  contact_number: string | null
  email: string | null
  is_active: boolean
  active_assignments: number
  created_at: string
}

export interface DeploymentSite {
  id: number
  host_agency_id: number
  host_agency?: HostAgency | null
  name: string
  address: string | null
  city: string | null
  region: string | null
  contact_person: string | null
  contact_number: string | null
  email: string | null
  description: string | null
  is_active: boolean
  created_at: string
}

export interface DeploymentAssignment {
  id: number
  application_id: number
  deployment_slot_id: number | null
  position: string | null
  start_date: string | null
  end_date: string | null
  status: DeploymentStatus
  status_label: string
  remarks: string | null
  assigned_at: string | null
  updated_at?: string
  host_agency: HostAgency | null
  deployment_site: DeploymentSite | null
  deployment_slot: DeploymentSlot | null
  program_cycle?: ProgramCycle | null
  applicant?: UserSummary | null
  assigned_by?: UserSummary | null
  assigned_by_name?: string | null
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
  verified_by?: string | null
  view_url: string
  download_url: string
}

export interface MissingRequirement {
  id: number
  name: string
  is_required: boolean
}

export interface ApplicationCompleteness {
  is_complete: boolean
  application_complete: boolean
  documents_complete: boolean
  missing_application_fields: string[]
  missing_requirements: MissingRequirement[]
}

export type ApplicationSubmissionResponse = Application

export interface Application {
  id: number
  status: ApplicationStatus
  status_label: string
  remarks: string | null
  decision_reason: string | null
  decided_at: string | null
  decided_by?: string | null
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

export type DeploymentSlotStatus = "active" | "inactive"

export type DeploymentSlotStatusFilter = "all" | "active" | "inactive"

export interface DeploymentSlot {
  id: number
  program_cycle_id: number
  deployment_site_id: number
  title: string
  description: string | null
  capacity: number
  assigned_count: number
  available_count: number
  status: DeploymentSlotStatus
  status_label: string
  program_cycle?: ProgramCycle
  deployment_site?: DeploymentSite
  created_at: string
  updated_at: string
}

export interface DeploymentSlotFilters {
  search?: string
  program_cycle_id?: number
  deployment_site_id?: number
  host_agency_id?: number
  status?: DeploymentSlotStatusFilter
  sort?: string
  direction?: "asc" | "desc"
  page?: number
  per_page?: number
}

export interface DeploymentSlotOption {
  id: number
  title: string
  capacity: number
  assigned_count: number
  available_count: number
}

export interface DeploymentSiteOption {
  id: number
  name: string
  slots: DeploymentSlotOption[]
}

export interface DeploymentAgencyOption {
  id: number
  name: string
  deployment_sites: DeploymentSiteOption[]
}

export interface DeploymentOptions {
  program_cycle: { id: number; name: string; program_id: number }
  host_agencies: DeploymentAgencyOption[]
}

export type AssignmentStatusFilter = "all" | DeploymentStatus

export interface AssignmentFilters {
  search?: string
  status?: AssignmentStatusFilter
  program_cycle_id?: number
  host_agency_id?: number
  deployment_site_id?: number
  page?: number
  per_page?: number
}

export type AuditEventSource = "status_history" | "audit_log"

export interface AuditEvent {
  id: number
  source?: AuditEventSource
  action: string
  label: string
  actor: string | null
  occurred_at: string
  reason: string | null
  old_values?: Record<string, unknown> | null
  new_values?: Record<string, unknown> | null
  metadata?: Record<string, unknown> | null
}

export type HistoryPage = Paginated<AuditEvent>
