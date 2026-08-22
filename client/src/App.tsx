import { BrowserRouter, Navigate, Route, Routes } from "react-router-dom"
import { useAuth } from "@/auth/useAuth"
import { GuestRoute } from "@/components/auth/GuestRoute"
import { ProtectedRoute } from "@/components/auth/ProtectedRoute"
import { RoleRoute } from "@/components/auth/RoleRoute"
import { AppLayout } from "@/layouts/AppLayout"
import { AuthLayout } from "@/layouts/AuthLayout"
import { homePathForRole } from "@/lib/routes"
import { ForbiddenPage } from "@/pages/Forbidden"
import { NotFoundPage } from "@/pages/NotFound"
import { LoginPage } from "@/pages/auth/Login"
import { RegisterPage } from "@/pages/auth/Register"
import { StaffCatalogPage } from "@/pages/staff/Catalog"
import { StaffApplicationDetailPage } from "@/pages/staff/ApplicationDetail"
import { StaffDashboardPage } from "@/pages/staff/Dashboard"
import { StaffDeploymentSitesPage } from "@/pages/staff/DeploymentSites"
import { StaffDeploymentSiteDetailPage } from "@/pages/staff/DeploymentSiteDetail"
import { StaffDeploymentSlotsPage } from "@/pages/staff/DeploymentSlots"
import { StaffDeploymentSlotDetailPage } from "@/pages/staff/DeploymentSlotDetail"
import { StaffDeploymentsPage } from "@/pages/staff/Deployments"
import { StaffHostAgencyDetailPage } from "@/pages/staff/HostAgencyDetail"
import { StaffHostAgenciesPage } from "@/pages/staff/HostAgencies"
import { StaffReviewQueuePage } from "@/pages/staff/ReviewQueue"
import { StudentApplicationDetailPage } from "@/pages/student/ApplicationDetail"
import { StudentApplicationReviewPage } from "@/pages/student/ApplicationReview"
import { StudentApplicationsPage } from "@/pages/student/Applications"
import { StudentDashboardPage } from "@/pages/student/Dashboard"
import { StudentProfilePage } from "@/pages/student/Profile"
import { StudentProgramsPage } from "@/pages/student/Programs"
import { NotificationsPage } from "@/pages/shared/Notifications"

function RoleDashboardRedirect() {
  const { user } = useAuth()
  return <Navigate to={user ? homePathForRole(user.role) : "/login"} replace />
}

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route element={<GuestRoute children={<AuthLayout />} />}>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />
        </Route>

        <Route
          element={
            <ProtectedRoute>
              <AppLayout />
            </ProtectedRoute>
          }
        >
          <Route path="/dashboard" element={<RoleDashboardRedirect />} />
          <Route path="/" element={<RoleDashboardRedirect />} />
        </Route>

        <Route
          element={
            <ProtectedRoute>
              <RoleRoute roles={["student"]}>
                <AppLayout />
              </RoleRoute>
            </ProtectedRoute>
          }
        >
          <Route path="/student/dashboard" element={<StudentDashboardPage />} />
          <Route path="/student/programs" element={<StudentProgramsPage />} />
          <Route path="/student/applications" element={<StudentApplicationsPage />} />
          <Route path="/student/applications/:id" element={<StudentApplicationDetailPage />} />
          <Route path="/student/applications/:id/review" element={<StudentApplicationReviewPage />} />
          <Route path="/student/notifications" element={<NotificationsPage />} />
          <Route path="/student/profile" element={<StudentProfilePage />} />
        </Route>

        <Route
          element={
            <ProtectedRoute>
              <RoleRoute roles={["staff"]}>
                <AppLayout />
              </RoleRoute>
            </ProtectedRoute>
          }
        >
          <Route path="/staff/dashboard" element={<StaffDashboardPage />} />
          <Route path="/staff/review" element={<StaffReviewQueuePage />} />
          <Route path="/staff/applications/:id" element={<StaffApplicationDetailPage />} />
          <Route path="/staff/host-agencies" element={<StaffHostAgenciesPage />} />
          <Route path="/staff/host-agencies/:id" element={<StaffHostAgencyDetailPage />} />
          <Route path="/staff/deployment-sites" element={<StaffDeploymentSitesPage />} />
          <Route path="/staff/deployment-sites/:id" element={<StaffDeploymentSiteDetailPage />} />
          <Route path="/staff/deployment-slots" element={<StaffDeploymentSlotsPage />} />
          <Route path="/staff/deployment-slots/:id" element={<StaffDeploymentSlotDetailPage />} />
          <Route path="/staff/deployments" element={<StaffDeploymentsPage />} />
          <Route path="/staff/notifications" element={<NotificationsPage />} />
          <Route path="/staff/catalog" element={<StaffCatalogPage />} />
        </Route>

        <Route path="/403" element={<ForbiddenPage />} />
        <Route path="*" element={<NotFoundPage />} />
      </Routes>
    </BrowserRouter>
  )
}

export default App
