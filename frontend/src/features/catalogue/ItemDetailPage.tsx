import AddIcon from '@mui/icons-material/Add'
import Inventory2OutlinedIcon from '@mui/icons-material/Inventory2Outlined'
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined'
import SyncAltIcon from '@mui/icons-material/SyncAlt'
import { Alert, Button, Chip, Grid, Skeleton, Stack, Table, TableBody, TableCell, TableHead, TableRow, TextField, Typography } from '@mui/material'
import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'
import { ActionDialog, type ActionSpec } from '../../components/ActionDialog'
import { errorMessage } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { LookupField, labels } from '../../components/Pickers'
import { CodeText, InfoGrid, Qty, SectionCard } from '../../components/ui'
import { useDoc, useRun } from '../../hooks/useApi'
import { formatDateTime, humanize } from '../../utils/format'

type Rec = Record<string, any> // eslint-disable-line @typescript-eslint/no-explicit-any

export function ItemDetailPage() {
  const { id } = useParams()
  const { can } = useAuth()
  const run = useRun()
  const item = useDoc<Rec>(['/items', id], `/items/${id}`)
  const [dialog, setDialog] = useState<null | { kind: 'add' } | { kind: 'approve'; id: number }>(null)
  const [conv, setConv] = useState<{ from: number | null; to: number | null; factor: string }>({ from: null, to: null, factor: '' })

  if (item.isLoading) return <Skeleton height={300} />
  if (item.isError || !item.data) return <Alert severity="error">{errorMessage(item.error)}</Alert>
  const i = item.data

  const specs = (): ActionSpec | null => {
    if (!dialog) return null
    if (dialog.kind === 'approve') {
      return { title: 'Approve conversion', description: 'Confirm the factor against the supplier packaging. You cannot approve a conversion you entered.', remarks: 'optional', confirmLabel: 'Approve', run: (r) => run('post', `/items/${i.id}/conversions/${dialog.id}/approve`, { reason: r || null }, [['/items', id]]) }
    }
    return {
      title: 'Add unit conversion',
      description: '1 "from" unit = factor × "to" unit. A second officer must approve it before it is used.',
      confirmLabel: 'Save',
      body: (
        <Grid container spacing={2}>
          <Grid size={6}>
            <LookupField endpoint="/units" label="From unit" value={conv.from} onChange={(v) => setConv((c) => ({ ...c, from: v }))} optionLabel={labels.coded} />
          </Grid>
          <Grid size={6}>
            <LookupField endpoint="/units" label="To unit" value={conv.to} onChange={(v) => setConv((c) => ({ ...c, to: v }))} optionLabel={labels.coded} />
          </Grid>
          <Grid size={12}>
            <TextField label="Factor" type="number" value={conv.factor} onChange={(e) => setConv((c) => ({ ...c, factor: e.target.value }))} helperText="e.g. 1 Ream = 500 Sheets → factor 500" />
          </Grid>
        </Grid>
      ),
      disabled: !conv.from || !conv.to || !conv.factor,
      run: () => run('post', `/items/${i.id}/conversions`, { from_unit_id: conv.from, to_unit_id: conv.to, factor: conv.factor }, [['/items', id]]),
    }
  }

  return (
    <>
      <PageHeader
        crumbs={[{ label: 'Items', to: '/catalogue/items' }, { label: i.item_code }]}
        title={
          <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
            <span>{i.name_en}</span>
            <Chip label={i.tracking_method === 'asset' ? 'Asset-tracked' : 'Quantity-tracked'} color={i.tracking_method === 'asset' ? 'info' : 'default'} />
            {i.is_paper && <Chip label="A4 paper" color="secondary" />}
          </Stack>
        }
        subtitle={<CodeText>{i.item_code}</CodeText>}
      />
      <Grid container spacing={2.5}>
        <Grid size={{ xs: 12, lg: 7 }}>
          <SectionCard title="Details" icon={<InfoOutlinedIcon />}>
            <InfoGrid
              items={[
                { label: 'Category', value: i.category?.name },
                { label: 'Brand', value: i.brand },
                { label: 'Old register code', value: i.legacy_code },
                { label: 'Base unit (ledger)', value: i.base_unit?.name },
                { label: 'Purchase unit', value: i.purchase_unit?.name },
                { label: 'Issue unit', value: i.issue_unit?.name },
                { label: 'Reorder level', value: <Qty value={i.reorder_level} unit={i.base_unit?.code} /> },
                { label: 'Reorder quantity', value: <Qty value={i.reorder_qty} unit={i.base_unit?.code} /> },
                { label: 'Active', value: i.is_active ? 'Yes' : 'No' },
                { label: 'Specification', value: i.specification, wide: true },
              ]}
            />
          </SectionCard>
        </Grid>
        <Grid size={{ xs: 12, lg: 5 }}>
          <Stack spacing={2.5}>
            <SectionCard title="Stock by store" icon={<Inventory2OutlinedIcon />} tone="success">
              {(i.stock_by_store ?? []).length === 0 ? (
                <Typography color="text.secondary" sx={{ fontSize: 14 }}>No stock recorded.</Typography>
              ) : (
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Store</TableCell>
                      <TableCell align="right">On hand</TableCell>
                      <TableCell align="right">Reserved</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {i.stock_by_store.map((s: Rec) => (
                      <TableRow key={s.store?.id}>
                        <TableCell>{s.store?.name}</TableCell>
                        <TableCell align="right"><Qty value={s.on_hand_usable} unit={i.base_unit?.code} /></TableCell>
                        <TableCell align="right"><Qty value={s.reserved} /></TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </SectionCard>
            <SectionCard
              title="Unit conversions"
              icon={<SyncAltIcon />}
              tone="info"
              action={can('item.manage') && <Button size="small" startIcon={<AddIcon />} onClick={() => { setConv({ from: null, to: null, factor: '' }); setDialog({ kind: 'add' }) }}>Add</Button>}
            >
              {(i.conversions ?? []).length === 0 ? (
                <Typography color="text.secondary" sx={{ fontSize: 14 }}>None. The item can only be recorded in its base unit.</Typography>
              ) : (
                <Stack spacing={1}>
                  {i.conversions.map((c: Rec) => (
                    <Stack key={c.id} direction="row" sx={{ alignItems: 'center', gap: 1, p: 1.25, borderRadius: 3, border: 1, borderColor: 'divider' }}>
                      <Typography sx={{ flex: 1, fontSize: 14 }}>
                        1 {c.from_unit?.name} = <b>{Number(c.factor).toLocaleString()}</b> {c.to_unit?.name}
                      </Typography>
                      {c.approved_at ? (
                        <Chip label={`Approved ${formatDateTime(c.approved_at)}`} color="success" variant="outlined" />
                      ) : can('item.manage') ? (
                        <Button size="small" variant="outlined" onClick={() => setDialog({ kind: 'approve', id: c.id })}>
                          Approve
                        </Button>
                      ) : (
                        <Chip label={humanize('pending approval')} color="warning" />
                      )}
                    </Stack>
                  ))}
                </Stack>
              )}
            </SectionCard>
          </Stack>
        </Grid>
      </Grid>
      <ActionDialog action={specs()} onClose={() => setDialog(null)} />
    </>
  )
}
