import type { ReactNode } from 'react'
import { Route, Routes } from 'react-router-dom'
import { RequireAuth, RequirePermission } from './auth/guards'
import { EmptyState } from './components/EmptyState'
import { ExceptionsPage, MachineDetailPage, MachinesPage, ReadingsPage, SharedUsagePage, StatementsPage } from './features/a4/A4Pages'
import { ProfilePage } from './features/account/ProfilePage'
import { DelegationsPage } from './features/access/DelegationsPage'
import { ModulesPage } from './features/access/ModulesPage'
import { PermissionsPage } from './features/access/PermissionsPage'
import { SettingsPage } from './features/access/SettingsPage'
import { AssetDetailPage, AssetsPage, ScanPage } from './features/assets/Assets'
import { TransferDetailPage, TransfersPage } from './features/assets/Transfers'
import { AuditLogsPage } from './features/audit/AuditLogsPage'
import { ChangePasswordPage } from './features/auth/ChangePasswordPage'
import { ForgotPasswordPage } from './features/auth/ForgotPasswordPage'
import { LoginPage } from './features/auth/LoginPage'
import { ResetPasswordPage } from './features/auth/ResetPasswordPage'
import { categoriesConfig, itemsConfig, suppliersConfig, unitsConfig } from './features/catalogue/configs'
import { ItemDetailPage } from './features/catalogue/ItemDetailPage'
import { DashboardPage } from './features/dashboard/DashboardPage'
import { DisposalsPage, RepairDetailPage, RepairsPage, ServicingPage, ValuationsPage, VerificationDetailPage, VerificationPage, WarrantiesPage } from './features/maintenance/Maintenance'
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
import { GrnDetailPage, GrnsPage, NewGrnPage } from './features/receiving/Grns'
import { PurchaseOrderDetailPage, PurchaseOrdersPage } from './features/receiving/PurchaseOrders'
import { SupplierReturnsPage } from './features/receiving/SupplierReturns'
import { DepartmentReturnDetailPage, DepartmentReturnsPage, IssueDetailPage, IssuesPage } from './features/requests/Issues'
import { RequestDetailPage, RequestFormPage, RequestsPage } from './features/requests/Requests'
import { RoleDetailPage } from './features/roles/RoleDetailPage'
import { RolesPage } from './features/roles/RolesPage'
import { AdjustmentsPage, DiscrepanciesPage, StockBalancesPage, StockCountDetailPage, StockCountsPage, StockLedgerPage } from './features/stock/StockPages'
import { UserDetailPage } from './features/users/UserDetailPage'
import { UsersPage } from './features/users/UsersPage'
import { AppLayout } from './layout/AppLayout'

const gate = (anyOf: string[], element: ReactNode) => <RequirePermission anyOf={anyOf}>{element}</RequirePermission>
const VIEW_REQUESTS = ['request.view_own', 'request.view_department', 'request.view_all']
const VIEW_TRANSFERS = ['transfer.create', 'transfer.approve', 'transfer.dispatch', 'transfer.receive']

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/forgot-password" element={<ForgotPasswordPage />} />
      <Route path="/reset-password" element={<ResetPasswordPage />} />
      <Route path="/change-password" element={<RequireAuth><ChangePasswordPage /></RequireAuth>} />

      <Route element={<RequireAuth><AppLayout /></RequireAuth>}>
        <Route index element={<DashboardPage />} />
        <Route path="profile" element={<ProfilePage />} />

        {/* Requests & issues */}
        <Route path="requests" element={gate(VIEW_REQUESTS, <RequestsPage />)} />
        <Route path="requests/new" element={gate(['request.create'], <RequestFormPage />)} />
        <Route path="requests/:id" element={gate(VIEW_REQUESTS, <RequestDetailPage />)} />
        <Route path="requests/:id/edit" element={gate(['request.create'], <RequestFormPage />)} />
        <Route path="issues" element={gate(['request.view_all', 'issue.acknowledge'], <IssuesPage />)} />
        <Route path="issues/:id" element={gate(['request.view_all', 'issue.acknowledge'], <IssueDetailPage />)} />
        <Route path="department-returns" element={gate(['return.create', 'return.inspect'], <DepartmentReturnsPage />)} />
        <Route path="department-returns/:id" element={gate(['return.create', 'return.inspect'], <DepartmentReturnDetailPage />)} />

        {/* Stores & stock */}
        <Route path="stock" element={gate(['stock.view'], <StockBalancesPage />)} />
        <Route path="stock/ledger" element={gate(['ledger.view'], <StockLedgerPage />)} />
        <Route path="stock/counts" element={gate(['stock.view'], <StockCountsPage />)} />
        <Route path="stock/counts/:id" element={gate(['stock.view'], <StockCountDetailPage />)} />
        <Route path="stock/adjustments" element={gate(['stock.view'], <AdjustmentsPage />)} />
        <Route path="discrepancies" element={gate(['stock.view'], <DiscrepanciesPage />)} />

        {/* Receiving */}
        <Route path="purchase-orders" element={gate(['po.view'], <PurchaseOrdersPage />)} />
        <Route path="purchase-orders/:id" element={gate(['po.view'], <PurchaseOrderDetailPage />)} />
        <Route path="grns" element={gate(['grn.view'], <GrnsPage />)} />
        <Route path="grns/new" element={gate(['grn.create'], <NewGrnPage />)} />
        <Route path="grns/:id" element={gate(['grn.view'], <GrnDetailPage />)} />
        <Route path="supplier-returns" element={gate(['supplier_return.manage'], <SupplierReturnsPage />)} />

        {/* A4 paper */}
        <Route path="a4/machines" element={gate(['machine.manage', 'meter.record', 'meter.verify'], <MachinesPage />)} />
        <Route path="a4/machines/:id" element={gate(['machine.manage', 'meter.record', 'meter.verify'], <MachineDetailPage />)} />
        <Route path="a4/readings" element={gate(['meter.record', 'meter.verify'], <ReadingsPage />)} />
        <Route path="a4/statements" element={gate(['paper_statement.manage', 'meter.verify'], <StatementsPage />)} />
        <Route path="a4/shared-usage" element={gate(['meter.record', 'shared_usage.review'], <SharedUsagePage />)} />
        <Route path="a4/exceptions" element={gate(['request.create', 'a4.exception_approve'], <ExceptionsPage />)} />

        {/* Assets */}
        <Route path="assets" element={gate(['asset.view'], <AssetsPage />)} />
        <Route path="assets/transfers" element={gate(VIEW_TRANSFERS, <TransfersPage />)} />
        <Route path="assets/transfers/:id" element={gate(VIEW_TRANSFERS, <TransferDetailPage />)} />
        <Route path="assets/:id" element={gate(['asset.view'], <AssetDetailPage />)} />
        <Route path="scan" element={gate(['asset.view'], <ScanPage />)} />
        <Route path="scan/:token" element={gate(['asset.view'], <ScanPage />)} />

        {/* Maintenance */}
        <Route path="maintenance/repairs" element={gate(['asset.view'], <RepairsPage />)} />
        <Route path="maintenance/repairs/:id" element={gate(['asset.view'], <RepairDetailPage />)} />
        <Route path="maintenance/warranties" element={gate(['asset.view'], <WarrantiesPage />)} />
        <Route path="maintenance/servicing" element={gate(['asset.view'], <ServicingPage />)} />
        <Route path="maintenance/verification" element={gate(['verification.perform'], <VerificationPage />)} />
        <Route path="maintenance/verification/:id" element={gate(['verification.perform'], <VerificationDetailPage />)} />
        <Route path="maintenance/valuations" element={gate(['valuation.manage', 'valuation.approve'], <ValuationsPage />)} />
        <Route path="maintenance/disposals" element={gate(['disposal.manage', 'disposal.approve'], <DisposalsPage />)} />

        {/* Catalogue */}
        <Route path="catalogue/items" element={gate(['item.view'], <MasterDataPage key="items" config={itemsConfig} />)} />
        <Route path="catalogue/items/:id" element={gate(['item.view'], <ItemDetailPage />)} />
        <Route path="catalogue/categories" element={gate(['item.view'], <MasterDataPage key="cat" config={categoriesConfig} />)} />
        <Route path="catalogue/units" element={gate(['item.view'], <MasterDataPage key="units" config={unitsConfig} />)} />
        <Route path="catalogue/suppliers" element={gate(['supplier.view'], <MasterDataPage key="sup" config={suppliersConfig} />)} />

        {/* Access control */}
        <Route path="users" element={gate(['user.view'], <UsersPage />)} />
        <Route path="users/:id" element={gate(['user.view'], <UserDetailPage />)} />
        <Route path="roles" element={gate(['role.view'], <RolesPage />)} />
        <Route path="roles/:id" element={gate(['role.view'], <RoleDetailPage />)} />
        <Route path="permissions" element={gate(['permission.view'], <PermissionsPage />)} />
        <Route path="modules" element={gate(['module.toggle'], <ModulesPage />)} />
        <Route path="delegations" element={gate(['delegation.manage'], <DelegationsPage />)} />
        <Route path="audit-logs" element={gate(['audit.view'], <AuditLogsPage />)} />
        <Route path="settings" element={gate(['settings.manage'], <SettingsPage />)} />

        {/* Organisation */}
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
