import {
  Alert,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  Grid,
  MenuItem,
  Switch,
  TextField,
} from '@mui/material'
import { useEffect, useRef, useState } from 'react'
import { ApiError } from '../api/client'
import { useLookup } from '../hooks/useLookup'
import { errorMessage } from './Notify'

export type FieldType = 'text' | 'email' | 'textarea' | 'number' | 'select' | 'switch' | 'date'

export interface FieldDef {
  name: string
  label: string
  type?: FieldType
  required?: boolean
  /** Only shown when creating (e.g. permanent codes). */
  createOnly?: boolean
  /** Shown but not editable after creation. */
  lockedOnEdit?: boolean
  options?: { value: string | number; label: string }[]
  /** Dropdown filled from an API list endpoint. */
  lookup?: { endpoint: string; label: (row: Record<string, unknown>) => string; sort?: string }
  helper?: string
  width?: 4 | 6 | 12
  defaultValue?: unknown
}

type Values = Record<string, unknown>

function LookupSelect({ field, value, onChange, error, disabled }: { field: FieldDef; value: unknown; onChange: (v: unknown) => void; error?: string; disabled?: boolean }) {
  const { data = [], isLoading } = useLookup(field.lookup!.endpoint, { sort: field.lookup!.sort })

  return (
    <TextField
      select
      label={field.label}
      value={value ?? ''}
      onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))}
      required={field.required}
      error={Boolean(error)}
      helperText={error ?? field.helper ?? (isLoading ? 'Loading…' : undefined)}
      disabled={disabled}
    >
      {!field.required && (
        <MenuItem value="">
          <em>None</em>
        </MenuItem>
      )}
      {data.map((row) => (
        <MenuItem key={String(row.id)} value={row.id as number}>
          {field.lookup!.label(row)}
        </MenuItem>
      ))}
    </TextField>
  )
}

/**
 * Create / edit dialog generated from field definitions. Shows the API's
 * per-field validation messages next to the inputs.
 */
export function FormDialog({
  open,
  title,
  fields,
  initial,
  submitLabel = 'Save',
  onClose,
  onSubmit,
}: {
  open: boolean
  title: string
  fields: FieldDef[]
  initial?: Values | null
  submitLabel?: string
  onClose: () => void
  onSubmit: (values: Values) => Promise<unknown>
}) {
  const editing = Boolean(initial)
  const [values, setValues] = useState<Values>({})
  const [error, setError] = useState<unknown>(null)
  const [busy, setBusy] = useState(false)

  // Reset only when the dialog opens (fields may be re-created on every render).
  const fieldsRef = useRef(fields)
  fieldsRef.current = fields
  useEffect(() => {
    if (!open) return
    const start: Values = {}
    fieldsRef.current.forEach((f) => {
      const value = initial?.[f.name] ?? f.defaultValue ?? (f.type === 'switch' ? false : null)
      start[f.name] = f.type === 'date' && typeof value === 'string' ? value.slice(0, 10) : value
    })
    if (initial) start.is_active = initial.is_active
    setValues(start)
    setError(null)
  }, [open, initial])

  const set = (name: string, value: unknown) => setValues((v) => ({ ...v, [name]: value }))

  const submit = async () => {
    setBusy(true)
    setError(null)
    try {
      const payload: Values = {}
      fields.forEach((f) => {
        if (editing && (f.createOnly || f.lockedOnEdit)) return
        const v = values[f.name]
        payload[f.name] = v === '' ? null : v
      })
      if (editing && typeof values.is_active === 'boolean' && !('is_active' in payload)) {
        payload.is_active = values.is_active
      }
      await onSubmit(payload)
      onClose()
    } catch (e) {
      setError(e)
    } finally {
      setBusy(false)
    }
  }

  const fieldError = (name: string) => (error instanceof ApiError ? error.field(name) : undefined)
  const generalError = error !== null && !(error instanceof ApiError && Object.keys(error.errors).some((k) => fields.some((f) => f.name === k)))

  return (
    <Dialog open={open} onClose={() => !busy && onClose()} maxWidth="sm" fullWidth>
      <DialogTitle>{title}</DialogTitle>
      <DialogContent dividers>
        <Grid container spacing={2} sx={{ pt: 0.5 }}>
          {fields
            .filter((f) => !(editing && f.createOnly))
            .map((f) => {
              const disabled = editing && f.lockedOnEdit
              const err = fieldError(f.name)
              const v = values[f.name]
              let input
              if (f.lookup) {
                input = <LookupSelect field={f} value={v} onChange={(x) => set(f.name, x)} error={err} disabled={disabled} />
              } else if (f.type === 'switch') {
                input = <FormControlLabel control={<Switch checked={Boolean(v)} onChange={(e) => set(f.name, e.target.checked)} />} label={f.label} />
              } else if (f.type === 'select') {
                input = (
                  <TextField select label={f.label} value={v ?? ''} onChange={(e) => set(f.name, e.target.value)} required={f.required} error={Boolean(err)} helperText={err ?? f.helper} disabled={disabled}>
                    {!f.required && (
                      <MenuItem value="">
                        <em>None</em>
                      </MenuItem>
                    )}
                    {f.options?.map((o) => (
                      <MenuItem key={o.value} value={o.value}>
                        {o.label}
                      </MenuItem>
                    ))}
                  </TextField>
                )
              } else {
                input = (
                  <TextField
                    label={f.label}
                    type={f.type === 'textarea' ? 'text' : f.type ?? 'text'}
                    multiline={f.type === 'textarea'}
                    minRows={f.type === 'textarea' ? 3 : undefined}
                    value={v ?? ''}
                    onChange={(e) => set(f.name, f.type === 'number' ? (e.target.value === '' ? null : Number(e.target.value)) : e.target.value)}
                    required={f.required}
                    error={Boolean(err)}
                    helperText={err ?? f.helper}
                    disabled={disabled}
                    slotProps={f.type === 'date' ? { inputLabel: { shrink: true } } : undefined}
                  />
                )
              }
              return (
                <Grid key={f.name} size={{ xs: 12, sm: f.width ?? 12 }}>
                  {input}
                </Grid>
              )
            })}
          {editing && 'is_active' in (initial ?? {}) && !fields.some((f) => f.name === 'is_active') && (
            <Grid size={12}>
              <FormControlLabel control={<Switch checked={Boolean(values.is_active)} onChange={(e) => set('is_active', e.target.checked)} />} label="Active" />
            </Grid>
          )}
        </Grid>
        {generalError && (
          <Alert severity="error" sx={{ mt: 2 }}>
            {errorMessage(error)}
          </Alert>
        )}
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={busy}>
          Cancel
        </Button>
        <Button variant="contained" onClick={submit} disabled={busy}>
          {busy ? 'Saving…' : submitLabel}
        </Button>
      </DialogActions>
    </Dialog>
  )
}
