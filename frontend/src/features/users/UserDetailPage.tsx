import BlockIcon from '@mui/icons-material/Block'
import EditOutlinedIcon from '@mui/icons-material/EditOutlined'
import LockResetIcon from '@mui/icons-material/LockReset'
import LogoutIcon from '@mui/icons-material/Logout'
import MoreVertIcon from '@mui/icons-material/MoreVert'
import PauseCircleOutlineIcon from '@mui/icons-material/PauseCircleOutlined'
import PlayCircleOutlineIcon from '@mui/icons-material/PlayCircleOutlined'
import { Alert, Box, Button, Card, CardContent, Chip, Grid, ListItemIcon, Menu, MenuItem, Skeleton, Stack, Tab, Tabs, Typography } from '@mui/material'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { useParams } from 'react-router-dom'
import { get, send } from '../../api/client'
import type { Officer, User } from '../../api/types'
import { useAuth } from '../../auth/AuthContext'
import { FormDialog, type FieldDef } from '../../components/FormDialog'
import { errorMessage, useNotify } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { ReasonDialog } from '../../components/ReasonDialog'
import { StatusChip } from '../../components/StatusChip'
import { formatDateTime } from '../../utils/format'
import { TemporaryPasswordDialog } from './TemporaryPasswordDialog'
import { UserActivityTab } from './UserActivityTab'
import { UserPermissionsTab } from './UserPermissionsTab'
import { UserRolesTab } from './UserRolesTab'

type Action = 'suspend' | 'reactivate' | 'deactivate' | 'reset-password' | 'revoke-tokens'

const ACTIONS: Record<Action, { title: string; description: string; confirm: string; danger?: boolean; done: string }> = {
  suspend: { title: 'Suspend account', description: 'The user is signed out everywhere and cannot sign in until reactivated.', confirm: 'Suspend', danger: true, done: 'Account suspended.' },
  reactivate: { title: 'Reactivate account', description: 'The user can sign in again with their existing password.', confirm: 'Reactivate', done: 'Account reactivated.' },
  deactivate: {
    title: 'Deactivate account',
    description: 'Permanent: signs the user out, ends every role assignment, override and delegation. History is kept; the account is never deleted and cannot be reactivated.',
    confirm: 'Deactivate',
    danger: true,
    done: 'Account deactivated.',
  },
  'reset-password': { title: 'Reset password', description: 'Creates a new temporary password and signs the user out. They must change it at next sign-in.', confirm: 'Reset password', done: 'Password reset.' },
  'revoke-tokens': { title: 'Sign out everywhere', description: 'Ends every active session of this user.', confirm: 'Sign out everywhere', done: 'All sessions ended.' },
}

function Detail({ label, children }: { label: string; children: ReactNode }) {
  return (
    <Box>
      <Typography sx={{ fontSize: 12 }} color="text.secondary">
        {label}
      </Typography>
      <Box sx={{ fontSize: 14, mt: 0.25 }}>{children}</Box>
    </Box>
  )
}

export function UserDetailPage() {
  const { id } = useParams<{ id: string }>()
  const { can, me } = useAuth()
  const notify = useNotify()
  const queryClient = useQueryClient()
  const [tab, setTab] = useState(0)
  const [menu, setMenu] = useState<HTMLElement | null>(null)
  const [action, setAction] = useState<Action | null>(null)
  const [editing, setEditing] = useState(false)
  const [tempPassword, setTempPassword] = useState<string | null>(null)

  const query = useQuery({ queryKey: ['users', id], queryFn: () => get<User>(`/users/${id}`) })
  const user = query.data
  const isSelf = me?.user.id === id

  if (query.isLoading) return <Skeleton height={200} />
  if (query.isError || !user) return <Alert severity="error">{errorMessage(query.error)}</Alert>

  const available: Action[] = [
    ...(can('user.deactivate') && !isSelf && ['active', 'pending'].includes(user.status) ? (['suspend'] as Action[]) : []),
    ...(can('user.reactivate') && user.status === 'suspended' ? (['reactivate'] as Action[]) : []),
    ...(can('user.reset_password') && user.status !== 'deactivated' ? (['reset-password'] as Action[]) : []),
    ...(can('user.revoke_tokens') ? (['revoke-tokens'] as Action[]) : []),
    ...(can('user.deactivate') && !isSelf && user.status !== 'deactivated' ? (['deactivate'] as Action[]) : []),
  ]

  const icons: Record<Action, ReactNode> = {
    suspend: <PauseCircleOutlineIcon fontSize="small" />,
    reactivate: <PlayCircleOutlineIcon fontSize="small" />,
    deactivate: <BlockIcon fontSize="small" color="error" />,
    'reset-password': <LockResetIcon fontSize="small" />,
    'revoke-tokens': <LogoutIcon fontSize="small" />,
  }

  const run = async (reason: string) => {
    if (!action) return
    const res = await send<{ temporary_password?: string }>('post', `/users/${user.id}/${action}`, { reason })
    await queryClient.invalidateQueries({ queryKey: ['users'] })
    notify.success(ACTIONS[action].done)
    if (action === 'reset-password' && res.data?.temporary_password) setTempPassword(res.data.temporary_password)
  }

  const editFields: FieldDef[] = [
    { name: 'username', label: 'Username', required: true, width: 6 },
    { name: 'email', label: 'Email', type: 'email', required: true, width: 6 },
    ...(can('officer.view')
      ? [{ name: 'officer_id', label: 'Linked officer', lookup: { endpoint: '/officers', label: (r: Record<string, unknown>) => `${(r as unknown as Officer).officer_no} — ${(r as unknown as Officer).full_name}`, sort: 'full_name' } } satisfies FieldDef]
      : []),
    { name: 'reason', label: 'Reason', helper: 'Recorded in the audit trail.' },
  ]

  const tabs = [
    { label: 'Roles', show: can('permission.assign_role'), node: <UserRolesTab user={user} /> },
    { label: 'Permissions', show: can('permission.view'), node: <UserPermissionsTab user={user} /> },
    { label: 'Activity', show: can('audit.view'), node: <UserActivityTab user={user} /> },
  ].filter((t) => t.show)

  return (
    <>
      <PageHeader
        crumbs={[{ label: 'Users', to: '/users' }, { label: user.username }]}
        title={
          <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
            <span>{user.officer?.full_name ?? user.username}</span>
            <StatusChip value={user.status} />
            {user.is_locked && <Chip label="Locked" color="error" />}
          </Stack>
        }
        subtitle={`${user.username} · ${user.email}`}
        actions={
          <>
            {can('user.update') && user.status !== 'deactivated' && (
              <Button variant="outlined" startIcon={<EditOutlinedIcon />} onClick={() => setEditing(true)}>
                Edit
              </Button>
            )}
            {available.length > 0 && (
              <Button variant="outlined" endIcon={<MoreVertIcon />} onClick={(e) => setMenu(e.currentTarget)}>
                Actions
              </Button>
            )}
          </>
        }
      />

      <Menu anchorEl={menu} open={menu !== null} onClose={() => setMenu(null)}>
        {available.map((a) => (
          <MenuItem key={a} onClick={() => { setMenu(null); setAction(a) }} sx={ACTIONS[a].danger ? { color: 'error.main' } : undefined}>
            <ListItemIcon>{icons[a]}</ListItemIcon>
            {ACTIONS[a].title}
          </MenuItem>
        ))}
      </Menu>

      {user.status === 'deactivated' && (
        <Alert severity="info" sx={{ mb: 2 }}>
          Deactivated {formatDateTime(user.deactivated_at)} — {user.deactivation_reason}
        </Alert>
      )}

      <Card sx={{ mb: 2 }}>
        <CardContent>
          <Grid container spacing={2}>
            <Grid size={{ xs: 6, md: 3 }}>
              <Detail label="Officer">{user.officer ? `${user.officer.full_name} (${user.officer.officer_no})` : 'Not linked'}</Detail>
            </Grid>
            <Grid size={{ xs: 6, md: 3 }}>
              <Detail label="Designation / department">{user.officer ? `${user.officer.designation ?? '—'} · ${user.officer.department ?? '—'}` : '—'}</Detail>
            </Grid>
            <Grid size={{ xs: 6, md: 2 }}>
              <Detail label="Last sign-in">{formatDateTime(user.last_login_at)}</Detail>
            </Grid>
            <Grid size={{ xs: 6, md: 2 }}>
              <Detail label="From IP">{user.last_login_ip ?? '—'}</Detail>
            </Grid>
            <Grid size={{ xs: 6, md: 2 }}>
              <Detail label="Password">{user.must_change_password ? <Chip label="Must change" color="warning" /> : formatDateTime(user.password_changed_at)}</Detail>
            </Grid>
          </Grid>
        </CardContent>
      </Card>

      {tabs.length > 0 && (
        <>
          <Tabs value={Math.min(tab, tabs.length - 1)} onChange={(_, v) => setTab(v)} sx={{ mb: 2, borderBottom: 1, borderColor: 'divider' }}>
            {tabs.map((t) => (
              <Tab key={t.label} label={t.label} />
            ))}
          </Tabs>
          {tabs[Math.min(tab, tabs.length - 1)].node}
        </>
      )}

      {action && (
        <ReasonDialog
          open
          title={ACTIONS[action].title}
          description={ACTIONS[action].description}
          confirmLabel={ACTIONS[action].confirm}
          danger={ACTIONS[action].danger}
          onClose={() => setAction(null)}
          onConfirm={run}
        />
      )}

      <FormDialog
        open={editing}
        title="Edit user"
        fields={editFields}
        initial={user as unknown as Record<string, unknown>}
        onClose={() => setEditing(false)}
        onSubmit={async (values) => {
          await send('patch', `/users/${user.id}`, values)
          await queryClient.invalidateQueries({ queryKey: ['users'] })
          notify.success('User updated.')
        }}
      />

      <TemporaryPasswordDialog password={tempPassword} username={user.username} onClose={() => setTempPassword(null)} />
    </>
  )
}
