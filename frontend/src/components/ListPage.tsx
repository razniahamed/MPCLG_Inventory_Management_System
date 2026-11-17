import AddIcon from '@mui/icons-material/Add'
import { Button, MenuItem, TextField } from '@mui/material'
import type { GridColDef, GridSortModel, GridValidRowModel } from '@mui/x-data-grid'
import { useState, type ReactNode } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useLookup } from '../hooks/useLookup'
import { humanize } from '../utils/format'
import { PageHeader } from './PageHeader'
import { ServerTable } from './ServerTable'

export interface FilterDef {
  name: string
  label: string
  options?: (string | { value: string; label: string })[]
  lookup?: { endpoint: string; label: (r: Record<string, unknown>) => string }
  width?: number
}

function LookupFilter({ def, value, onChange }: { def: FilterDef; value: string; onChange: (v: string) => void }) {
  const { data = [] } = useLookup(def.lookup!.endpoint)

  return (
    <TextField select label={def.label} value={value} onChange={(e) => onChange(e.target.value)} sx={{ width: { xs: '100%', sm: def.width ?? 200 } }}>
      <MenuItem value="">All</MenuItem>
      {data.map((r) => (
        <MenuItem key={String(r.id)} value={String(r.id)}>
          {def.lookup!.label(r)}
        </MenuItem>
      ))}
    </TextField>
  )
}

/**
 * Standard list screen: header, create button, filters (initialised from the
 * URL so dashboard links open pre-filtered) and a server-side table.
 */
export function ListPage<T extends GridValidRowModel>({
  title,
  subtitle,
  endpoint,
  queryKey,
  columns,
  sortFields,
  defaultSort,
  filters = [],
  fixedFilters,
  searchPlaceholder,
  createLabel,
  onCreate,
  rowLink,
  onRowClick,
  actions,
  searchAs,
}: {
  title: string
  subtitle?: ReactNode
  endpoint: string
  queryKey: unknown[]
  columns: GridColDef<T>[]
  sortFields?: string[]
  defaultSort?: GridSortModel
  filters?: FilterDef[]
  fixedFilters?: Record<string, string | number | boolean | undefined>
  searchPlaceholder?: string
  createLabel?: string
  onCreate?: () => void
  rowLink?: (row: T) => string
  onRowClick?: (row: T) => void
  actions?: ReactNode
  searchAs?: string
}) {
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const [values, setValues] = useState<Record<string, string>>(() => Object.fromEntries(filters.map((f) => [f.name, params.get(f.name) ?? ''])))

  return (
    <>
      <PageHeader
        title={title}
        subtitle={subtitle}
        actions={
          <>
            {actions}
            {onCreate && (
              <Button variant="contained" startIcon={<AddIcon />} onClick={onCreate}>
                {createLabel ?? 'New'}
              </Button>
            )}
          </>
        }
      />
      <ServerTable<T>
        queryKey={queryKey}
        endpoint={endpoint}
        columns={columns}
        sortFields={sortFields}
        defaultSort={defaultSort}
        searchPlaceholder={searchPlaceholder}
        searchAs={searchAs}
        filters={{ ...fixedFilters, ...values }}
        onRowClick={onRowClick ?? (rowLink ? (row) => navigate(rowLink(row)) : undefined)}
        toolbar={filters.map((f) =>
          f.lookup ? (
            <LookupFilter key={f.name} def={f} value={values[f.name] ?? ''} onChange={(v) => setValues((x) => ({ ...x, [f.name]: v }))} />
          ) : (
            <TextField
              key={f.name}
              select
              label={f.label}
              value={values[f.name] ?? ''}
              onChange={(e) => setValues((x) => ({ ...x, [f.name]: e.target.value }))}
              sx={{ width: { xs: '100%', sm: f.width ?? 180 } }}
            >
              <MenuItem value="">All</MenuItem>
              {f.options!.map((o) => {
                const opt = typeof o === 'string' ? { value: o, label: humanize(o) } : o
                return (
                  <MenuItem key={opt.value} value={opt.value}>
                    {opt.label}
                  </MenuItem>
                )
              })}
            </TextField>
          ),
        )}
      />
    </>
  )
}
