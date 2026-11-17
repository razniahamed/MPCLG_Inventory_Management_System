import { Box, Card, CardContent, Chip, Grid, Stack, Typography } from '@mui/material'
import { useAuth } from '../../auth/AuthContext'
import { PageHeader } from '../../components/PageHeader'
import { StatusChip } from '../../components/StatusChip'
import { formatDateTime, humanize } from '../../utils/format'

function Row({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <Stack direction="row" sx={{ py: 1, borderBottom: 1, borderColor: 'divider' }}>
      <Typography color="text.secondary" sx={{ fontSize: 14, width: 170, flexShrink: 0 }}>
        {label}
      </Typography>
      <Box sx={{ fontSize: 14 }}>{value}</Box>
    </Stack>
  )
}

export function ProfilePage() {
  const { me } = useAuth()
  const user = me!.user

  const byArea = me!.permissions.reduce<Record<string, string[]>>((acc, code) => {
    const area = code.split('.')[0]
    ;(acc[area] ??= []).push(code)
    return acc
  }, {})

  return (
    <>
      <PageHeader title="My account" subtitle="Your profile and what you can do in the system." />
      <Grid container spacing={2}>
        <Grid size={{ xs: 12, md: 5 }}>
          <Card>
            <CardContent>
              <Row label="Username" value={user.username} />
              <Row label="Email" value={user.email} />
              <Row label="Status" value={<StatusChip value={user.status} />} />
              <Row label="Officer" value={user.officer ? `${user.officer.full_name} (${user.officer.officer_no})` : '—'} />
              <Row label="Designation" value={user.officer?.designation ?? '—'} />
              <Row label="Department" value={user.officer?.department ?? '—'} />
              <Row label="Last sign-in" value={formatDateTime(user.last_login_at)} />
              <Row label="Password changed" value={formatDateTime(user.password_changed_at)} />
              <Row
                label="Roles"
                value={
                  <Stack direction="row" spacing={0.5} useFlexGap sx={{ flexWrap: 'wrap' }}>
                    {user.roles?.map((r) => (
                      <Chip key={r.id} label={`${r.role?.name}${r.scope_type !== 'global' ? ` (${r.scope_type} #${r.scope_id})` : ''}`} />
                    ))}
                  </Stack>
                }
              />
            </CardContent>
          </Card>
        </Grid>
        <Grid size={{ xs: 12, md: 7 }}>
          <Card>
            <CardContent>
              <Typography sx={{ fontSize: 16 }} variant="h6" gutterBottom>
                Effective permissions ({me!.permissions.length})
              </Typography>
              <Typography color="text.secondary" sx={{ fontSize: 13, mb: 2 }}>
                Worked out from your roles, any personal overrides and active delegations.
              </Typography>
              <Stack spacing={1.5}>
                {Object.entries(byArea)
                  .sort(([a], [b]) => a.localeCompare(b))
                  .map(([area, codes]) => (
                    <Box key={area}>
                      <Typography sx={{ fontSize: 13, fontWeight: 600 }} gutterBottom>
                        {humanize(area)}
                      </Typography>
                      <Stack direction="row" spacing={0.5} useFlexGap sx={{ flexWrap: 'wrap' }}>
                        {codes.map((c) => {
                          const scopes = me!.permission_scopes[c] ?? []
                          const scoped = scopes.every((s) => s.scope_type !== 'global')
                          return <Chip key={c} label={c.split('.')[1].replace(/_/g, ' ')} variant="outlined" color={scoped ? 'warning' : 'default'} title={scoped ? 'Limited to: ' + scopes.map((s) => `${s.scope_type} #${s.scope_id}`).join(', ') : 'Everywhere'} />
                        })}
                      </Stack>
                    </Box>
                  ))}
              </Stack>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </>
  )
}
