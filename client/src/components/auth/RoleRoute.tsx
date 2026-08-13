import type { ReactNode } from "react"
import { Navigate } from "react-router-dom"
import { useAuth } from "@/auth/useAuth"
import { FullPageLoader } from "@/components/FullPageLoader"
import type { UserRole } from "@/types/auth"

export function RoleRoute({ roles, children }: { roles: UserRole[]; children: ReactNode }) {
  const { user, isAuthenticated, isLoading } = useAuth()

  if (isLoading) {
    return <FullPageLoader />
  }

  if (!isAuthenticated || !user) {
    return <Navigate to="/login" replace />
  }

  if (!roles.includes(user.role)) {
    return <Navigate to="/403" replace />
  }

  return <>{children}</>
}
