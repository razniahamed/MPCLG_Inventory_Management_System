import { Chip } from '@mui/material'
import type { MasterRecord } from '../../api/types'
import { CodeText } from '../../components/ui'
import type { FieldDef } from '../../components/FormDialog'
import { humanize } from '../../utils/format'
import type { MasterDataConfig } from '../organisation/MasterDataPage'

const rel = (key: string, labelKey = 'name'): ((v: unknown, row: MasterRecord) => string) => (_, row) =>
  ((row[key] as Record<string, unknown> | null)?.[labelKey] as string) ?? '—'
const coded = (r: Record<string, unknown>) => `${r.code} — ${(r.name_en ?? r.name) as string}`
const reason: FieldDef = { name: 'reason', label: 'Reason (optional)' }

export const categoriesConfig: MasterDataConfig = {
  title: 'Item categories',
  singular: 'Category',
  subtitle: 'Groups of items. The default tracking method decides whether new items are counted by quantity or registered as individual assets.',
  endpoint: '/item-categories',
  managePermission: 'category.manage',
  sortFields: ['code', 'name'],
  defaultSort: { field: 'name', sort: 'asc' },
  filters: [{ name: 'default_tracking_method', label: 'Tracking', options: [{ value: 'quantity', label: 'By quantity' }, { value: 'asset', label: 'As assets' }] }],
  columns: [
    { field: 'code', headerName: 'Code', width: 120, renderCell: ({ value }) => <CodeText>{String(value)}</CodeText> },
    { field: 'name', headerName: 'Name', flex: 1, minWidth: 220 },
    { field: 'parent', headerName: 'Under', width: 200, sortable: false, valueGetter: rel('parent') },
    { field: 'default_tracking_method', headerName: 'Tracking', width: 140, renderCell: ({ value }) => <Chip label={value === 'asset' ? 'Assets' : 'Quantity'} color={value === 'asset' ? 'info' : 'default'} variant="outlined" /> },
  ],
  fields: [
    { name: 'code', label: 'Code', required: true, width: 6 },
    { name: 'default_tracking_method', label: 'Default tracking', type: 'select', width: 6, options: [{ value: 'quantity', label: 'By quantity' }, { value: 'asset', label: 'As individual assets' }], defaultValue: 'quantity' },
    { name: 'name', label: 'Name', required: true },
    { name: 'parent_id', label: 'Parent category', lookup: { endpoint: '/item-categories', label: coded, sort: 'name' } },
    reason,
  ],
}

export const unitsConfig: MasterDataConfig = {
  title: 'Units of measure',
  singular: 'Unit',
  subtitle: 'The ledger always stores an item in its base unit; other units need an approved per-item conversion.',
  endpoint: '/units',
  managePermission: 'unit.manage',
  sortFields: ['code', 'name'],
  defaultSort: { field: 'name', sort: 'asc' },
  columns: [
    { field: 'code', headerName: 'Code', width: 120, renderCell: ({ value }) => <CodeText>{String(value)}</CodeText> },
    { field: 'name', headerName: 'Name', flex: 1, minWidth: 200 },
    { field: 'kind', headerName: 'Kind', width: 140, valueGetter: (v) => humanize(String(v ?? '')) },
  ],
  fields: [
    { name: 'code', label: 'Code', required: true, width: 6 },
    { name: 'kind', label: 'Kind', type: 'select', width: 6, options: ['count', 'weight', 'volume', 'length', 'pack'].map((k) => ({ value: k, label: humanize(k) })), defaultValue: 'count' },
    { name: 'name', label: 'Name', required: true },
    reason,
  ],
}

export const suppliersConfig: MasterDataConfig = {
  title: 'Suppliers',
  singular: 'Supplier',
  subtitle: 'Goods suppliers and service / repair providers. Bank details are not kept here — payment stays with Finance.',
  endpoint: '/suppliers',
  managePermission: 'supplier.manage',
  sortFields: ['code', 'name'],
  defaultSort: { field: 'name', sort: 'asc' },
  filters: [{ name: 'supplier_type', label: 'Type', options: ['goods', 'service', 'both'].map((v) => ({ value: v, label: humanize(v) })) }],
  columns: [
    { field: 'code', headerName: 'Code', width: 110, renderCell: ({ value }) => <CodeText>{String(value)}</CodeText> },
    { field: 'name', headerName: 'Name', flex: 1, minWidth: 220 },
    { field: 'supplier_type', headerName: 'Type', width: 110, valueGetter: (v) => humanize(String(v ?? '')) },
    { field: 'contact_person', headerName: 'Contact', width: 160 },
    { field: 'phone', headerName: 'Phone', width: 140 },
    { field: 'categories', headerName: 'Supplies', width: 220, sortable: false, valueGetter: (v) => ((v as { name: string }[] | undefined) ?? []).map((c) => c.name).join(', ') },
  ],
  fields: [
    { name: 'code', label: 'Code', required: true, width: 6 },
    { name: 'supplier_type', label: 'Type', type: 'select', required: true, width: 6, options: ['goods', 'service', 'both'].map((v) => ({ value: v, label: humanize(v) })), defaultValue: 'goods' },
    { name: 'name', label: 'Name', required: true },
    { name: 'contact_person', label: 'Contact person', width: 6 },
    { name: 'phone', label: 'Phone', width: 6 },
    { name: 'email', label: 'Email', type: 'email', width: 6 },
    { name: 'tax_ref', label: 'Tax / VAT reference', width: 6 },
    { name: 'address', label: 'Address', type: 'textarea' },
    reason,
  ],
}

const unitLookup = { endpoint: '/units', label: coded, sort: 'name' }

export const itemsConfig: MasterDataConfig = {
  title: 'Items',
  singular: 'Item',
  subtitle: 'The catalogue. Consumables are tracked by quantity; controlled assets (computers, furniture …) get individual asset numbers and QR codes.',
  endpoint: '/items',
  managePermission: 'item.manage',
  sortFields: ['item_code', 'name_en'],
  defaultSort: { field: 'name_en', sort: 'asc' },
  rowLink: (row) => `/catalogue/items/${row.id}`,
  filters: [
    { name: 'tracking_method', label: 'Tracking', options: [{ value: 'quantity', label: 'By quantity' }, { value: 'asset', label: 'As assets' }] },
    { name: 'is_paper', label: 'Paper', options: [{ value: 'true', label: 'Paper items' }] },
  ],
  columns: [
    { field: 'item_code', headerName: 'Code', width: 140, renderCell: ({ value }) => <CodeText>{String(value)}</CodeText> },
    { field: 'name_en', headerName: 'Name', flex: 1, minWidth: 220 },
    { field: 'category', headerName: 'Category', width: 160, sortable: false, valueGetter: rel('category') },
    { field: 'tracking_method', headerName: 'Tracking', width: 120, renderCell: ({ value }) => <Chip label={value === 'asset' ? 'Asset' : 'Quantity'} color={value === 'asset' ? 'info' : 'default'} variant="outlined" /> },
    { field: 'base_unit', headerName: 'Base unit', width: 110, sortable: false, valueGetter: rel('base_unit', 'code') },
    { field: 'reorder_level', headerName: 'Reorder at', width: 110, type: 'number', valueGetter: (v) => (v === null ? null : Number(v)) },
  ],
  fields: [
    { name: 'item_code', label: 'Item code', required: true, width: 6, helper: 'Permanent code, e.g. PAP-A4-80' },
    { name: 'legacy_code', label: 'Old register code', width: 6 },
    { name: 'name_en', label: 'Name (English)', required: true },
    { name: 'name_si', label: 'Name (Sinhala)', width: 6 },
    { name: 'name_ta', label: 'Name (Tamil)', width: 6 },
    { name: 'category_id', label: 'Category', required: true, width: 6, lookup: { endpoint: '/item-categories', label: coded, sort: 'name' } },
    { name: 'tracking_method', label: 'Tracking', type: 'select', required: true, width: 6, lockedOnEdit: true, options: [{ value: 'quantity', label: 'By quantity' }, { value: 'asset', label: 'As individual assets' }], defaultValue: 'quantity' },
    { name: 'base_unit_id', label: 'Base unit (ledger)', required: true, width: 4, lookup: unitLookup, lockedOnEdit: true },
    { name: 'purchase_unit_id', label: 'Purchase unit', required: true, width: 4, lookup: unitLookup },
    { name: 'issue_unit_id', label: 'Issue unit', required: true, width: 4, lookup: unitLookup },
    { name: 'reorder_level', label: 'Reorder level (base units)', type: 'number', width: 6 },
    { name: 'reorder_qty', label: 'Reorder quantity', type: 'number', width: 6 },
    { name: 'brand', label: 'Brand', width: 6 },
    { name: 'is_paper', label: 'A4 paper controls apply', type: 'switch', width: 6 },
    { name: 'specification', label: 'Specification', type: 'textarea' },
    reason,
  ],
}
