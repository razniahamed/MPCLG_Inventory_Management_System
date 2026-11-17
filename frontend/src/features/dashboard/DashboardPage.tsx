import AddIcon from '@mui/icons-material/Add'
import AssignmentOutlinedIcon from '@mui/icons-material/AssignmentOutlined'
import BadgeOutlinedIcon from '@mui/icons-material/BadgeOutlined'
import ChecklistRtlOutlinedIcon from '@mui/icons-material/ChecklistRtlOutlined'
import ChevronRightIcon from '@mui/icons-material/ChevronRight'
import HistoryOutlinedIcon from '@mui/icons-material/HistoryOutlined'
import Inventory2OutlinedIcon from '@mui/icons-material/Inventory2Outlined'
import QrCode2OutlinedIcon from '@mui/icons-material/QrCode2Outlined'
import RocketLaunchOutlinedIcon from '@mui/icons-material/RocketLaunchOutlined'
import ScheduleOutlinedIcon from '@mui/icons-material/ScheduleOutlined'
import TaskAltIcon from '@mui/icons-material/TaskAlt'
import VerifiedUserOutlinedIcon from '@mui/icons-material/VerifiedUserOutlined'
import WarningAmberOutlinedIcon from '@mui/icons-material/WarningAmberOutlined'
import WorkspacePremiumOutlinedIcon from '@mui/icons-material/WorkspacePremiumOutlined'
import { Avatar, Box, Button, Chip, Grid, LinearProgress, Skeleton, Stack, Typography } from '@mui/material'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { get, getPage, listParams } from '../../api/client'
import type { AuditLog } from '../../api/types'
import { useAuth } from '../../auth/AuthContext'
import { StatusChip } from '../../components/StatusChip'
import { IconTile, SectionCard, StatCard } from '../../components/ui'
import { brand } from '../../theme'
import { formatDateTime, humanize } from '../../utils/format'

interface Dashboard {
  tasks: { key: string; label: string; count: number; link: string }[]
  figures: {
    low_stock_lines?: number
    stocked_items?: number
    items?: number
    assets?: number
    assets_by_status?: Record<string, number>
    warranties_expiring_60d?: number
    requests_this_month?: number
  }
}

const ROADMAP = [
  ['Foundation', true],
  ['Access & organisation', true],
  ['Item catalogue & suppliers', true],
  ['Receiving & stock ledger', true],
  ['Requests & issues', true],
  ['A4 paper control', true],
  ['Assets & QR', true],
  ['Maintenance & verification', true],
  ['Reports & documents', false],
  ['Migration & go-live', false],
] as const

const STATUS_COLOR: Record<string, string> = {
  in_store: '#2f6fed',
  in_use: '#16a34a',
  in_transit: '#0ea5e9',
  under_repair: '#f59e0b',
  on_loan: '#8b5cf6',
  lost: '#dc2626',
  disposed: '#94a3b8',
}

function Hero() {
  const { me, can } = useAuth()
  const navigate = useNavigate()
  const user = me!.user
  const name = user.officer?.full_name ?? user.username
  const initials = name.split(/\s+/).map((p) => p[0]).slice(0, 2).join('').toUpperCase()
  const hour = new Date().getHours()
  const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening'

  return (
    <Box
      sx={{
        position: 'relative',
        overflow: 'hidden',
        borderRadius: 6,
        p: { xs: 3, md: 4 },
        mb: 3,
        color: '#fff',
        background: brand.hero,
        boxShadow: '0 20px 50px rgba(6, 26, 77, 0.35)',
        '&::after': {
          content: '""',
          position: 'absolute',
          width: 420,
          height: 420,
          right: -120,
          top: -180,
          borderRadius: '50%',
          background: 'radial-gradient(circle, rgba(90,176,255,0.35) 0%, transparent 65%)',
        },
      }}
    >
      <Stack direction={{ xs: 'column', md: 'row' }} spacing={3} sx={{ alignItems: { md: 'center' }, position: 'relative', zIndex: 1 }}>
        <Box sx={{ position: 'relative', alignSelf: 'flex-start' }}>
          <Avatar sx={{ width: 96, height: 96, fontSize: 34, fontWeight: 800, borderRadius: 4, background: 'linear-gradient(135deg, #5ab0ff, #1446b8)', border: '3px solid rgba(160,200,255,0.6)' }}>
            {initials}
          </Avatar>
          <Box sx={{ position: 'absolute', right: -4, bottom: -4, width: 22, height: 22, borderRadius: '50%', bgcolor: '#22c55e', border: '4px solid #0b1a3d' }} />
        </Box>
        <Box sx={{ flex: 1, minWidth: 0 }}>
          <Typography variant="overline" sx={{ color: brand.sky, fontSize: 12 }}>
            {greeting.toUpperCase()} · WELCOME BACK
          </Typography>
          <Typography sx={{ fontSize: { xs: 26, md: 34 }, fontWeight: 800, lineHeight: 1.15 }}>{name}</Typography>
          <Typography sx={{ color: 'rgba(220,232,255,0.75)', mt: 0.5 }}>
            {user.officer ? `${user.officer.designation ?? ''}${user.officer.department ? ' · ' + user.officer.department : ''}` : 'Ministry inventory & asset management'}
          </Typography>
          <Stack direction="row" spacing={1} useFlexGap sx={{ mt: 2, flexWrap: 'wrap' }}>
            {user.officer && <Chip icon={<BadgeOutlinedIcon />} label={user.officer.officer_no} sx={{ bgcolor: 'rgba(255,255,255,0.1)', color: '#fff', '& .MuiChip-icon': { color: brand.sky } }} />}
            {user.roles?.slice(0, 3).map((r) => (
              <Chip key={r.id} icon={<WorkspacePremiumOutlinedIcon />} label={r.role?.name} sx={{ bgcolor: 'rgba(47,111,237,0.35)', color: '#fff', border: '1px solid rgba(90,176,255,0.5)', '& .MuiChip-icon': { color: brand.ice } }} />
            ))}
            <Chip icon={<ScheduleOutlinedIcon />} label={`Last sign-in: ${formatDateTime(user.last_login_at)}`} sx={{ bgcolor: 'rgba(255,255,255,0.1)', color: '#fff', '& .MuiChip-icon': { color: brand.sky } }} />
          </Stack>
        </Box>
        {can('request.create') && (
          <Button onClick={() => navigate('/requests/new')} startIcon={<AddIcon />} sx={{ alignSelf: { md: 'flex-start' }, bgcolor: '#fff', color: brand.deep, fontWeight: 800, px: 2.5, py: 1.2, borderRadius: 3, '&:hover': { bgcolor: '#e8f0ff' } }}>
            New request
          </Button>
        )}
      </Stack>
    </Box>
  )
}

export function DashboardPage() {
  const { can } = useAuth()
  const navigate = useNavigate()

  const dashboard = useQuery({ queryKey: ['dashboard'], queryFn: () => get<Dashboard>('/dashboard'), enabled: can('dashboard.view') })
  const recent = useQuery({
    queryKey: ['audit-logs', 'recent'],
    queryFn: () => getPage<AuditLog>('/audit-logs', listParams({ perPage: 6, sort: '-id' })),
    enabled: can('audit.view'),
  })

  const f = dashboard.data?.figures ?? {}
  const tasks = dashboard.data?.tasks ?? []
  const open = tasks.filter((t) => t.count > 0)
  const byStatus = f.assets_by_status ?? {}
  const totalAssets = Object.values(byStatus).reduce((a, b) => a + b, 0)
  const done = ROADMAP.filter((r) => r[1]).length

  const stats = [
    f.stocked_items !== undefined && <StatCard key="s" label="Items in stock" value={f.stocked_items} hint={`${f.items ?? 0} catalogue items`} icon={<Inventory2OutlinedIcon />} onClick={() => navigate('/stock')} />,
    f.low_stock_lines !== undefined && <StatCard key="l" label="At or below reorder level" value={f.low_stock_lines} hint="Stock lines to replenish" icon={<WarningAmberOutlinedIcon />} tone="warning" onClick={() => navigate('/stock?low_stock=true')} />,
    f.assets !== undefined && <StatCard key="a" label="Registered assets" value={f.assets} hint={`${byStatus.in_use ?? 0} in use · ${byStatus.under_repair ?? 0} under repair`} icon={<QrCode2OutlinedIcon />} tone="info" onClick={() => navigate('/assets')} />,
    f.warranties_expiring_60d !== undefined && <StatCard key="w" label="Warranties ending ≤ 60 days" value={f.warranties_expiring_60d} hint="Plan renewals early" icon={<VerifiedUserOutlinedIcon />} tone="secondary" onClick={() => navigate('/maintenance/warranties?expiring=true')} />,
    f.requests_this_month !== undefined && <StatCard key="r" label="Requests this month" value={f.requests_this_month} hint="All departments in your scope" icon={<AssignmentOutlinedIcon />} tone="success" onClick={() => navigate('/requests')} />,
  ].filter(Boolean)

  return (
    <>
      <Hero />

      {stats.length > 0 && (
        <Grid container spacing={2.5} sx={{ mb: 3 }}>
          {stats.map((s, i) => (
            <Grid key={i} size={{ xs: 12, sm: 6, lg: 12 / Math.min(stats.length, 4) }}>
              {s}
            </Grid>
          ))}
        </Grid>
      )}

      <Grid container spacing={2.5}>
        <Grid size={{ xs: 12, lg: 7 }}>
          <SectionCard title="Waiting for you" icon={<ChecklistRtlOutlinedIcon />} action={<Chip label={`${open.reduce((n, t) => n + t.count, 0)} open`} color={open.length ? 'primary' : 'default'} />}>
            {dashboard.isLoading ? (
              <Skeleton height={160} />
            ) : tasks.length === 0 ? (
              <Typography color="text.secondary">Nothing in your queues.</Typography>
            ) : (
              <Stack spacing={1}>
                {tasks.map((t) => (
                  <Stack
                    key={t.key}
                    direction="row"
                    onClick={() => navigate(t.link)}
                    sx={(th) => ({
                      alignItems: 'center',
                      gap: 1.5,
                      p: 1.5,
                      borderRadius: 3,
                      cursor: 'pointer',
                      border: `1px solid ${th.palette.divider}`,
                      opacity: t.count ? 1 : 0.6,
                      '&:hover': { borderColor: th.palette.primary.main, bgcolor: 'action.hover' },
                    })}
                  >
                    {t.count ? <IconTile icon={<ScheduleOutlinedIcon />} tone="warning" size={34} /> : <IconTile icon={<TaskAltIcon />} tone="success" size={34} />}
                    <Typography sx={{ flex: 1, fontWeight: 600, fontSize: 14 }}>{t.label}</Typography>
                    <Typography sx={{ fontWeight: 800, fontSize: 18, fontVariantNumeric: 'tabular-nums', color: t.count ? 'primary.main' : 'text.secondary' }}>{t.count}</Typography>
                    <ChevronRightIcon color="action" />
                  </Stack>
                ))}
              </Stack>
            )}
          </SectionCard>
        </Grid>

        <Grid size={{ xs: 12, lg: 5 }}>
          <Stack spacing={2.5}>
            {totalAssets > 0 && (
              <SectionCard title="Assets by status" icon={<QrCode2OutlinedIcon />} tone="info">
                <Stack spacing={1.25}>
                  {Object.entries(byStatus).map(([status, count]) => (
                    <Box key={status}>
                      <Stack direction="row" sx={{ justifyContent: 'space-between', mb: 0.5 }}>
                        <Typography sx={{ fontSize: 13, fontWeight: 600 }}>{humanize(status)}</Typography>
                        <Typography sx={{ fontSize: 13, fontWeight: 700 }}>{count}</Typography>
                      </Stack>
                      <LinearProgress variant="determinate" value={(count / totalAssets) * 100} sx={{ height: 8, bgcolor: 'action.hover', '& .MuiLinearProgress-bar': { bgcolor: STATUS_COLOR[status] ?? '#2f6fed' } }} />
                    </Box>
                  ))}
                </Stack>
              </SectionCard>
            )}
            <SectionCard title="Build progress" icon={<RocketLaunchOutlinedIcon />} tone="success" action={<Typography sx={{ fontSize: 13, color: 'text.secondary', fontWeight: 600 }}>{done} of {ROADMAP.length}</Typography>}>
              <LinearProgress variant="determinate" value={(done / ROADMAP.length) * 100} sx={{ height: 10, mb: 2 }} />
              <Grid container spacing={0.75}>
                {ROADMAP.map(([name, ok], i) => (
                  <Grid key={name} size={6}>
                    <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                      <Chip label={`P${i}`} color={ok ? 'success' : 'default'} variant={ok ? 'filled' : 'outlined'} sx={{ width: 38 }} />
                      <Typography noWrap sx={{ fontSize: 13, color: ok ? 'text.primary' : 'text.secondary' }}>
                        {name}
                      </Typography>
                    </Stack>
                  </Grid>
                ))}
              </Grid>
            </SectionCard>
          </Stack>
        </Grid>

        {can('audit.view') && (
          <Grid size={12}>
            <SectionCard title="Recent activity" icon={<HistoryOutlinedIcon />} tone="secondary" action={<Button size="small" onClick={() => navigate('/audit-logs')}>View audit trail</Button>}>
              {recent.isLoading ? (
                <Skeleton height={120} />
              ) : (
                <Stack divider={<Box sx={{ borderTop: 1, borderColor: 'divider' }} />}>
                  {(recent.data?.data ?? []).map((log) => (
                    <Stack key={log.id} direction={{ xs: 'column', sm: 'row' }} spacing={1.5} sx={{ py: 1.25, alignItems: { sm: 'center' } }}>
                      <Box sx={{ width: 160, flexShrink: 0 }}>
                        <StatusChip value={log.action} />
                      </Box>
                      <Typography sx={{ fontSize: 14, flex: 1 }}>
                        <b>{log.user?.username ?? 'system'}</b> · {humanize(log.auditable_type)} #{log.auditable_id}
                        {log.reason ? ` — ${log.reason}` : ''}
                      </Typography>
                      <Typography color="text.secondary" sx={{ fontSize: 13 }}>
                        {formatDateTime(log.event_at)}
                      </Typography>
                    </Stack>
                  ))}
                </Stack>
              )}
            </SectionCard>
          </Grid>
        )}
      </Grid>
    </>
  )
}
