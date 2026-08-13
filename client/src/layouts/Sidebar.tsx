import { useState } from "react"
import { Link, NavLink, useNavigate } from "react-router-dom"
import {
  Building2,
  CalendarRange,
  ClipboardList,
  FileText,
  LayoutDashboard,
  Library,
  Loader2,
  LogOut,
  Menu,
  User,
  X,
} from "lucide-react"
import { useAuth } from "@/auth/useAuth"
import { Button } from "@/components/ui/button"
import { homePathForRole } from "@/lib/routes"
import { cn } from "@/lib/utils"
import { useToast } from "@/toast/useToast"

interface NavItem {
  to: string
  label: string
  icon: typeof LayoutDashboard
  end?: boolean
}

export function Sidebar() {
  const { user, logout } = useAuth()
  const { toast } = useToast()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)
  const [loggingOut, setLoggingOut] = useState(false)

  const dashboardPath = user ? homePathForRole(user.role) : "/dashboard"

  const navItems: NavItem[] = user
    ? user.role === "student"
      ? [
          { to: dashboardPath, label: "Dashboard", icon: LayoutDashboard, end: true },
          { to: "/student/programs", label: "Programs", icon: CalendarRange },
          { to: "/student/applications", label: "My applications", icon: FileText },
          { to: "/student/profile", label: "Profile", icon: User },
        ]
      : [
          { to: dashboardPath, label: "Dashboard", icon: LayoutDashboard, end: true },
          { to: "/staff/review", label: "Review applications", icon: ClipboardList },
          { to: "/staff/deployments", label: "Deployments", icon: Building2 },
          { to: "/staff/catalog", label: "Catalog", icon: Library },
        ]
    : []

  async function handleLogout() {
    setLoggingOut(true)
    try {
      await logout()
      toast({ title: "Signed out", description: "You have been signed out of the portal." })
      navigate("/login", { replace: true })
    } catch {
      setLoggingOut(false)
    }
  }

  const close = () => setOpen(false)

  return (
    <>
      <header className="sticky top-0 z-40 flex h-14 items-center gap-3 border-b bg-background/95 px-4 backdrop-blur lg:hidden">
        <Button variant="ghost" size="icon" onClick={() => setOpen(true)} aria-label="Open navigation">
          <Menu aria-hidden="true" />
        </Button>
        <Link to={dashboardPath} className="flex items-center gap-2" onClick={close}>
          <img
            src="/Department_of_Labor_and_Employment_(DOLE).png"
            alt="Department of Labor and Employment logo"
            className="h-7 w-7 object-contain"
          />
          <span className="text-sm font-semibold">DOLE GIP / SPES Portal</span>
        </Link>
      </header>

      {open ? (
        <div
          className="fixed inset-0 z-40 bg-black/40 lg:hidden"
          onClick={close}
          aria-hidden="true"
        />
      ) : null}

      <aside
        className={cn(
          "fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r bg-sidebar text-sidebar-foreground transition-transform duration-200 ease-out",
          "lg:translate-x-0",
          open ? "translate-x-0" : "-translate-x-full",
        )}
      >
        <div className="flex h-14 items-center justify-between gap-2 border-b px-4">
          <Link to={dashboardPath} className="flex min-w-0 items-center gap-2" onClick={close}>
            <img
              src="/Department_of_Labor_and_Employment_(DOLE).png"
              alt="Department of Labor and Employment logo"
              className="h-8 w-8 shrink-0 object-contain"
            />
            <span className="truncate text-sm font-semibold">DOLE GIP / SPES Portal</span>
          </Link>
          <Button
            variant="ghost"
            size="icon"
            className="lg:hidden"
            onClick={close}
            aria-label="Close navigation"
          >
            <X aria-hidden="true" />
          </Button>
        </div>

        <nav className="flex-1 space-y-1 overflow-y-auto p-3">
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              onClick={close}
              className={({ isActive }) =>
                cn(
                  "flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors",
                  isActive
                    ? "bg-sidebar-accent text-sidebar-accent-foreground"
                    : "text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground",
                )
              }
            >
              <item.icon className="size-4 shrink-0" aria-hidden="true" />
              {item.label}
            </NavLink>
          ))}
        </nav>

        <div className="space-y-3 border-t p-3">
          <div className="px-3 pt-1">
            <p className="truncate text-sm font-medium">{user?.name}</p>
            <p className="truncate text-xs text-sidebar-foreground/60">{user?.email}</p>
          </div>
          <Button
            variant="outline"
            size="sm"
            className="w-full"
            onClick={handleLogout}
            disabled={loggingOut}
          >
            {loggingOut ? (
              <Loader2 className="animate-spin" aria-hidden="true" />
            ) : (
              <LogOut aria-hidden="true" />
            )}
            Sign out
          </Button>
        </div>
      </aside>
    </>
  )
}
