export type UserRole = "student" | "staff"

export interface User {
  id: number
  name: string
  email: string
  role: UserRole
  created_at?: string
}
