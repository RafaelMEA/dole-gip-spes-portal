import type { ReactNode } from "react"
import { Navigate, useLocation } from "react-router-dom"
import { useAuth } from "@/auth/useAuth"
import { FullPageLoader } from "@/components/FullPageLoader"

export function ProtectedRoute({ children }: { children: ReactNode }) {
  const { user, isAuthenticated, isLoading } = useAuth()
  const location = useLocation()

  if (isLoading) {
    return <FullPageLoader />
  }

  if (!isAuthenticated || !user) {
    return <Navigate to="/login" replace state={{ from: location }} />
  }

  return <>{children}</>
}
