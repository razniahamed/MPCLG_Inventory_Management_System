import AddIcon from '@mui/icons-material/Add'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlined'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import {
  Alert,
  Box,
  Button,
  Card,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  Grid,
  IconButton,
  MenuItem,
  Paper,
  Stack,
  Switch,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { get, send } from '../../api/client'
import type { Role, ScopeType, User, UserRoleAssignment } from '../../api/types'
import { useAuth } from '../../auth/AuthContext'
import { errorMessage, useNotify } from '../../components/Notify'
import { ScopeFields, useScopeLabel } from '../../components/ScopeFields'
import { StatusChip } from '../../components/StatusChip'
import { useLookup } from '../../hooks/useLookup'
import { dayjs, DISPLAY_TZ, formatDateTime, localInputToIso } from '../../utils/format'

interface Row {
  role: string
  scope_type: ScopeType
  scope_id: number | null
  valid_to: string // datetime-local value
}

function RolesEditor({ user, current, onClose }: { user: User; current: UserRoleAssignment[]; onClose: () => void }) {
  const notify = useNotify()
  const queryClient = useQueryClient()
  const roles = useLookup<Role>('/roles', { sort: 'name' })
  const [rows, setRows] = useState<Row[]>(() =>
    current.map((a) => ({
      role: a.role?.code ?? '',
      scope_type: a.scope_type,
      scope_id: a.scope_id,
      valid_to: a.valid_to ? dayjs(a.valid_to).tz(DISPLAY_TZ).format('YYYY-MM-DDTHH:mm') : '',
    })),
  )
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<unknown>(null)

  const update = (i: number, patch: Partial<Row>) => setRows((r) => r.map((row, j) => (j === i ? { ...row, ...patch } : row)))

  const save = async () => {
    setBusy(true)
    setError(null)
    try {
      await send('put', `/users/${user.id}/roles`, {
        mode: 'replace',
        roles: rows.map((r) => ({
          role: r.role,
          scope_type: r.scope_type,
          scope_id: r.scope_type === 'global' ? null : r.scope_id,
          ...(r.valid_to ? { valid_to: localInputToIso(r.valid_to) } : {}),
        })),
        reason,
      })
      await queryClient.invalidateQueries({ queryKey: ['users', user.id] })
      await queryClient.invalidateQueries({ queryKey: ['user-roles', user.id] })
      await queryClient.invalidateQueries({ queryKey: ['user-effective', user.id] })
      notify.success('Roles updated. The change applies immediately.')
      onClose()
    } catch (e) {
      setError(e)
    } finally {
      setBusy(false)
    }
  }

  const valid = rows.every((r) => r.role && (r.scope_type === 'global' || r.scope_id)) && reason.trim().length >= 3

  return (
    <Dialog open onClose={() => !busy && onClose()} maxWidth="md" fullWidth>
      <DialogTitle>Edit role assignments</DialogTitle>
      <DialogContent dividers>
        <Typography color="text.secondary" sx={{ fontSize: 14, mb: 2 }}>
          The list below replaces the user's current roles. Removed roles are ended (kept in history). Limit a role to one division, department or store where needed, and give temporary roles an end date.
        </Typography>
        <Stack spacing={2}>
          {rows.map((row, i) => (
            <Paper key={i} variant="outlined" sx={{ p: 2 }}>
              <Grid container spacing={2} sx={{ alignItems: 'flex-start' }}>
                <Grid size={{ xs: 12, md: 4 }}>
                  <TextField select label="Role" value={row.role} onChange={(e) => update(i, { role: e.target.value })} required>
                    {(roles.data ?? []).map((r) => (
                      <MenuItem key={r.code} value={r.code}>
                        {r.name}
                      </MenuItem>
                    ))}
                  </TextField>
                </Grid>
                <Grid size={{ xs: 12, md: 8 }}>
                  <Stack direction="row" spacing={1} sx={{ alignItems: 'flex-start' }}>
                    <Box sx={{ flex: 1 }}>
                      <ScopeFields scopeType={row.scope_type} scopeId={row.scope_id} onChange={(t, id) => update(i, { scope_type: t, scope_id: id })} />
                    </Box>
                    <IconButton onClick={() => setRows((r) => r.filter((_, j) => j !== i))} aria-label="Remove role">
                      <DeleteOutlineIcon />
                    </IconButton>
                  </Stack>
                </Grid>
                <Grid size={{ xs: 12, md: 4 }}>
                  <FormControlLabel
                    control={<Switch checked={row.valid_to !== ''} onChange={(e) => update(i, { valid_to: e.target.checked ? dayjs().add(30, 'day').format('YYYY-MM-DDT17:00') : '' })} />}
                    label="Temporary"
                  />
                </Grid>
                {row.valid_to !== '' && (
                  <Grid size={{ xs: 12, md: 5 }}>
                    <TextField type="datetime-local" label="Ends (Colombo time)" value={row.valid_to} onChange={(e) => update(i, { valid_to: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} />
                  </Grid>
                )}
              </Grid>
            </Paper>
          ))}
          <Button startIcon={<AddIcon />} onClick={() => setRows((r) => [...r, { role: '', scope_type: 'global', scope_id: null, valid_to: '' }])} sx={{ alignSelf: 'flex-start' }}>
            Add role
          </Button>
          <TextField label="Reason (recorded in the audit trail)" value={reason} onChange={(e) => setReason(e.target.value)} required multiline minRows={2} />
          {error !== null && <Alert severity="error">{errorMessage(error)}</Alert>}
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={busy}>
          Cancel
        </Button>
        <Button variant="contained" onClick={save} disabled={busy || !valid}>
          {busy ? 'Saving…' : 'Save roles'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}

export function UserRolesTab({ user }: { user: User }) {
  const { can } = useAuth()
  const scopeLabel = useScopeLabel()
  const [history, setHistory] = useState(false)
  const [editing, setEditing] = useState(false)

  const query = useQuery({
    queryKey: ['user-roles', user.id, history],
    queryFn: () => get<UserRoleAssignment[]>(`/users/${user.id}/roles`, history ? { history: 1 } : {}),
  })

  const current = (query.data ?? []).filter((a) => a.revoked_at === null && (a.valid_to === null || dayjs(a.valid_to).isAfter(dayjs())))

  return (
    <Card>
      <Stack direction="row" sx={{ p: 2, alignItems: 'center', justifyContent: 'space-between' }}>
        <FormControlLabel control={<Switch checked={history} onChange={(e) => setHistory(e.target.checked)} />} label="Show ended assignments" />
        {can('permission.assign_role') && user.status !== 'deactivated' && (
          <Button variant="contained" startIcon={<EditOutlinedIcon />} onClick={() => setEditing(true)} disabled={query.isLoading}>
            Edit roles
          </Button>
        )}
      </Stack>
      <Table size="small">
        <TableHead>
          <TableRow>
            <TableCell>Role</TableCell>
            <TableCell>Applies to</TableCell>
            <TableCell>From</TableCell>
            <TableCell>Until</TableCell>
            <TableCell>Status</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {(query.data ?? []).map((a) => (
            <TableRow key={a.id}>
              <TableCell>
                <Typography sx={{ fontSize: 14, fontWeight: 600 }}>
                  {a.role?.name}
                </Typography>
                <Typography sx={{ fontSize: 12 }} color="text.secondary">
                  {a.role?.code}
                </Typography>
              </TableCell>
              <TableCell>{scopeLabel(a.scope_type, a.scope_id)}</TableCell>
              <TableCell>{formatDateTime(a.valid_from)}</TableCell>
              <TableCell>{a.valid_to ? formatDateTime(a.valid_to) : 'No end date'}</TableCell>
              <TableCell>
                {a.is_current ? <StatusChip value="active" /> : a.revoked_at ? <StatusChip value="revoked" label={`Ended${a.revoke_reason ? ': ' + a.revoke_reason : ''}`} /> : <StatusChip value="pending" label="Not current" />}
              </TableCell>
            </TableRow>
          ))}
          {query.data?.length === 0 && (
            <TableRow>
              <TableCell colSpan={5}>
                <Typography color="text.secondary" sx={{ fontSize: 14, py: 2, textAlign: 'center' }}>
                  No roles assigned.
                </Typography>
              </TableCell>
            </TableRow>
          )}
        </TableBody>
      </Table>
      {editing && <RolesEditor user={user} current={current} onClose={() => setEditing(false)} />}
    </Card>
  )
}
