import { Chip, type ChipProps } from '@mui/material'
import { humanize } from '../utils/format'

const COLORS: Record<string, ChipProps['color']> = {
  active: 'success',
  pending: 'warning',
  suspended: 'warning',
  deactivated: 'default',
  expired: 'default',
  revoked: 'default',
  allow: 'success',
  deny: 'error',
  transferred: 'info',
  retired: 'default',
  resigned: 'default',
  enabled: 'success',
  disabled: 'default',
  created: 'success',
  updated: 'info',
  deleted: 'error',
  status_changed: 'warning',
  permission_changed: 'secondary',
}

export function StatusChip({ value, label }: { value: string; label?: string }) {
  return <Chip label={label ?? humanize(value)} color={COLORS[value] ?? 'default'} variant={COLORS[value] ? 'filled' : 'outlined'} />
}

export function BoolChip({ value, yes = 'Active', no = 'Inactive' }: { value: boolean | undefined; yes?: string; no?: string }) {
  return <Chip label={value ? yes : no} color={value ? 'success' : 'default'} variant={value ? 'filled' : 'outlined'} />
}
