import { Route, Routes } from 'react-router-dom'
import { RequireAuth, RequirePermission } from './auth/guards'
import { EmptyState } from './components/EmptyState'
import { ProfilePage } from './features/account/ProfilePage'
import { DelegationsPage } from './features/access/DelegationsPage'
import { ModulesPage } from './features/access/ModulesPage'
import { PermissionsPage } from './features/access/PermissionsPage'
import { SettingsPage } from './features/access/SettingsPage'
import { AuditLogsPage } from './features/audit/AuditLogsPage'
import { ChangePasswordPage } from './features/auth/ChangePasswordPage'
import { ForgotPasswordPage } from './features/auth/ForgotPasswordPage'
import { LoginPage } from './features/auth/LoginPage'
import { ResetPasswordPage } from './features/auth/ResetPasswordPage'
import { DashboardPage } from './features/dashboard/DashboardPage'
import {
  departmentsConfig,
  designationsConfig,
  divisionsConfig,
  locationsConfig,
  publicServicesConfig,
  serviceGradesConfig,
  sitesConfig,
} from './features/organisation/configs'
import { MasterDataPage } from './features/organisation/MasterDataPage'
import { OfficerDetailPage } from './features/organisation/OfficerDetailPage'
import { OfficersPage } from './features/organisation/OfficersPage'
import { StoresPage } from './features/organisation/StoresPage'
import { RoleDetailPage } from './features/roles/RoleDetailPage'
import { RolesPage } from './features/roles/RolesPage'
import { UserDetailPage } from './features/users/UserDetailPage'
import { UsersPage } from './features/users/UsersPage'
import { AppLayout } from './layout/AppLayout'

const gate = (anyOf: string[], element: React.ReactNode) => <RequirePermission anyOf={anyOf}>{element}</RequirePermission>

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/forgot-password" element={<ForgotPasswordPage />} />
      <Route path="/reset-password" element={<ResetPasswordPage />} />
      <Route
        path="/change-password"
        element={
          <RequireAuth>
            <ChangePasswordPage />
          </RequireAuth>
        }
      />

      <Route
        element={
          <RequireAuth>
            <AppLayout />
          </RequireAuth>
        }
      >
        <Route index element={<DashboardPage />} />
        <Route path="profile" element={<ProfilePage />} />

        <Route path="users" element={gate(['user.view'], <UsersPage />)} />
        <Route path="users/:id" element={gate(['user.view'], <UserDetailPage />)} />
        <Route path="roles" element={gate(['role.view'], <RolesPage />)} />
        <Route path="roles/:id" element={gate(['role.view'], <RoleDetailPage />)} />
        <Route path="permissions" element={gate(['permission.view'], <PermissionsPage />)} />
        <Route path="modules" element={gate(['module.toggle'], <ModulesPage />)} />
        <Route path="delegations" element={gate(['delegation.manage'], <DelegationsPage />)} />
        <Route path="audit-logs" element={gate(['audit.view'], <AuditLogsPage />)} />
        <Route path="settings" element={gate(['settings.manage'], <SettingsPage />)} />

        <Route path="organisation/divisions" element={gate(['organisation.view'], <MasterDataPage key="div" config={divisionsConfig} />)} />
        <Route path="organisation/departments" element={gate(['department.view'], <MasterDataPage key="dep" config={departmentsConfig} />)} />
        <Route path="organisation/sites" element={gate(['organisation.view'], <MasterDataPage key="site" config={sitesConfig} />)} />
        <Route path="organisation/stores" element={gate(['organisation.view'], <StoresPage />)} />
        <Route path="organisation/locations" element={gate(['organisation.view'], <MasterDataPage key="loc" config={locationsConfig} />)} />
        <Route path="organisation/designations" element={gate(['organisation.view'], <MasterDataPage key="des" config={designationsConfig} />)} />
        <Route path="organisation/public-services" element={gate(['organisation.view'], <MasterDataPage key="ps" config={publicServicesConfig} />)} />
        <Route path="organisation/service-grades" element={gate(['organisation.view'], <MasterDataPage key="sg" config={serviceGradesConfig} />)} />
        <Route path="organisation/officers" element={gate(['officer.view'], <OfficersPage />)} />
        <Route path="organisation/officers/:id" element={gate(['officer.view'], <OfficerDetailPage />)} />

        <Route path="*" element={<EmptyState title="Page not found" description="The page you asked for does not exist." />} />
      </Route>
    </Routes>
  )
}
