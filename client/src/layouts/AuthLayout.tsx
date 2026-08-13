import { Outlet } from "react-router-dom"

export function AuthLayout() {
  return (
    <div className="flex min-h-screen flex-col bg-muted/40">
      <main className="flex flex-1 items-center justify-center px-4 py-10">
        <div className="w-full max-w-md">
          <div className="mb-6 flex flex-col items-center gap-3 text-center">
            <img
              src="/Department_of_Labor_and_Employment_(DOLE).png"
              alt="Department of Labor and Employment logo"
              className="h-16 w-16 object-contain"
            />
            <div>
              <h1 className="text-lg font-semibold tracking-tight">DOLE GIP / SPES Portal</h1>
              <p className="text-sm text-muted-foreground">Government Application Portal</p>
            </div>
            <div className="flex items-center justify-center gap-4">
              <img src="/gip2.png" alt="Government Internship Program" className="h-10 w-auto object-contain" />
              <img src="/spes_logo.png" alt="Special Program for Employment of Students" className="h-10 w-auto object-contain" />
            </div>
          </div>

          <div className="rounded-xl border bg-card p-6 shadow-sm">
            <Outlet />
          </div>

          <p className="mt-6 text-center text-xs text-muted-foreground">
            An official portal of the Department of Labor and Employment (DOLE)
          </p>
        </div>
      </main>
    </div>
  )
}
