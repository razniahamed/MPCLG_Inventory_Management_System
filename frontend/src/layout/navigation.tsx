import AccountTreeOutlinedIcon from '@mui/icons-material/AccountTreeOutlined'
import AdminPanelSettingsOutlinedIcon from '@mui/icons-material/AdminPanelSettingsOutlined'
import ApartmentOutlinedIcon from '@mui/icons-material/ApartmentOutlined'
import AssignmentOutlinedIcon from '@mui/icons-material/AssignmentOutlined'
import AssignmentReturnOutlinedIcon from '@mui/icons-material/AssignmentReturnOutlined'
import BadgeOutlinedIcon from '@mui/icons-material/BadgeOutlined'
import BalanceOutlinedIcon from '@mui/icons-material/BalanceOutlined'
import BuildOutlinedIcon from '@mui/icons-material/BuildOutlined'
import CategoryOutlinedIcon from '@mui/icons-material/CategoryOutlined'
import ChecklistOutlinedIcon from '@mui/icons-material/ChecklistOutlined'
import DashboardOutlinedIcon from '@mui/icons-material/DashboardOutlined'
import DeleteSweepOutlinedIcon from '@mui/icons-material/DeleteSweepOutlined'
import DescriptionOutlinedIcon from '@mui/icons-material/DescriptionOutlined'
import EventRepeatOutlinedIcon from '@mui/icons-material/EventRepeatOutlined'
import ExtensionOutlinedIcon from '@mui/icons-material/ExtensionOutlined'
import FactCheckOutlinedIcon from '@mui/icons-material/FactCheckOutlined'
import GppMaybeOutlinedIcon from '@mui/icons-material/GppMaybeOutlined'
import GroupsOutlinedIcon from '@mui/icons-material/GroupsOutlined'
import HistoryOutlinedIcon from '@mui/icons-material/HistoryOutlined'
import Inventory2OutlinedIcon from '@mui/icons-material/Inventory2Outlined'
import KeyOutlinedIcon from '@mui/icons-material/KeyOutlined'
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import MenuBookOutlinedIcon from '@mui/icons-material/MenuBookOutlined'
import MilitaryTechOutlinedIcon from '@mui/icons-material/MilitaryTechOutlined'
import MoveDownOutlinedIcon from '@mui/icons-material/MoveDownOutlined'
import OutboxOutlinedIcon from '@mui/icons-material/OutboxOutlined'
import PeopleOutlinedIcon from '@mui/icons-material/PeopleOutlined'
import PlaceOutlinedIcon from '@mui/icons-material/PlaceOutlined'
import PrintOutlinedIcon from '@mui/icons-material/PrintOutlined'
import QrCode2OutlinedIcon from '@mui/icons-material/QrCode2Outlined'
import QrCodeScannerOutlinedIcon from '@mui/icons-material/QrCodeScannerOutlined'
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined'
import ReportProblemOutlinedIcon from '@mui/icons-material/ReportProblemOutlined'
import RequestQuoteOutlinedIcon from '@mui/icons-material/RequestQuoteOutlined'
import SettingsOutlinedIcon from '@mui/icons-material/SettingsOutlined'
import ShareOutlinedIcon from '@mui/icons-material/ShareOutlined'
import SpeedOutlinedIcon from '@mui/icons-material/SpeedOutlined'
import StorefrontOutlinedIcon from '@mui/icons-material/StorefrontOutlined'
import StraightenOutlinedIcon from '@mui/icons-material/StraightenOutlined'
import SwapHorizOutlinedIcon from '@mui/icons-material/SwapHorizOutlined'
import TuneOutlinedIcon from '@mui/icons-material/TuneOutlined'
import VerifiedUserOutlinedIcon from '@mui/icons-material/VerifiedUserOutlined'
import WarehouseOutlinedIcon from '@mui/icons-material/WarehouseOutlined'
import WorkOutlinedIcon from '@mui/icons-material/WorkOutlined'
import type { ReactNode } from 'react'

export interface NavItem {
  label: string
  to: string
  icon: ReactNode
  /** Any one of these permissions shows the item. Empty = everyone. */
  anyOf: string[]
  /** Later phases: shown greyed with the phase number. */
  phase?: number
}

export interface NavGroup {
  label: string
  items: NavItem[]
}

export const NAVIGATION: NavGroup[] = [
  { label: '', items: [{ label: 'Dashboard', to: '/', icon: <DashboardOutlinedIcon />, anyOf: [] }] },
  {
    label: 'Requests',
    items: [
      { label: 'Requests', to: '/requests', icon: <AssignmentOutlinedIcon />, anyOf: ['request.view_own', 'request.view_department', 'request.view_all'] },
      { label: 'Issues', to: '/issues', icon: <OutboxOutlinedIcon />, anyOf: ['request.view_all', 'issue.acknowledge'] },
      { label: 'Returns to stores', to: '/department-returns', icon: <AssignmentReturnOutlinedIcon />, anyOf: ['return.create', 'return.inspect'] },
    ],
  },
  {
    label: 'Stores & stock',
    items: [
      { label: 'Stock balances', to: '/stock', icon: <Inventory2OutlinedIcon />, anyOf: ['stock.view'] },
      { label: 'Stock ledger', to: '/stock/ledger', icon: <MenuBookOutlinedIcon />, anyOf: ['ledger.view'] },
      { label: 'Stock counts', to: '/stock/counts', icon: <ChecklistOutlinedIcon />, anyOf: ['stock.view'] },
      { label: 'Adjustments', to: '/stock/adjustments', icon: <TuneOutlinedIcon />, anyOf: ['stock.view'] },
      { label: 'Discrepancies', to: '/discrepancies', icon: <ReportProblemOutlinedIcon />, anyOf: ['stock.view'] },
    ],
  },
  {
    label: 'Receiving',
    items: [
      { label: 'Purchase orders', to: '/purchase-orders', icon: <RequestQuoteOutlinedIcon />, anyOf: ['po.view'] },
      { label: 'Goods received', to: '/grns', icon: <LocalShippingOutlinedIcon />, anyOf: ['grn.view'] },
      { label: 'Supplier returns', to: '/supplier-returns', icon: <MoveDownOutlinedIcon />, anyOf: ['supplier_return.manage'] },
    ],
  },
  {
    label: 'A4 paper',
    items: [
      { label: 'Machines', to: '/a4/machines', icon: <PrintOutlinedIcon />, anyOf: ['machine.manage', 'meter.record', 'meter.verify'] },
      { label: 'Meter readings', to: '/a4/readings', icon: <SpeedOutlinedIcon />, anyOf: ['meter.record', 'meter.verify'] },
      { label: 'Paper statements', to: '/a4/statements', icon: <ReceiptLongOutlinedIcon />, anyOf: ['paper_statement.manage', 'meter.verify'] },
      { label: 'Shared usage', to: '/a4/shared-usage', icon: <ShareOutlinedIcon />, anyOf: ['meter.record', 'shared_usage.review'] },
      { label: 'A4 exceptions', to: '/a4/exceptions', icon: <GppMaybeOutlinedIcon />, anyOf: ['request.create', 'a4.exception_approve'] },
    ],
  },
  {
    label: 'Assets',
    items: [
      { label: 'Asset register', to: '/assets', icon: <QrCode2OutlinedIcon />, anyOf: ['asset.view'] },
      { label: 'Transfers & loans', to: '/assets/transfers', icon: <SwapHorizOutlinedIcon />, anyOf: ['transfer.create', 'transfer.approve', 'transfer.dispatch', 'transfer.receive'] },
      { label: 'Scan QR', to: '/scan', icon: <QrCodeScannerOutlinedIcon />, anyOf: ['asset.view'] },
    ],
  },
  {
    label: 'Maintenance',
    items: [
      { label: 'Repairs', to: '/maintenance/repairs', icon: <BuildOutlinedIcon />, anyOf: ['asset.view'] },
      { label: 'Warranties', to: '/maintenance/warranties', icon: <VerifiedUserOutlinedIcon />, anyOf: ['asset.view'] },
      { label: 'Servicing', to: '/maintenance/servicing', icon: <EventRepeatOutlinedIcon />, anyOf: ['asset.view'] },
      { label: 'Verification', to: '/maintenance/verification', icon: <FactCheckOutlinedIcon />, anyOf: ['verification.perform'] },
      { label: 'Valuations', to: '/maintenance/valuations', icon: <BalanceOutlinedIcon />, anyOf: ['valuation.manage', 'valuation.approve'] },
      { label: 'Disposals', to: '/maintenance/disposals', icon: <DeleteSweepOutlinedIcon />, anyOf: ['disposal.manage', 'disposal.approve'] },
    ],
  },
  {
    label: 'Catalogue',
    items: [
      { label: 'Items', to: '/catalogue/items', icon: <CategoryOutlinedIcon />, anyOf: ['item.view'] },
      { label: 'Item categories', to: '/catalogue/categories', icon: <AccountTreeOutlinedIcon />, anyOf: ['item.view'] },
      { label: 'Units', to: '/catalogue/units', icon: <StraightenOutlinedIcon />, anyOf: ['item.view'] },
      { label: 'Suppliers', to: '/catalogue/suppliers', icon: <StorefrontOutlinedIcon />, anyOf: ['supplier.view'] },
    ],
  },
  {
    label: 'Access control',
    items: [
      { label: 'Users', to: '/users', icon: <PeopleOutlinedIcon />, anyOf: ['user.view'] },
      { label: 'Roles', to: '/roles', icon: <AdminPanelSettingsOutlinedIcon />, anyOf: ['role.view'] },
      { label: 'Permissions', to: '/permissions', icon: <KeyOutlinedIcon />, anyOf: ['permission.view'] },
      { label: 'Modules', to: '/modules', icon: <ExtensionOutlinedIcon />, anyOf: ['module.toggle'] },
      { label: 'Delegations', to: '/delegations', icon: <SwapHorizOutlinedIcon />, anyOf: ['delegation.manage'] },
      { label: 'Audit trail', to: '/audit-logs', icon: <HistoryOutlinedIcon />, anyOf: ['audit.view'] },
      { label: 'Settings', to: '/settings', icon: <SettingsOutlinedIcon />, anyOf: ['settings.manage'] },
    ],
  },
  {
    label: 'Organisation',
    items: [
      { label: 'Divisions', to: '/organisation/divisions', icon: <AccountTreeOutlinedIcon />, anyOf: ['organisation.view'] },
      { label: 'Departments', to: '/organisation/departments', icon: <ApartmentOutlinedIcon />, anyOf: ['department.view'] },
      { label: 'Officers', to: '/organisation/officers', icon: <BadgeOutlinedIcon />, anyOf: ['officer.view'] },
      { label: 'Sites', to: '/organisation/sites', icon: <PlaceOutlinedIcon />, anyOf: ['organisation.view'] },
      { label: 'Stores', to: '/organisation/stores', icon: <StorefrontOutlinedIcon />, anyOf: ['organisation.view'] },
      { label: 'Locations', to: '/organisation/locations', icon: <WarehouseOutlinedIcon />, anyOf: ['organisation.view'] },
      { label: 'Designations', to: '/organisation/designations', icon: <WorkOutlinedIcon />, anyOf: ['organisation.view'] },
      { label: 'Public services', to: '/organisation/public-services', icon: <GroupsOutlinedIcon />, anyOf: ['organisation.view'] },
      { label: 'Service grades', to: '/organisation/service-grades', icon: <MilitaryTechOutlinedIcon />, anyOf: ['organisation.view'] },
    ],
  },
  {
    label: 'Coming next',
    items: [
      { label: 'Reports & documents', to: '#', icon: <DescriptionOutlinedIcon />, anyOf: [], phase: 8 },
      { label: 'Migration & go-live', to: '#', icon: <LocalShippingOutlinedIcon />, anyOf: [], phase: 9 },
    ],
  },
]
