import type { UserRole } from "@/types/auth"

export function homePathForRole(role: UserRole): string {
  switch (role) {
    case "student":
      return "/student/dashboard"
    case "staff":
      return "/staff/dashboard"
  }
}
