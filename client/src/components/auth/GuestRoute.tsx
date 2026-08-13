import type { ReactNode } from "react"
import { Navigate } from "react-router-dom"
import { useAuth } from "@/auth/useAuth"
import { FullPageLoader } from "@/components/FullPageLoader"
import { homePathForRole } from "@/lib/routes"

export function GuestRoute({ children }: { children: ReactNode }) {
  const { user, isAuthenticated, isLoading } = useAuth()

  if (isLoading) {
    return <FullPageLoader />
  }

  if (isAuthenticated && user) {
    return <Navigate to={homePathForRole(user.role)} replace />
  }

  return <>{children}</>
}
