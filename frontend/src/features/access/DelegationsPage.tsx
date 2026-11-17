import AddIcon from '@mui/icons-material/Add'
import { Alert, Button, Dialog, DialogActions, DialogContent, DialogTitle, Grid, IconButton, MenuItem, Stack, TextField, Tooltip } from '@mui/material'
import BlockIcon from '@mui/icons-material/Block'
import type { GridColDef } from '@mui/x-data-grid'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { get, send } from '../../api/client'
import type { Delegation, Module, ScopeType, User } from '../../api/types'
import { useAuth } from '../../auth/AuthContext'
import { errorMessage, useNotify } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { ReasonDialog } from '../../components/ReasonDialog'
import { ScopeFields } from '../../components/ScopeFields'
import { ServerTable } from '../../components/ServerTable'
import { StatusChip } from '../../components/StatusChip'
import { useLookup } from '../../hooks/useLookup'
import { dayjs, formatDateTime, localInputToIso } from '../../utils/format'

function NewDelegationDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const notify = useNotify()
  const queryClient = useQueryClient()
  const users = useLookup<User>('/users', { filters: { status: 'active', is_active: undefined }, sort: 'username' })
  const catalogue = useQuery({ queryKey: ['permission-catalogue'], queryFn: () => get<Module[]>('/permissions') })
  const approvals = (catalogue.data ?? []).flatMap((m) => m.permissions ?? []).filter((p) => p.is_sensitive)

  const [form, setForm] = useState({
    delegator: '',
    delegate: '',
    permission: '',
    from: dayjs().format('YYYY-MM-DDTHH:mm'),
    to: dayjs().add(7, 'day').format('YYYY-MM-DDT17:00'),
    reference: '',
    reason: '',
  })
  const [scopeType, setScopeType] = useState<ScopeType>('global')
  const [scopeId, setScopeId] = useState<number | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const set = (k: keyof typeof form, v: string) => setForm((f) => ({ ...f, [k]: v }))

  const submit = async () => {
    setBusy(true)
    setError(null)
    try {
      await send('post', '/delegations', {
        delegator_user_id: form.delegator,
        delegate_user_id: form.delegate,
        permission_code: form.permission || null,
        scope: scopeType === 'global' ? null : { scope_type: scopeType, scope_id: scopeId },
        valid_from: localInputToIso(form.from),
        valid_to: localInputToIso(form.to),
        written_reference: form.reference,
        reason: form.reason || null,
      })
      await queryClient.invalidateQueries({ queryKey: ['delegations'] })
      notify.success('Delegation recorded.')
      onClose()
    } catch (e) {
      setError(e)
    } finally {
      setBusy(false)
    }
  }

  const userOptions = (users.data ?? []).map((u) => (
    <MenuItem key={u.id} value={u.id}>
      {u.officer?.full_name ? `${u.officer.full_name} (${u.username})` : u.username}
    </MenuItem>
  ))

  return (
    <Dialog open={open} onClose={() => !busy && onClose()} maxWidth="sm" fullWidth>
      <DialogTitle>Record a delegation</DialogTitle>
      <DialogContent dividers>
        <Stack spacing={2}>
          <Alert severity="info">A deputy acts for an approver for a stated period. Only approval permissions can be delegated, and never to System Administrators or Internal Auditors.</Alert>
          <TextField select label="Approver (delegating)" value={form.delegator} onChange={(e) => set('delegator', e.target.value)} required>
            {userOptions}
          </TextField>
          <TextField select label="Deputy (receiving)" value={form.delegate} onChange={(e) => set('delegate', e.target.value)} required>
            {userOptions}
          </TextField>
          <TextField select label="Permission" value={form.permission} onChange={(e) => set('permission', e.target.value)} helperText="Leave as 'All' to delegate every approval permission the approver holds.">
            <MenuItem value="">All of the approver's approval permissions</MenuItem>
            {approvals.map((p) => (
              <MenuItem key={p.code} value={p.code}>
                {p.code}
              </MenuItem>
            ))}
          </TextField>
          <ScopeFields scopeType={scopeType} scopeId={scopeId} onChange={(t, id) => { setScopeType(t); setScopeId(id) }} />
          <Grid container spacing={2}>
            <Grid size={6}>
              <TextField type="datetime-local" label="From" value={form.from} onChange={(e) => set('from', e.target.value)} slotProps={{ inputLabel: { shrink: true } }} />
            </Grid>
            <Grid size={6}>
              <TextField type="datetime-local" label="Until" value={form.to} onChange={(e) => set('to', e.target.value)} slotProps={{ inputLabel: { shrink: true } }} />
            </Grid>
          </Grid>
          <TextField label="Written reference (letter / circular)" value={form.reference} onChange={(e) => set('reference', e.target.value)} required helperText="Mandatory, e.g. MPCLG/ACC/2026/114" />
          <TextField label="Reason (optional)" value={form.reason} onChange={(e) => set('reason', e.target.value)} />
          {error !== null && <Alert severity="error">{errorMessage(error)}</Alert>}
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={busy}>
          Cancel
        </Button>
        <Button variant="contained" onClick={submit} disabled={busy || !form.delegator || !form.delegate || form.reference.length < 3}>
          {busy ? 'Saving…' : 'Record delegation'}
        </Button>
      </DialogActions>
    </Dialog>
  )
}

export function DelegationsPage() {
  const { can } = useAuth()
  const notify = useNotify()
  const queryClient = useQueryClient()
  const [status, setStatus] = useState('active')
  const [creating, setCreating] = useState(false)
  const [revoking, setRevoking] = useState<Delegation | null>(null)

  const columns: GridColDef<Delegation>[] = [
    { field: 'delegator', headerName: 'Approver', width: 150, valueGetter: (_, r) => r.delegator?.username },
    { field: 'delegate', headerName: 'Deputy', width: 150, valueGetter: (_, r) => r.delegate?.username },
    { field: 'permission', headerName: 'Covers', flex: 1, minWidth: 180, valueGetter: (_, r) => r.permission ?? 'All approval permissions' },
    { field: 'valid_from', headerName: 'From', width: 160, valueFormatter: (v: string) => formatDateTime(v) },
    { field: 'valid_to', headerName: 'Until', width: 160, valueFormatter: (v: string) => formatDateTime(v) },
    { field: 'written_reference', headerName: 'Reference', width: 170 },
    { field: 'status', headerName: 'Status', width: 110, renderCell: ({ row }) => <StatusChip value={row.status} /> },
    {
      field: 'actions',
      headerName: '',
      width: 60,
      sortable: false,
      renderCell: ({ row }) =>
        row.status === 'active' && (
          <Tooltip title="Revoke">
            <IconButton size="small" onClick={() => setRevoking(row)}>
              <BlockIcon fontSize="small" />
            </IconButton>
          </Tooltip>
        ),
    },
  ]

  return (
    <>
      <PageHeader
        title="Delegations"
        subtitle="Deputies acting for approvers, with period, scope and written reference."
        actions={can('delegation.manage') && <Button variant="contained" startIcon={<AddIcon />} onClick={() => setCreating(true)}>New delegation</Button>}
      />
      <ServerTable<Delegation>
        queryKey={['delegations']}
        endpoint="/delegations"
        columns={columns}
        sortFields={['valid_from', 'valid_to']}
        defaultSort={[{ field: 'valid_from', sort: 'desc' }]}
        filters={{ status }}
        searchPlaceholder="Search references"
        toolbar={
          <TextField select label="Status" value={status} onChange={(e) => setStatus(e.target.value)} sx={{ width: { xs: '100%', sm: 160 } }}>
            <MenuItem value="">All</MenuItem>
            <MenuItem value="active">Active</MenuItem>
            <MenuItem value="expired">Expired</MenuItem>
            <MenuItem value="revoked">Revoked</MenuItem>
          </TextField>
        }
      />
      <NewDelegationDialog open={creating} onClose={() => setCreating(false)} />
      {revoking && (
        <ReasonDialog
          open
          title="Revoke delegation"
          description={`${revoking.delegate?.username} will stop acting for ${revoking.delegator?.username} immediately.`}
          confirmLabel="Revoke"
          danger
          onClose={() => setRevoking(null)}
          onConfirm={async (reason) => {
            await send('delete', `/delegations/${revoking.id}`, { reason })
            await queryClient.invalidateQueries({ queryKey: ['delegations'] })
            notify.success('Delegation revoked.')
          }}
        />
      )}
    </>
  )
}
