import AccountTreeOutlinedIcon from '@mui/icons-material/AccountTreeOutlined'
import AdminPanelSettingsOutlinedIcon from '@mui/icons-material/AdminPanelSettingsOutlined'
import ApartmentOutlinedIcon from '@mui/icons-material/ApartmentOutlined'
import AssignmentOutlinedIcon from '@mui/icons-material/AssignmentOutlined'
import BadgeOutlinedIcon from '@mui/icons-material/BadgeOutlined'
import BuildOutlinedIcon from '@mui/icons-material/BuildOutlined'
import CategoryOutlinedIcon from '@mui/icons-material/CategoryOutlined'
import DashboardOutlinedIcon from '@mui/icons-material/DashboardOutlined'
import DescriptionOutlinedIcon from '@mui/icons-material/DescriptionOutlined'
import ExtensionOutlinedIcon from '@mui/icons-material/ExtensionOutlined'
import GroupsOutlinedIcon from '@mui/icons-material/GroupsOutlined'
import HistoryOutlinedIcon from '@mui/icons-material/HistoryOutlined'
import InventoryOutlinedIcon from '@mui/icons-material/Inventory2Outlined'
import KeyOutlinedIcon from '@mui/icons-material/KeyOutlined'
import LocalShippingOutlinedIcon from '@mui/icons-material/LocalShippingOutlined'
import MilitaryTechOutlinedIcon from '@mui/icons-material/MilitaryTechOutlined'
import PeopleOutlineIcon from '@mui/icons-material/PeopleOutlined'
import PlaceOutlinedIcon from '@mui/icons-material/PlaceOutlined'
import PrintOutlinedIcon from '@mui/icons-material/PrintOutlined'
import QrCode2OutlinedIcon from '@mui/icons-material/QrCode2Outlined'
import SettingsOutlinedIcon from '@mui/icons-material/SettingsOutlined'
import ShieldOutlinedIcon from '@mui/icons-material/ShieldOutlined'
import StorefrontOutlinedIcon from '@mui/icons-material/StorefrontOutlined'
import SwapHorizOutlinedIcon from '@mui/icons-material/SwapHorizOutlined'
import WarehouseOutlinedIcon from '@mui/icons-material/WarehouseOutlined'
import WorkOutlineIcon from '@mui/icons-material/WorkOutlined'
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
  {
    label: '',
    items: [{ label: 'Dashboard', to: '/', icon: <DashboardOutlinedIcon />, anyOf: [] }],
  },
  {
    label: 'Access control',
    items: [
      { label: 'Users', to: '/users', icon: <PeopleOutlineIcon />, anyOf: ['user.view'] },
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
      { label: 'Designations', to: '/organisation/designations', icon: <WorkOutlineIcon />, anyOf: ['organisation.view'] },
      { label: 'Public services', to: '/organisation/public-services', icon: <GroupsOutlinedIcon />, anyOf: ['organisation.view'] },
      { label: 'Service grades', to: '/organisation/service-grades', icon: <MilitaryTechOutlinedIcon />, anyOf: ['organisation.view'] },
    ],
  },
  {
    label: 'Coming in later phases',
    items: [
      { label: 'Item catalogue', to: '#', icon: <CategoryOutlinedIcon />, anyOf: [], phase: 2 },
      { label: 'Receiving (GRN)', to: '#', icon: <LocalShippingOutlinedIcon />, anyOf: [], phase: 3 },
      { label: 'Stock', to: '#', icon: <InventoryOutlinedIcon />, anyOf: [], phase: 3 },
      { label: 'Requests & issues', to: '#', icon: <AssignmentOutlinedIcon />, anyOf: [], phase: 4 },
      { label: 'A4 paper', to: '#', icon: <PrintOutlinedIcon />, anyOf: [], phase: 5 },
      { label: 'Assets & QR', to: '#', icon: <QrCode2OutlinedIcon />, anyOf: [], phase: 6 },
      { label: 'Maintenance', to: '#', icon: <BuildOutlinedIcon />, anyOf: [], phase: 7 },
      { label: 'Reports', to: '#', icon: <DescriptionOutlinedIcon />, anyOf: [], phase: 8 },
    ],
  },
]

export const PROFILE_ICON = <ShieldOutlinedIcon />
