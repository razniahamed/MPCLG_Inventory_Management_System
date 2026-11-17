import { Box, Dialog, DialogContent, DialogTitle, Grid, MenuItem, TextField, Typography } from '@mui/material'
import type { GridColDef } from '@mui/x-data-grid'
import { useState } from 'react'
import type { AuditLog } from '../../api/types'
import { ServerTable } from '../../components/ServerTable'
import { StatusChip } from '../../components/StatusChip'
import { formatDateTime, humanize } from '../../utils/format'

const ACTIONS = ['created', 'updated', 'deleted', 'status_changed', 'approved', 'posted', 'reversed', 'permission_changed', 'exported', 'viewed_sensitive']

const columns: GridColDef<AuditLog>[] = [
  { field: 'event_at', headerName: 'When', width: 170, valueFormatter: (v: string) => formatDateTime(v) },
  { field: 'user', headerName: 'By', width: 140, valueGetter: (_, row) => row.user?.username ?? 'system' },
  { field: 'action', headerName: 'Action', width: 170, renderCell: ({ row }) => <StatusChip value={row.action} /> },
  { field: 'auditable_type', headerName: 'Record', width: 190, valueGetter: (_, row) => `${humanize(row.auditable_type)} #${row.auditable_id}` },
  { field: 'reason', headerName: 'Reason', flex: 1, minWidth: 200, valueGetter: (v) => v ?? '—' },
]

function JsonBlock({ title, value }: { title: string; value: unknown }) {
  return (
    <Box>
      <Typography sx={{ fontSize: 13, fontWeight: 600 }} gutterBottom>
        {title}
      </Typography>
      <Box component="pre" sx={{ m: 0, p: 1.5, bgcolor: 'action.hover', borderRadius: 1, fontSize: 12, overflowX: 'auto', minHeight: 60 }}>
        {value ? JSON.stringify(value, null, 2) : '—'}
      </Box>
    </Box>
  )
}

export function AuditTable({ filters: fixed = {}, showFilters = false }: { filters?: Record<string, string>; showFilters?: boolean }) {
  const [action, setAction] = useState('')
  const [type, setType] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [selected, setSelected] = useState<AuditLog | null>(null)

  return (
    <>
      <ServerTable<AuditLog>
        queryKey={['audit-logs', fixed]}
        endpoint="/audit-logs"
        columns={columns}
        sortFields={['event_at']}
        defaultSort={[{ field: 'event_at', sort: 'desc' }]}
        filters={{ ...fixed, action, auditable_type: type, from: from || undefined, to: to ? `${to} 23:59:59` : undefined }}
        searchPlaceholder="Search reasons"
        onRowClick={setSelected}
        toolbar={
          showFilters && (
            <>
              <TextField select label="Action" value={action} onChange={(e) => setAction(e.target.value)} sx={{ width: { xs: '100%', sm: 180 } }}>
                <MenuItem value="">All</MenuItem>
                {ACTIONS.map((a) => (
                  <MenuItem key={a} value={a}>
                    {humanize(a)}
                  </MenuItem>
                ))}
              </TextField>
              <TextField label="Record type" placeholder="e.g. users" value={type} onChange={(e) => setType(e.target.value.trim())} sx={{ width: { xs: '100%', sm: 160 } }} />
              <TextField type="date" label="From" value={from} onChange={(e) => setFrom(e.target.value)} sx={{ width: { xs: '100%', sm: 160 } }} slotProps={{ inputLabel: { shrink: true } }} />
              <TextField type="date" label="To" value={to} onChange={(e) => setTo(e.target.value)} sx={{ width: { xs: '100%', sm: 160 } }} slotProps={{ inputLabel: { shrink: true } }} />
            </>
          )
        }
      />
      <Dialog open={selected !== null} onClose={() => setSelected(null)} maxWidth="md" fullWidth>
        {selected && (
          <>
            <DialogTitle>
              {humanize(selected.action)} · {humanize(selected.auditable_type)} #{selected.auditable_id}
            </DialogTitle>
            <DialogContent dividers>
              <Grid container spacing={2} sx={{ mb: 2 }}>
                {[
                  ['When', formatDateTime(selected.event_at)],
                  ['By', selected.user?.username ?? 'system'],
                  ['IP address', selected.ip_address ?? '—'],
                  ['Request id', selected.request_id ?? '—'],
                  ['Reason', selected.reason ?? '—'],
                ].map(([k, v]) => (
                  <Grid key={k} size={{ xs: 12, sm: k === 'Reason' ? 12 : 6 }}>
                    <Typography sx={{ fontSize: 12 }} color="text.secondary">
                      {k}
                    </Typography>
                    <Typography sx={{ fontSize: 14, wordBreak: 'break-all' }}>
                      {v}
                    </Typography>
                  </Grid>
                ))}
              </Grid>
              <Grid container spacing={2}>
                <Grid size={{ xs: 12, md: 6 }}>
                  <JsonBlock title="Before" value={selected.old_values} />
                </Grid>
                <Grid size={{ xs: 12, md: 6 }}>
                  <JsonBlock title="After" value={selected.new_values} />
                </Grid>
              </Grid>
            </DialogContent>
          </>
        )}
      </Dialog>
    </>
  )
}
