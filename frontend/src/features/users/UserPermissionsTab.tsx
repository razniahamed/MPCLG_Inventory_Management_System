import CheckCircleIcon from '@mui/icons-material/CheckCircle'
import RemoveCircleOutlineIcon from '@mui/icons-material/RemoveCircleOutlined'
import {
  Alert,
  Box,
  Card,
  Chip,
  FormControlLabel,
  MenuItem,
  Skeleton,
  Stack,
  Switch,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  ToggleButton,
  ToggleButtonGroup,
  Tooltip,
  Typography,
} from '@mui/material'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { get, send } from '../../api/client'
import type { EffectivePermissionRow, ScopeType, User, UserOverride } from '../../api/types'
import { useAuth } from '../../auth/AuthContext'
import { useNotify } from '../../components/Notify'
import { ReasonDialog } from '../../components/ReasonDialog'
import { ScopeFields, useScopeLabel } from '../../components/ScopeFields'
import { humanize, localInputToIso } from '../../utils/format'

type Effect = 'allow' | 'deny' | 'inherit'

const REASONS: Record<string, string> = {
  allowed_by_role: 'From role',
  allowed_by_override: 'Turned on for this user',
  allowed_by_delegation: 'Delegated',
  denied_by_override: 'Turned off for this user',
  not_granted: 'Not granted',
  module_disabled: 'Module switched off',
  permission_disabled: 'Switched off for everyone',
  account_not_active: 'Account not active',
  account_locked: 'Account locked',
}

interface Pending {
  code: string
  effect: Effect
  supportsScope: boolean
}

/**
 * Turn each permission on / off / back to role default for this one user (Section 5.1).
 */
export function UserPermissionsTab({ user }: { user: User }) {
  const { can } = useAuth()
  const notify = useNotify()
  const queryClient = useQueryClient()
  const scopeLabel = useScopeLabel()
  const editable = can('permission.override_user') && user.status !== 'deactivated'

  const [module, setModule] = useState('')
  const [onlyAllowed, setOnlyAllowed] = useState(false)
  const [search, setSearch] = useState('')
  const [pending, setPending] = useState<Pending | null>(null)
  const [scopeType, setScopeType] = useState<ScopeType>('global')
  const [scopeId, setScopeId] = useState<number | null>(null)
  const [validTo, setValidTo] = useState('')

  const effective = useQuery({
    queryKey: ['user-effective', user.id],
    queryFn: () => get<{ account_active: boolean; permissions: EffectivePermissionRow[] }>(`/users/${user.id}/effective-permissions`),
  })
  const overrides = useQuery({
    queryKey: ['user-overrides', user.id],
    queryFn: () => get<UserOverride[]>(`/users/${user.id}/permissions`),
  })
  const catalogue = useQuery({
    queryKey: ['permission-catalogue'],
    queryFn: () => get<{ code: string; permissions: { code: string; supports_scope: boolean; is_sensitive: boolean; name: string }[] }[]>('/permissions'),
    staleTime: 5 * 60_000,
  })

  const meta = useMemo(() => {
    const map = new Map<string, { supports_scope: boolean; is_sensitive: boolean; name: string }>()
    catalogue.data?.forEach((m) => m.permissions.forEach((p) => map.set(p.code, p)))
    return map
  }, [catalogue.data])

  const overridesByCode = useMemo(() => {
    const map = new Map<string, UserOverride[]>()
    overrides.data?.forEach((o) => {
      if (!o.permission) return
      map.set(o.permission, [...(map.get(o.permission) ?? []), o])
    })
    return map
  }, [overrides.data])

  const rows = (effective.data?.permissions ?? []).filter(
    (r) => (!module || r.module === module) && (!onlyAllowed || r.allowed) && (!search || r.code.includes(search.toLowerCase())),
  )
  const modules = [...new Set((effective.data?.permissions ?? []).map((r) => r.module))]

  const currentEffect = (code: string): Effect => {
    const global = overridesByCode.get(code)?.find((o) => o.scope_type === 'global')
    return global ? global.effect : 'inherit'
  }

  const apply = async (reason: string) => {
    if (!pending) return
    await send('put', `/users/${user.id}/permissions/${pending.code}`, {
      effect: pending.effect,
      reason,
      ...(pending.effect !== 'inherit' && scopeType !== 'global' ? { scope: { scope_type: scopeType, scope_id: scopeId } } : {}),
      ...(pending.effect !== 'inherit' && validTo ? { valid_to: localInputToIso(validTo) } : {}),
    })
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['user-effective', user.id] }),
      queryClient.invalidateQueries({ queryKey: ['user-overrides', user.id] }),
    ])
    notify.success(pending.effect === 'inherit' ? `${pending.code} now follows the user's roles.` : `${pending.code} turned ${pending.effect === 'allow' ? 'on' : 'off'}.`)
  }

  const openChange = (code: string, effect: Effect) => {
    setScopeType('global')
    setScopeId(null)
    setValidTo('')
    setPending({ code, effect, supportsScope: meta.get(code)?.supports_scope ?? false })
  }

  if (effective.isLoading || overrides.isLoading) return <Skeleton height={300} />

  const allowedCount = effective.data?.permissions.filter((p) => p.allowed).length ?? 0

  return (
    <Card>
      <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} sx={{ p: 2, alignItems: { md: 'center' } }}>
        <TextField label="Search permission" value={search} onChange={(e) => setSearch(e.target.value)} sx={{ maxWidth: { md: 260 } }} />
        <TextField select label="Module" value={module} onChange={(e) => setModule(e.target.value)} sx={{ maxWidth: { md: 200 } }}>
          <MenuItem value="">All modules</MenuItem>
          {modules.map((m) => (
            <MenuItem key={m} value={m}>
              {humanize(m)}
            </MenuItem>
          ))}
        </TextField>
        <FormControlLabel control={<Switch checked={onlyAllowed} onChange={(e) => setOnlyAllowed(e.target.checked)} />} label="Only what they can do" />
        <Box sx={{ flex: 1 }} />
        <Typography sx={{ fontSize: 13 }} color="text.secondary">
          {allowedCount} of {effective.data?.permissions.length} allowed
        </Typography>
      </Stack>
      {effective.data && !effective.data.account_active && (
        <Alert severity="warning" sx={{ mx: 2, mb: 2 }}>
          This account is {user.status}{user.is_locked ? ' and locked' : ''}, so it cannot use anything right now. The table shows what will apply once it is active.
        </Alert>
      )}
      <Box sx={{ overflowX: 'auto' }}>
        <Table size="small" stickyHeader>
          <TableHead>
            <TableRow>
              <TableCell>Permission</TableCell>
              <TableCell>Result</TableCell>
              <TableCell>Why</TableCell>
              <TableCell align="right">Personal override</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {rows.map((r) => {
              const scopedOverrides = (overridesByCode.get(r.code) ?? []).filter((o) => o.scope_type !== 'global')
              const p = meta.get(r.code)
              return (
                <TableRow key={r.code} hover>
                  <TableCell>
                    <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                      <Typography sx={{ fontSize: 14, fontFamily: 'monospace' }}>
                        {r.code}
                      </Typography>
                      {p?.is_sensitive && <Chip label="approval" color="secondary" variant="outlined" sx={{ height: 18, fontSize: 10 }} />}
                    </Stack>
                    <Typography sx={{ fontSize: 12 }} color="text.secondary">
                      {humanize(r.module)}
                    </Typography>
                  </TableCell>
                  <TableCell>
                    {r.allowed ? (
                      <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center', color: 'success.main' }}>
                        <CheckCircleIcon fontSize="small" />
                        <Typography sx={{ fontSize: 13 }}>Allowed</Typography>
                      </Stack>
                    ) : (
                      <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center', color: 'text.disabled' }}>
                        <RemoveCircleOutlineIcon fontSize="small" />
                        <Typography sx={{ fontSize: 13 }}>No</Typography>
                      </Stack>
                    )}
                  </TableCell>
                  <TableCell>
                    <Typography sx={{ fontSize: 13 }}>{REASONS[r.reason] ?? humanize(r.reason)}</Typography>
                    <Stack direction="row" spacing={0.5} useFlexGap sx={{ flexWrap: 'wrap', mt: 0.5 }}>
                      {r.sources.map((s, i) => (
                        <Tooltip key={i} title={s.valid_to ? `Until ${s.valid_to}` : 'No end date'}>
                          <Chip variant="outlined" label={`${s.source === 'role' ? s.label : s.source}${s.scope_type !== 'global' ? ' · ' + scopeLabel(s.scope_type, s.scope_id) : ''}`} sx={{ height: 20, fontSize: 11 }} />
                        </Tooltip>
                      ))}
                      {scopedOverrides.map((o) => (
                        <Chip key={o.id} color={o.effect === 'allow' ? 'success' : 'error'} label={`${o.effect} · ${scopeLabel(o.scope_type, o.scope_id)}`} onDelete={editable ? () => openChange(r.code, 'inherit') : undefined} sx={{ height: 20, fontSize: 11 }} />
                      ))}
                    </Stack>
                  </TableCell>
                  <TableCell align="right">
                    <ToggleButtonGroup
                      size="small"
                      exclusive
                      value={currentEffect(r.code)}
                      disabled={!editable}
                      onChange={(_, v: Effect | null) => v && v !== currentEffect(r.code) && openChange(r.code, v)}
                    >
                      <ToggleButton value="inherit" sx={{ px: 1.25, fontSize: 12 }}>
                        Role default
                      </ToggleButton>
                      <ToggleButton value="allow" color="success" sx={{ px: 1.25, fontSize: 12 }}>
                        On
                      </ToggleButton>
                      <ToggleButton value="deny" color="error" sx={{ px: 1.25, fontSize: 12 }}>
                        Off
                      </ToggleButton>
                    </ToggleButtonGroup>
                  </TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      </Box>

      {pending && (
        <ReasonDialog
          open
          title={pending.effect === 'inherit' ? `Return ${pending.code} to role default` : `Turn ${pending.code} ${pending.effect === 'allow' ? 'ON' : 'OFF'} for ${user.username}`}
          description={
            pending.effect === 'deny'
              ? 'A personal "off" beats every role and delegation the user has.'
              : pending.effect === 'allow'
                ? 'The user gets this permission even if none of their roles includes it.'
                : 'Removes the personal override; the user gets whatever their roles give.'
          }
          confirmLabel="Apply"
          danger={pending.effect === 'deny'}
          onClose={() => setPending(null)}
          onConfirm={apply}
        >
          {pending.effect !== 'inherit' && (
            <Stack spacing={2} sx={{ mb: 1 }}>
              {pending.supportsScope && <ScopeFields scopeType={scopeType} scopeId={scopeId} onChange={(t, id) => { setScopeType(t); setScopeId(id) }} />}
              <TextField type="datetime-local" label="Ends (optional, Colombo time)" value={validTo} onChange={(e) => setValidTo(e.target.value)} slotProps={{ inputLabel: { shrink: true } }} helperText="Leave empty for no end date." />
            </Stack>
          )}
        </ReasonDialog>
      )}
    </Card>
  )
}
