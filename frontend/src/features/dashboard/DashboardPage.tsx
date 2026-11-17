import ArrowForwardIcon from '@mui/icons-material/ArrowForward'
import { Box, Card, CardActionArea, CardContent, Chip, Grid, LinearProgress, Skeleton, Stack, Typography } from '@mui/material'
import { useQuery } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { useNavigate } from 'react-router-dom'
import { getPage, listParams } from '../../api/client'
import type { AuditLog } from '../../api/types'
import { useAuth } from '../../auth/AuthContext'
import { PageHeader } from '../../components/PageHeader'
import { StatusChip } from '../../components/StatusChip'
import { formatDateTime, humanize } from '../../utils/format'

function useCount(endpoint: string, enabled: boolean, filters: Record<string, string | boolean> = {}) {
  return useQuery({
    queryKey: ['count', endpoint, filters],
    queryFn: async () => (await getPage(endpoint, listParams({ perPage: 1, filters }))).meta.total,
    enabled,
  })
}

function StatTile({ label, value, loading, hint, to }: { label: string; value?: number; loading: boolean; hint?: string; to: string }) {
  const navigate = useNavigate()
  return (
    <Card sx={{ height: '100%' }}>
      <CardActionArea onClick={() => navigate(to)} sx={{ height: '100%' }}>
        <CardContent>
          <Typography sx={{ fontSize: 13, fontWeight: 500 }} color="text.secondary">
            {label}
          </Typography>
          <Typography sx={{ fontSize: 32, fontWeight: 700, my: 0.5, fontVariantNumeric: 'tabular-nums' }}>
            {loading ? <Skeleton width={60} /> : (value ?? 0).toLocaleString()}
          </Typography>
          {hint && (
            <Typography sx={{ fontSize: 12 }} color="text.secondary">
              {hint}
            </Typography>
          )}
        </CardContent>
      </CardActionArea>
    </Card>
  )
}

const ROADMAP: { phase: number; name: string; done?: boolean }[] = [
  { phase: 0, name: 'Foundation', done: true },
  { phase: 1, name: 'Access & organisation', done: true },
  { phase: 2, name: 'Item catalogue & suppliers' },
  { phase: 3, name: 'Receiving & stock ledger' },
  { phase: 4, name: 'Requests & issues' },
  { phase: 5, name: 'A4 paper control' },
  { phase: 6, name: 'Assets & QR' },
  { phase: 7, name: 'Maintenance & verification' },
  { phase: 8, name: 'Reports & documents' },
  { phase: 9, name: 'Migration & go-live' },
]

function Section({ title, children, action }: { title: string; children: ReactNode; action?: ReactNode }) {
  return (
    <Card sx={{ height: '100%' }}>
      <CardContent>
        <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
          <Typography sx={{ fontSize: 16 }} variant="h6">
            {title}
          </Typography>
          {action}
        </Stack>
        {children}
      </CardContent>
    </Card>
  )
}

export function DashboardPage() {
  const { me, can } = useAuth()
  const navigate = useNavigate()
  const user = me!.user

  const users = useCount('/users', can('user.view'), { status: 'active' })
  const pending = useCount('/users', can('user.view'), { status: 'pending' })
  const departments = useCount('/departments', can('department.view'))
  const officers = useCount('/officers', can('officer.view'))
  const roles = useCount('/roles', can('role.view'))

  const recent = useQuery({
    queryKey: ['audit-logs', 'recent'],
    queryFn: () => getPage<AuditLog>('/audit-logs', listParams({ perPage: 6, sort: '-id' })),
    enabled: can('audit.view'),
  })

  const done = ROADMAP.filter((r) => r.done).length
  const hour = new Date().getHours()
  const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening'

  const tiles = [
    can('user.view') && <StatTile key="u" label="Active users" value={users.data} loading={users.isLoading} hint={`${pending.data ?? 0} awaiting first sign-in`} to="/users" />,
    can('officer.view') && <StatTile key="o" label="Officers" value={officers.data} loading={officers.isLoading} hint="People on the staff register" to="/organisation/officers" />,
    can('department.view') && <StatTile key="d" label="Departments" value={departments.data} loading={departments.isLoading} hint="Working units" to="/organisation/departments" />,
    can('role.view') && <StatTile key="r" label="Roles" value={roles.data} loading={roles.isLoading} hint="Permission bundles" to="/roles" />,
  ].filter(Boolean)

  return (
    <>
      <PageHeader
        title={`${greeting}, ${user.officer?.full_name?.split(' ')[0] ?? user.username}`}
        subtitle={user.officer ? `${user.officer.designation ?? ''}${user.officer.department ? ' · ' + user.officer.department : ''}` : 'Ministry inventory & asset management'}
      />

      {tiles.length > 0 && (
        <Grid container spacing={2} sx={{ mb: 2 }}>
          {tiles.map((t, i) => (
            <Grid key={i} size={{ xs: 12, sm: 6, lg: 3 }}>
              {t}
            </Grid>
          ))}
        </Grid>
      )}

      <Grid container spacing={2}>
        <Grid size={{ xs: 12, md: 6 }}>
          <Section title="My access">
            <Stack spacing={1.5}>
              <Box>
                <Typography sx={{ fontSize: 13 }} color="text.secondary" gutterBottom>
                  Roles
                </Typography>
                <Stack direction="row" spacing={1} useFlexGap sx={{ flexWrap: 'wrap' }}>
                  {user.roles?.length ? user.roles.map((r) => <Chip key={r.id} label={`${r.role?.name}${r.scope_type !== 'global' ? ` · ${r.scope_type} #${r.scope_id}` : ''}`} color="primary" variant="outlined" />) : <Typography sx={{ fontSize: 14 }}>No roles yet</Typography>}
                </Stack>
              </Box>
              <Box>
                <Typography sx={{ fontSize: 13 }} color="text.secondary" gutterBottom>
                  Permissions
                </Typography>
                <Typography sx={{ fontSize: 14 }}>
                  You can use <b>{me!.permissions.length}</b> permissions across{' '}
                  <b>{new Set(me!.permissions.map((p) => p.split('.')[0])).size}</b> areas.
                </Typography>
              </Box>
              <Typography
                component="button"
                onClick={() => navigate('/profile')}
                sx={{ all: 'unset', cursor: 'pointer', color: 'primary.main', fontSize: 14, display: 'inline-flex', alignItems: 'center', gap: 0.5 }}
              >
                See all my permissions <ArrowForwardIcon fontSize="inherit" />
              </Typography>
            </Stack>
          </Section>
        </Grid>

        <Grid size={{ xs: 12, md: 6 }}>
          <Section title="Build progress" action={<Typography sx={{ fontSize: 13 }} color="text.secondary">{done} of {ROADMAP.length} phases</Typography>}>
            <LinearProgress variant="determinate" value={(done / ROADMAP.length) * 100} sx={{ height: 8, borderRadius: 4, mb: 2 }} />
            <Stack spacing={0.75}>
              {ROADMAP.map((r) => (
                <Stack key={r.phase} direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
                  <Chip label={`P${r.phase}`} color={r.done ? 'success' : 'default'} variant={r.done ? 'filled' : 'outlined'} sx={{ width: 40 }} />
                  <Typography sx={{ fontSize: 14 }} color={r.done ? 'text.primary' : 'text.secondary'}>
                    {r.name}
                  </Typography>
                </Stack>
              ))}
            </Stack>
          </Section>
        </Grid>

        {can('audit.view') && (
          <Grid size={12}>
            <Section title="Recent activity" action={<Typography component="button" onClick={() => navigate('/audit-logs')} sx={{ all: 'unset', cursor: 'pointer', color: 'primary.main', fontSize: 14 }}>View audit trail</Typography>}>
              {recent.isLoading ? (
                <Skeleton height={120} />
              ) : (
                <Stack divider={<Box sx={{ borderTop: 1, borderColor: 'divider' }} />}>
                  {(recent.data?.data ?? []).map((log) => (
                    <Stack key={log.id} direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ py: 1, alignItems: { sm: 'center' } }}>
                      <Box sx={{ width: 150, flexShrink: 0 }}>
                        <StatusChip value={log.action} />
                      </Box>
                      <Typography sx={{ fontSize: 14, flex: 1 }}>
                        <b>{log.user?.username ?? 'system'}</b> · {humanize(log.auditable_type)} #{log.auditable_id}
                        {log.reason ? ` — ${log.reason}` : ''}
                      </Typography>
                      <Typography sx={{ fontSize: 13 }} color="text.secondary">
                        {formatDateTime(log.event_at)}
                      </Typography>
                    </Stack>
                  ))}
                  {recent.data?.data.length === 0 && <Typography color="text.secondary">No activity yet.</Typography>}
                </Stack>
              )}
            </Section>
          </Grid>
        )}
      </Grid>
    </>
  )
}
