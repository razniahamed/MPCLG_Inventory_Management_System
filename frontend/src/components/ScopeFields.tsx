import { Grid, MenuItem, TextField } from '@mui/material'
import type { ScopeType } from '../api/types'
import { useLookup } from '../hooks/useLookup'

const ENDPOINT: Record<Exclude<ScopeType, 'global'>, { endpoint: string; label: (r: Record<string, unknown>) => string }> = {
  division: { endpoint: '/divisions', label: (r) => `${r.code} — ${r.name_en}` },
  department: { endpoint: '/departments', label: (r) => `${r.code} — ${r.name_en}` },
  store: { endpoint: '/stores', label: (r) => `${r.code} — ${r.name}` },
}

export function ScopeFields({
  scopeType,
  scopeId,
  onChange,
  disabled,
  errorType,
  errorId,
}: {
  scopeType: ScopeType
  scopeId: number | null
  onChange: (type: ScopeType, id: number | null) => void
  disabled?: boolean
  errorType?: string
  errorId?: string
}) {
  const config = scopeType === 'global' ? null : ENDPOINT[scopeType]
  const { data = [], isLoading } = useLookup(config?.endpoint ?? null)

  return (
    <Grid container spacing={2}>
      <Grid size={{ xs: 12, sm: 5 }}>
        <TextField select label="Applies to" value={scopeType} onChange={(e) => onChange(e.target.value as ScopeType, null)} disabled={disabled} error={Boolean(errorType)} helperText={errorType}>
          <MenuItem value="global">Everywhere (global)</MenuItem>
          <MenuItem value="division">One division</MenuItem>
          <MenuItem value="department">One department</MenuItem>
          <MenuItem value="store">One store</MenuItem>
        </TextField>
      </Grid>
      {config && (
        <Grid size={{ xs: 12, sm: 7 }}>
          <TextField
            select
            label={scopeType.charAt(0).toUpperCase() + scopeType.slice(1)}
            value={scopeId ?? ''}
            onChange={(e) => onChange(scopeType, Number(e.target.value))}
            disabled={disabled}
            required
            error={Boolean(errorId)}
            helperText={errorId ?? (isLoading ? 'Loading…' : undefined)}
          >
            {data.map((r) => (
              <MenuItem key={String(r.id)} value={r.id as number}>
                {config.label(r)}
              </MenuItem>
            ))}
          </TextField>
        </Grid>
      )}
    </Grid>
  )
}

/** Human label for a scope, using lookups when available. */
export function useScopeLabel() {
  const divisions = useLookup('/divisions')
  const departments = useLookup('/departments')
  const stores = useLookup('/stores')

  return (type: ScopeType, id: number | null): string => {
    if (type === 'global') return 'Everywhere'
    const list = type === 'division' ? divisions.data : type === 'department' ? departments.data : stores.data
    const row = list?.find((r) => r.id === id)
    if (!row) return `${type} #${id}`
    return `${type === 'store' ? 'Store' : type === 'division' ? 'Division' : 'Dept'}: ${(row.name_en ?? row.name) as string}`
  }
}
