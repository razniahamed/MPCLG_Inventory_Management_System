import { Card, CardContent, Grid, Skeleton, Stack, Switch, Typography } from '@mui/material'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { get, send } from '../../api/client'
import type { Module } from '../../api/types'
import { useAuth } from '../../auth/AuthContext'
import { useNotify } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { ReasonDialog } from '../../components/ReasonDialog'
import { BoolChip } from '../../components/StatusChip'

const DESCRIPTIONS: Record<string, string> = {
  access: 'Users, roles, permissions, delegations, audit and settings. Cannot be switched off.',
  organisation: 'Divisions, departments, sites, stores, locations and officers.',
  catalogue: 'Items, categories, units and suppliers.',
  receiving: 'Purchase order references, goods received notes and supplier returns.',
  requests: 'Department requests, approvals, issues and returns.',
  stock: 'Stock balances, ledger, counts and adjustments.',
  a4_paper: 'Machines, meter readings, shared usage and paper statements.',
  assets: 'Asset register, QR codes, transfers, loans, valuation and disposal.',
  maintenance: 'Warranty, repairs and servicing.',
  reports: 'Dashboards, reports, exports and printable forms.',
}

export function ModulesPage() {
  const { refresh } = useAuth()
  const notify = useNotify()
  const queryClient = useQueryClient()
  const [target, setTarget] = useState<Module | null>(null)
  const modules = useQuery({ queryKey: ['modules'], queryFn: () => get<Module[]>('/modules') })

  return (
    <>
      <PageHeader title="Modules" subtitle="Switch a whole feature area on or off — useful for phased go-live. A switched-off module blocks all of its endpoints." />
      <Grid container spacing={2}>
        {modules.isLoading && <Skeleton height={200} width="100%" />}
        {modules.data?.map((m) => (
          <Grid key={m.code} size={{ xs: 12, sm: 6, lg: 4 }}>
            <Card sx={{ height: '100%', opacity: m.is_enabled ? 1 : 0.7 }}>
              <CardContent>
                <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}>
                  <Typography sx={{ fontWeight: 600 }}>{m.name}</Typography>
                  <Switch checked={m.is_enabled} disabled={m.code === 'access'} onChange={() => setTarget(m)} />
                </Stack>
                <Typography color="text.secondary" sx={{ fontSize: 13, my: 1, minHeight: 40 }}>
                  {DESCRIPTIONS[m.code]}
                </Typography>
                <BoolChip value={m.is_enabled} yes="On" no="Off" />
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>
      {target && (
        <ReasonDialog
          open
          title={`${target.is_enabled ? 'Switch off' : 'Switch on'} ${target.name}`}
          description={target.is_enabled ? 'Every endpoint in this module will refuse requests until it is switched back on.' : 'The module becomes available to users with the right permissions.'}
          confirmLabel={target.is_enabled ? 'Switch off' : 'Switch on'}
          danger={target.is_enabled}
          onClose={() => setTarget(null)}
          onConfirm={async (reason) => {
            await send('patch', `/modules/${target.id}/enabled`, { is_enabled: !target.is_enabled, reason })
            await queryClient.invalidateQueries({ queryKey: ['modules'] })
            await queryClient.invalidateQueries({ queryKey: ['permission-catalogue'] })
            await refresh()
            notify.success(`${target.name} switched ${target.is_enabled ? 'off' : 'on'}.`)
          }}
        />
      )}
    </>
  )
}
