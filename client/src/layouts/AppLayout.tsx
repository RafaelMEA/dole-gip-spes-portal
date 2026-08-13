import { Outlet } from "react-router-dom"
import { Sidebar } from "@/layouts/Sidebar"

export function AppLayout() {
  return (
    <div className="min-h-screen bg-muted/40">
      <Sidebar />
      <main className="lg:pl-64">
        <div className="mx-auto max-w-6xl px-4 py-8">
          <Outlet />
        </div>
      </main>
    </div>
  )
}
