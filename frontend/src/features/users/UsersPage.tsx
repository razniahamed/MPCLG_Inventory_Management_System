import AddIcon from '@mui/icons-material/Add'
import { Box, Button, Chip, MenuItem, TextField, Typography } from '@mui/material'
import type { GridColDef } from '@mui/x-data-grid'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { send } from '../../api/client'
import type { Officer, Role, User } from '../../api/types'
import { useAuth } from '../../auth/AuthContext'
import { FormDialog, type FieldDef } from '../../components/FormDialog'
import { useNotify } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { ServerTable } from '../../components/ServerTable'
import { StatusChip } from '../../components/StatusChip'
import { useLookup } from '../../hooks/useLookup'
import { fromNow } from '../../utils/format'
import { TemporaryPasswordDialog } from './TemporaryPasswordDialog'

const columns: GridColDef<User>[] = [
  {
    field: 'username',
    headerName: 'User',
    flex: 1.2,
    minWidth: 200,
    renderCell: ({ row }) => (
      <Box sx={{ lineHeight: 1.3, py: 1 }}>
        <Typography sx={{ fontSize: 14, fontWeight: 600 }}>
          {row.officer?.full_name ?? row.username}
        </Typography>
        <Typography sx={{ fontSize: 12 }} color="text.secondary">
          {row.username} · {row.email}
        </Typography>
      </Box>
    ),
  },
  { field: 'department', headerName: 'Department', flex: 1, minWidth: 160, valueGetter: (_, row) => row.officer?.department ?? '—' },
  {
    field: 'status',
    headerName: 'Status',
    width: 140,
    renderCell: ({ row }) => (
      <Box sx={{ display: 'flex', gap: 0.5, alignItems: 'center', height: '100%' }}>
        <StatusChip value={row.status} />
        {row.is_locked && <Chip label="Locked" color="error" variant="outlined" />}
      </Box>
    ),
  },
  { field: 'last_login_at', headerName: 'Last sign-in', width: 150, valueFormatter: (v: string | null) => fromNow(v) },
  { field: 'created_at', headerName: 'Created', width: 140, valueFormatter: (v: string | null) => fromNow(v) },
]

export function UsersPage() {
  const { can } = useAuth()
  const navigate = useNavigate()
  const notify = useNotify()
  const queryClient = useQueryClient()
  const [status, setStatus] = useState('')
  const [role, setRole] = useState('')
  const [creating, setCreating] = useState(false)
  const [created, setCreated] = useState<{ password: string; username: string } | null>(null)

  const roles = useLookup<Role>(can('role.view') ? '/roles' : null, { sort: 'name' })
  const officersAllowed = can('officer.view')

  const fields: FieldDef[] = [
    { name: 'username', label: 'Username', required: true, width: 6, helper: 'Letters, numbers, dot, dash, underscore.' },
    { name: 'email', label: 'Email', type: 'email', required: true, width: 6 },
    ...(officersAllowed
      ? [{ name: 'officer_id', label: 'Linked officer', lookup: { endpoint: '/officers', label: (r: Record<string, unknown>) => `${(r as unknown as Officer).officer_no} — ${(r as unknown as Officer).full_name}`, sort: 'full_name' }, helper: 'Leave empty for service / IT accounts.' } satisfies FieldDef]
      : []),
    {
      name: 'role',
      label: 'Initial role',
      type: 'select',
      options: (roles.data ?? []).map((r) => ({ value: r.code, label: r.name })),
      helper: 'More roles, scopes and personal overrides can be set on the user page.',
    },
    { name: 'reason', label: 'Reason / reference', helper: 'Recorded in the audit trail.' },
  ]

  const create = async (values: Record<string, unknown>) => {
    const { role: roleCode, ...rest } = values
    const res = await send<User>('post', '/users', { ...rest, roles: roleCode ? [{ role: roleCode }] : [] })
    await queryClient.invalidateQueries({ queryKey: ['users'] })
    notify.success(`User ${res.data.username} created.`)
    if (res.data.temporary_password) setCreated({ password: res.data.temporary_password, username: res.data.username })
  }

  return (
    <>
      <PageHeader
        title="Users"
        subtitle="Sign-in accounts. Accounts are never deleted — suspend or deactivate them instead."
        actions={can('user.create') && <Button variant="contained" startIcon={<AddIcon />} onClick={() => setCreating(true)}>New user</Button>}
      />
      <ServerTable<User>
        queryKey={['users']}
        endpoint="/users"
        columns={columns}
        sortFields={['username', 'status', 'last_login_at', 'created_at']}
        defaultSort={[{ field: 'username', sort: 'asc' }]}
        filters={{ status, role }}
        searchPlaceholder="Search username or email"
        onRowClick={(row) => navigate(`/users/${row.id}`)}
        toolbar={
          <>
            <TextField select label="Status" value={status} onChange={(e) => setStatus(e.target.value)} sx={{ width: { xs: '100%', sm: 160 } }}>
              <MenuItem value="">All</MenuItem>
              {['active', 'pending', 'suspended', 'deactivated'].map((s) => (
                <MenuItem key={s} value={s}>
                  {s.charAt(0).toUpperCase() + s.slice(1)}
                </MenuItem>
              ))}
            </TextField>
            {roles.data && (
              <TextField select label="Role" value={role} onChange={(e) => setRole(e.target.value)} sx={{ width: { xs: '100%', sm: 220 } }}>
                <MenuItem value="">All roles</MenuItem>
                {roles.data.map((r) => (
                  <MenuItem key={r.code} value={r.code}>
                    {r.name}
                  </MenuItem>
                ))}
              </TextField>
            )}
          </>
        }
      />
      <FormDialog open={creating} title="New user" fields={fields} submitLabel="Create user" onClose={() => setCreating(false)} onSubmit={create} />
      <TemporaryPasswordDialog password={created?.password ?? null} username={created?.username} onClose={() => setCreated(null)} />
    </>
  )
}
