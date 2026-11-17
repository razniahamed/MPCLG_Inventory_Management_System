import { Card, Chip, MenuItem, Skeleton, Stack, Switch, Table, TableBody, TableCell, TableHead, TableRow, TextField, Typography } from '@mui/material'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { get, send } from '../../api/client'
import type { Module, Permission } from '../../api/types'
import { useAuth } from '../../auth/AuthContext'
import { useNotify } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { ReasonDialog } from '../../components/ReasonDialog'

const PROTECTED = ['role.manage', 'permission.override_user', 'permission.toggle_global', 'module.toggle']

export function PermissionsPage() {
  const { can, refresh } = useAuth()
  const notify = useNotify()
  const queryClient = useQueryClient()
  const [module, setModule] = useState('')
  const [search, setSearch] = useState('')
  const [target, setTarget] = useState<Permission | null>(null)

  const catalogue = useQuery({ queryKey: ['permission-catalogue'], queryFn: () => get<Module[]>('/permissions') })
  const canToggle = can('permission.toggle_global')

  const rows = (catalogue.data ?? [])
    .filter((m) => !module || m.code === module)
    .flatMap((m) => (m.permissions ?? []).map((p) => ({ ...p, moduleName: m.name, moduleEnabled: m.is_enabled })))
    .filter((p) => !search || p.code.includes(search.toLowerCase()) || p.name.toLowerCase().includes(search.toLowerCase()))

  return (
    <>
      <PageHeader title="Permission catalogue" subtitle="Every action in the system has one permission code. Switching one off here blocks it for everyone, whatever their roles." />
      <Card>
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} sx={{ p: 2 }}>
          <TextField label="Search" value={search} onChange={(e) => setSearch(e.target.value)} sx={{ maxWidth: { md: 280 } }} />
          <TextField select label="Module" value={module} onChange={(e) => setModule(e.target.value)} sx={{ maxWidth: { md: 220 } }}>
            <MenuItem value="">All modules</MenuItem>
            {catalogue.data?.map((m) => (
              <MenuItem key={m.code} value={m.code}>
                {m.name}
              </MenuItem>
            ))}
          </TextField>
        </Stack>
        {catalogue.isLoading ? (
          <Skeleton height={300} sx={{ mx: 2 }} />
        ) : (
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>Code</TableCell>
                <TableCell>Name</TableCell>
                <TableCell>Module</TableCell>
                <TableCell>Flags</TableCell>
                <TableCell align="right">Enabled for everyone</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {rows.map((p) => (
                <TableRow key={p.code} hover>
                  <TableCell sx={{ fontFamily: 'monospace', fontSize: 13 }}>{p.code}</TableCell>
                  <TableCell>{p.name}</TableCell>
                  <TableCell>
                    {p.moduleName} {!p.moduleEnabled && <Chip label="module off" color="warning" variant="outlined" sx={{ ml: 0.5 }} />}
                  </TableCell>
                  <TableCell>
                    <Stack direction="row" spacing={0.5}>
                      {p.is_sensitive && <Chip label="approval" color="secondary" variant="outlined" />}
                      {p.supports_scope && <Chip label="scopable" variant="outlined" />}
                    </Stack>
                  </TableCell>
                  <TableCell align="right">
                    <Switch checked={p.is_enabled} disabled={!canToggle || (p.is_enabled && PROTECTED.includes(p.code))} onChange={() => setTarget(p)} />
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
        <Typography color="text.secondary" sx={{ fontSize: 12, p: 2 }}>
          Lock-out protection: role.manage, permission.override_user, permission.toggle_global and module.toggle cannot be switched off.
        </Typography>
      </Card>

      {target && (
        <ReasonDialog
          open
          title={`${target.is_enabled ? 'Switch off' : 'Switch on'} ${target.code} for everyone`}
          description={target.is_enabled ? 'No user will be able to use this permission until it is switched back on.' : 'Users whose roles or overrides include it can use it again.'}
          confirmLabel={target.is_enabled ? 'Switch off' : 'Switch on'}
          danger={target.is_enabled}
          onClose={() => setTarget(null)}
          onConfirm={async (reason) => {
            await send('patch', `/permissions/${target.id}/enabled`, { is_enabled: !target.is_enabled, reason })
            await queryClient.invalidateQueries({ queryKey: ['permission-catalogue'] })
            await refresh()
            notify.success(`${target.code} switched ${target.is_enabled ? 'off' : 'on'}.`)
          }}
        />
      )}
    </>
  )
}
