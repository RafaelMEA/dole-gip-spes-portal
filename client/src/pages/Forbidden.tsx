import { Link, useNavigate } from "react-router-dom"
import { ArrowLeft, ShieldAlert } from "lucide-react"
import { useAuth } from "@/auth/useAuth"
import { Button } from "@/components/ui/button"
import { homePathForRole } from "@/lib/routes"

export function ForbiddenPage() {
  const { user, isAuthenticated, isLoading } = useAuth()
  const navigate = useNavigate()

  const target = user ? homePathForRole(user.role) : "/login"

  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/40 px-4">
      <div className="flex w-full max-w-md flex-col items-center gap-4 text-center">
        <div className="flex size-14 items-center justify-center rounded-full bg-destructive/10">
          <ShieldAlert className="size-7 text-destructive" aria-hidden="true" />
        </div>
        <h1 className="text-2xl font-semibold tracking-tight">Access denied</h1>
        <p className="text-sm text-muted-foreground">
          You do not have permission to view this page. If you believe this is a mistake, contact
          your DOLE office.
        </p>
        <div className="flex gap-3">
          {!isLoading && isAuthenticated ? (
            <Button onClick={() => navigate(target, { replace: true })}>
              <ArrowLeft aria-hidden="true" />
              Go to dashboard
            </Button>
          ) : (
            <Button nativeButton={false} render={<Link to={target} />}>
              <ArrowLeft aria-hidden="true" />
              Go to sign in
            </Button>
          )}
        </div>
      </div>
    </div>
  )
}
