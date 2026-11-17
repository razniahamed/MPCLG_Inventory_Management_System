import type { GridColDef } from '@mui/x-data-grid'
import type { MasterRecord } from '../../api/types'
import type { FieldDef } from '../../components/FormDialog'
import { humanize } from '../../utils/format'
import type { MasterDataConfig } from './MasterDataPage'

const code: GridColDef<MasterRecord> = { field: 'code', headerName: 'Code', width: 140, renderCell: ({ value }) => <span style={{ fontFamily: 'monospace' }}>{String(value)}</span> }
const rel = (field: string, header: string, key: string, labelKey = 'name', width = 200): GridColDef<MasterRecord> => ({
  field,
  headerName: header,
  width,
  sortable: false,
  valueGetter: (_, row) => ((row[key] as Record<string, unknown> | null)?.[labelKey] as string) ?? '—',
})
const enumCol = (field: string, header: string, width = 150): GridColDef<MasterRecord> => ({
  field,
  headerName: header,
  width,
  valueGetter: (v) => (v ? humanize(String(v)) : '—'),
})
const options = (values: string[]) => values.map((v) => ({ value: v, label: humanize(v) }))

const reasonField: FieldDef = { name: 'reason', label: 'Reason (optional)', helper: 'Recorded in the audit trail.' }
const codeField = (max: number): FieldDef => ({ name: 'code', label: 'Code', required: true, width: 6, helper: `Unique, up to ${max} characters.` })
const labelOf = (r: Record<string, unknown>) => `${r.code} — ${(r.name_en ?? r.name) as string}`

const SITE_TYPES = ['main_office', 'bungalow', 'related_institution', 'warehouse', 'other']
const STORE_TYPES = ['general', 'stationery', 'it', 'furniture', 'other']
const LOCATION_TYPES = ['room', 'shelf', 'rack', 'cupboard', 'other']
const DESIGNATION_CATEGORIES = ['executive', 'professional', 'clerical', 'technical', 'minor', 'contract']

export const divisionsConfig: MasterDataConfig = {
  title: 'Divisions',
  singular: 'Division',
  subtitle: 'The branches under the Secretary.',
  endpoint: '/divisions',
  managePermission: 'division.manage',
  sortFields: ['code', 'name_en', 'sort_order'],
  defaultSort: { field: 'sort_order', sort: 'asc' },
  columns: [
    code,
    { field: 'name_en', headerName: 'Name', flex: 1, minWidth: 240 },
    rel('head', 'Headed by', 'head_designation'),
    { field: 'sort_order', headerName: 'Order', width: 80, type: 'number' },
  ],
  fields: [
    codeField(30),
    { name: 'sort_order', label: 'Display order', type: 'number', width: 6 },
    { name: 'name_en', label: 'Name (English)', required: true },
    { name: 'name_si', label: 'Name (Sinhala)', width: 6 },
    { name: 'name_ta', label: 'Name (Tamil)', width: 6 },
    { name: 'head_designation_id', label: 'Head designation', lookup: { endpoint: '/designations', label: labelOf, sort: 'name' } },
    reasonField,
  ],
}

export const departmentsConfig: MasterDataConfig = {
  title: 'Departments',
  singular: 'Department',
  subtitle: 'Working units that raise requests, hold paper statements and are responsible for assets. A department is not a physical place.',
  endpoint: '/departments',
  managePermission: 'department.manage',
  sortFields: ['code', 'name_en', 'active_from'],
  defaultSort: { field: 'name_en', sort: 'asc' },
  columns: [
    code,
    { field: 'name_en', headerName: 'Name', flex: 1, minWidth: 240 },
    rel('division', 'Division', 'division', 'name_en', 220),
    rel('parent', 'Under', 'parent', 'name_en', 180),
    rel('coordinator', 'Coordinator', 'coordinator_officer', 'full_name', 180),
  ],
  fields: [
    codeField(30),
    { name: 'active_from', label: 'Active from', type: 'date', required: true, width: 6, defaultValue: new Date().toISOString().slice(0, 10) },
    { name: 'name_en', label: 'Name (English)', required: true },
    { name: 'name_si', label: 'Name (Sinhala)', width: 6 },
    { name: 'name_ta', label: 'Name (Tamil)', width: 6 },
    { name: 'division_id', label: 'Division', required: true, lookup: { endpoint: '/divisions', label: labelOf, sort: 'sort_order' } },
    { name: 'parent_department_id', label: 'Parent department', lookup: { endpoint: '/departments', label: labelOf, sort: 'name_en' } },
    { name: 'head_officer_id', label: 'Head officer', width: 6, lookup: { endpoint: '/officers', label: (r) => `${r.full_name}`, sort: 'full_name' } },
    { name: 'coordinator_officer_id', label: 'Coordinator (Chief Clerk)', width: 6, lookup: { endpoint: '/officers', label: (r) => `${r.full_name}`, sort: 'full_name' } },
    { name: 'default_site_id', label: 'Default site', lookup: { endpoint: '/sites', label: labelOf, sort: 'name' } },
    { name: 'active_to', label: 'Active until', type: 'date', width: 6 },
    reasonField,
  ],
}

export const sitesConfig: MasterDataConfig = {
  title: 'Sites',
  singular: 'Site',
  subtitle: 'Physical premises: head office, bungalows, related institutions, warehouses.',
  endpoint: '/sites',
  managePermission: 'site.manage',
  sortFields: ['code', 'name'],
  defaultSort: { field: 'name', sort: 'asc' },
  filters: [{ name: 'site_type', label: 'Type', options: options(SITE_TYPES) }],
  columns: [code, { field: 'name', headerName: 'Name', flex: 1, minWidth: 220 }, enumCol('site_type', 'Type', 170), { field: 'phone', headerName: 'Phone', width: 140 }, { field: 'address', headerName: 'Address', flex: 1, minWidth: 200 }],
  fields: [
    codeField(30),
    { name: 'site_type', label: 'Type', type: 'select', required: true, options: options(SITE_TYPES), width: 6 },
    { name: 'name', label: 'Name', required: true },
    { name: 'address', label: 'Address', type: 'textarea' },
    { name: 'phone', label: 'Phone', width: 6 },
    reasonField,
  ],
}

export const storesConfig: Omit<MasterDataConfig, 'rowActions'> = {
  title: 'Stores',
  singular: 'Store',
  subtitle: 'Store rooms that receive and issue goods. Storekeepers are usually given roles limited to their store.',
  endpoint: '/stores',
  managePermission: 'store.manage',
  sortFields: ['code', 'name'],
  defaultSort: { field: 'name', sort: 'asc' },
  filters: [{ name: 'store_type', label: 'Type', options: options(STORE_TYPES) }],
  columns: [code, { field: 'name', headerName: 'Name', flex: 1, minWidth: 220 }, enumCol('store_type', 'Type'), rel('site', 'Site', 'site'), rel('responsible', 'Responsible officer', 'responsible_officer', 'full_name')],
  fields: [
    codeField(30),
    { name: 'store_type', label: 'Type', type: 'select', options: options(STORE_TYPES), width: 6 },
    { name: 'name', label: 'Name', required: true },
    { name: 'site_id', label: 'Site', required: true, lookup: { endpoint: '/sites', label: labelOf, sort: 'name' } },
    { name: 'responsible_officer_id', label: 'Responsible officer', lookup: { endpoint: '/officers', label: (r) => `${r.full_name}`, sort: 'full_name' } },
    reasonField,
  ],
}

export const locationsConfig: MasterDataConfig = {
  title: 'Locations',
  singular: 'Location',
  subtitle: 'Rooms, shelves, racks and cupboards inside a site, optionally belonging to a store.',
  endpoint: '/locations',
  managePermission: 'location.manage',
  sortFields: ['code', 'name'],
  defaultSort: { field: 'code', sort: 'asc' },
  filters: [{ name: 'location_type', label: 'Type', options: options(LOCATION_TYPES) }],
  columns: [code, { field: 'name', headerName: 'Name', flex: 1, minWidth: 200 }, enumCol('location_type', 'Type', 120), rel('site', 'Site', 'site'), rel('store', 'Store', 'store'), rel('parent', 'Inside', 'parent')],
  fields: [
    { name: 'site_id', label: 'Site', required: true, lookup: { endpoint: '/sites', label: labelOf, sort: 'name' } },
    { name: 'code', label: 'Code', required: true, width: 6, helper: 'Unique within the site.' },
    { name: 'location_type', label: 'Type', type: 'select', required: true, options: options(LOCATION_TYPES), width: 6 },
    { name: 'name', label: 'Name', required: true },
    { name: 'store_id', label: 'Store', width: 6, lookup: { endpoint: '/stores', label: labelOf, sort: 'name' } },
    { name: 'parent_location_id', label: 'Inside location', width: 6, lookup: { endpoint: '/locations', label: labelOf, sort: 'code' } },
    reasonField,
  ],
}

export const designationsConfig: MasterDataConfig = {
  title: 'Designations',
  singular: 'Designation',
  subtitle: 'Posts on the organisation chart. Old transactions keep the designation as it was at the time.',
  endpoint: '/designations',
  managePermission: 'designation.manage',
  sortFields: ['code', 'name'],
  defaultSort: { field: 'name', sort: 'asc' },
  filters: [{ name: 'category', label: 'Category', options: options(DESIGNATION_CATEGORIES) }],
  columns: [code, { field: 'name', headerName: 'Name', flex: 1, minWidth: 260 }, enumCol('category', 'Category'), { field: 'is_head_post', headerName: 'Head post', width: 110, type: 'boolean' }],
  fields: [
    codeField(30),
    { name: 'category', label: 'Category', type: 'select', options: options(DESIGNATION_CATEGORIES), width: 6 },
    { name: 'name', label: 'Name', required: true },
    { name: 'is_head_post', label: 'Head of a unit', type: 'switch' },
    reasonField,
  ],
}

export const publicServicesConfig: MasterDataConfig = {
  title: 'Public services',
  singular: 'Public service',
  subtitle: 'S.L.A.S., S.L.Ac.S., S.L.Eng.S. and other services officers belong to.',
  endpoint: '/public-services',
  managePermission: 'designation.manage',
  sortFields: ['code', 'name'],
  defaultSort: { field: 'code', sort: 'asc' },
  columns: [
    code,
    { field: 'name', headerName: 'Name', flex: 1, minWidth: 300 },
    { field: 'grades', headerName: 'Grades', width: 220, sortable: false, valueGetter: (v) => ((v as { code: string }[] | undefined) ?? []).map((g) => g.code).join(', ') || '—' },
  ],
  fields: [codeField(30), { name: 'name', label: 'Name', required: true }, reasonField],
}

export const serviceGradesConfig: MasterDataConfig = {
  title: 'Service grades',
  singular: 'Service grade',
  subtitle: 'Grades within each public service (Special, I, II, III …).',
  endpoint: '/service-grades',
  managePermission: 'designation.manage',
  sortFields: ['code', 'rank'],
  defaultSort: { field: 'rank', sort: 'asc' },
  columns: [rel('service', 'Service', 'public_service', 'name', 320), code, { field: 'rank', headerName: 'Rank', width: 90, type: 'number' }],
  fields: [
    { name: 'public_service_id', label: 'Public service', required: true, lookup: { endpoint: '/public-services', label: labelOf, sort: 'code' } },
    { name: 'code', label: 'Grade', required: true, width: 6 },
    { name: 'rank', label: 'Rank (0 = highest)', type: 'number', width: 6 },
    reasonField,
  ],
}
