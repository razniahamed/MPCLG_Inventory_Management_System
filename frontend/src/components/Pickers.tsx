import { Autocomplete, CircularProgress, MenuItem, TextField } from '@mui/material'
import { useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { getPage, listParams } from '../api/client'
import { useLookup } from '../hooks/useLookup'

export interface ItemOption {
  id: number
  item_code: string
  name_en: string
  tracking_method: 'quantity' | 'asset'
  base_unit_id: number
  purchase_unit_id: number
  issue_unit_id: number
  is_paper?: boolean
  base_unit?: { code: string } | null
}

/** Searchable item picker (server-side search). */
export function ItemPicker({
  value,
  onChange,
  label = 'Item',
  filters,
  error,
  disabled,
}: {
  value: ItemOption | null
  onChange: (item: ItemOption | null) => void
  label?: string
  filters?: Record<string, string | boolean>
  error?: string
  disabled?: boolean
}) {
  const [input, setInput] = useState('')
  const [search, setSearch] = useState('')

  useEffect(() => {
    const t = setTimeout(() => setSearch(input), 250)
    return () => clearTimeout(t)
  }, [input])

  const query = useQuery({
    queryKey: ['item-picker', search, filters],
    queryFn: async () => (await getPage<ItemOption>('/items', listParams({ perPage: 20, search, sort: 'name_en', filters: { is_active: true, ...filters } }))).data,
    staleTime: 60_000,
  })

  return (
    <Autocomplete
      value={value}
      onChange={(_, v) => onChange(v)}
      inputValue={input}
      onInputChange={(_, v) => setInput(v)}
      options={query.data ?? []}
      getOptionLabel={(o) => `${o.item_code} — ${o.name_en}`}
      isOptionEqualToValue={(a, b) => a.id === b.id}
      filterOptions={(x) => x}
      loading={query.isFetching}
      disabled={disabled}
      renderInput={(params) => (
        <TextField
          {...params}
          label={label}
          error={Boolean(error)}
          helperText={error}
          slotProps={{
            ...params.slotProps,
            input: {
              ...params.slotProps.input,
              endAdornment: (
                <>
                  {query.isFetching ? <CircularProgress size={16} /> : null}
                  {params.slotProps.input.endAdornment}
                </>
              ),
            },
          }}
        />
      )}
    />
  )
}

/** Unit select limited to the item's base / purchase / issue units. */
export function UnitSelect({ item, value, onChange, label = 'Unit' }: { item: ItemOption | null; value: number | ''; onChange: (id: number) => void; label?: string }) {
  const units = useLookup<{ id: number; code: string; name: string }>('/units', { sort: 'name' })
  const allowed = item ? [...new Set([item.base_unit_id, item.purchase_unit_id, item.issue_unit_id])] : []

  return (
    <TextField select label={label} value={value} onChange={(e) => onChange(Number(e.target.value))} disabled={!item}>
      {(units.data ?? [])
        .filter((u) => allowed.includes(u.id))
        .map((u) => (
          <MenuItem key={u.id} value={u.id}>
            {u.name} ({u.code})
          </MenuItem>
        ))}
    </TextField>
  )
}

/** Generic select backed by a lookup endpoint. */
export function LookupField({
  endpoint,
  label,
  value,
  onChange,
  optionLabel,
  filters,
  required,
  allowEmpty = true,
  disabled,
  helperText,
  valueKey = 'id',
}: {
  endpoint: string
  label: string
  value: number | string | null | undefined
  onChange: (id: number | null) => void
  valueKey?: string
  optionLabel: (row: Record<string, unknown>) => string
  filters?: Record<string, string | number | boolean | undefined>
  required?: boolean
  allowEmpty?: boolean
  disabled?: boolean
  helperText?: string
}) {
  const { data = [], isLoading } = useLookup(endpoint, { filters })
  const numeric = valueKey === 'id'

  return (
    <TextField
      select
      label={label}
      value={value ?? ''}
      onChange={(e) => onChange(e.target.value === '' ? null : ((numeric ? Number(e.target.value) : e.target.value) as number))}
      required={required}
      disabled={disabled}
      helperText={helperText ?? (isLoading ? 'Loading…' : undefined)}
    >
      {allowEmpty && (
        <MenuItem value="">
          <em>{required ? 'Choose…' : 'None'}</em>
        </MenuItem>
      )}
      {data.map((row) => (
        <MenuItem key={String(row[valueKey])} value={row[valueKey] as number}>
          {optionLabel(row)}
        </MenuItem>
      ))}
    </TextField>
  )
}

export const labels = {
  coded: (r: Record<string, unknown>) => `${r.code} — ${(r.name_en ?? r.name) as string}`,
  name: (r: Record<string, unknown>) => String(r.name_en ?? r.name ?? r.full_name),
  officer: (r: Record<string, unknown>) => `${r.full_name} (${r.officer_no})`,
}
