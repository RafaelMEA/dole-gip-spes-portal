import { Link } from "react-router-dom"
import { Home, SearchX } from "lucide-react"
import { Button } from "@/components/ui/button"

export function NotFoundPage() {
  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/40 px-4">
      <div className="flex w-full max-w-md flex-col items-center gap-4 text-center">
        <div className="flex size-14 items-center justify-center rounded-full bg-muted">
          <SearchX className="size-7 text-muted-foreground" aria-hidden="true" />
        </div>
        <h1 className="text-2xl font-semibold tracking-tight">Page not found</h1>
        <p className="text-sm text-muted-foreground">
          The page you are looking for does not exist or has been moved.
        </p>
        <Button nativeButton={false} render={<Link to="/" />}>
          <Home aria-hidden="true" />
          Back to home
        </Button>
      </div>
    </div>
  )
}
