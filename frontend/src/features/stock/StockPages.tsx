import ChecklistOutlinedIcon from '@mui/icons-material/ChecklistOutlined'
import { Alert, Box, Button, Chip, Grid, LinearProgress, Skeleton, Stack, Table, TableBody, TableCell, TableHead, TableRow, TextField, Typography } from '@mui/material'
import type { GridColDef } from '@mui/x-data-grid'
import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'
import { ActionDialog, type ActionSpec } from '../../components/ActionDialog'
import { ListPage } from '../../components/ListPage'
import { errorMessage } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { ItemPicker, LookupField, labels, type ItemOption } from '../../components/Pickers'
import { StatusChip } from '../../components/StatusChip'
import { CodeText, Qty, SectionCard } from '../../components/ui'
import { useDoc, useRun } from '../../hooks/useApi'
import { formatDate, formatDateTime, humanize } from '../../utils/format'

type Rec = Record<string, any> // eslint-disable-line @typescript-eslint/no-explicit-any

// ---- Balances ------------------------------------------------------------------

export function StockBalancesPage() {
  const columns: GridColDef<Rec>[] = [
    { field: 'item', headerName: 'Item', flex: 1, minWidth: 260, sortable: false, renderCell: ({ row }) => <Box><CodeText>{row.item?.item_code}</CodeText> <span>{row.item?.name_en}</span></Box> },
    { field: 'store', headerName: 'Store', width: 170, sortable: false, valueGetter: (_, r) => r.store?.name },
    { field: 'batch_no', headerName: 'Batch', width: 110, valueGetter: (v) => v || '—' },
    { field: 'on_hand_usable', headerName: 'On hand', width: 140, renderCell: ({ row }) => <Qty value={row.on_hand_usable} unit={row.item?.base_unit?.code} /> },
    { field: 'reserved', headerName: 'Reserved', width: 120, renderCell: ({ value }) => <Qty value={value} /> },
    {
      field: 'available', headerName: 'Available', width: 200, sortable: false,
      renderCell: ({ row }) => {
        const available = Number(row.on_hand_usable) - Number(row.reserved)
        const reorder = row.item?.reorder_level === null ? null : Number(row.item?.reorder_level)
        const low = reorder !== null && Number(row.on_hand_usable) <= reorder
        return (
          <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
            <Qty value={available} />
            {low && <Chip label="reorder" color="warning" />}
          </Stack>
        )
      },
    },
    { field: 'on_hand_damaged', headerName: 'Damaged', width: 110, renderCell: ({ value }) => <Qty value={value} /> },
  ]

  return (
    <ListPage<Rec>
      title="Stock balances"
      subtitle="Calculated from the append-only stock ledger. Available = on hand − reserved for approved requests. Quantities are in each item's base unit."
      endpoint="/stock/balances"
      queryKey={['/stock/balances']}
      columns={columns}
      sortFields={['on_hand_usable', 'reserved']}
      defaultSort={[{ field: 'on_hand_usable', sort: 'desc' }]}
      filters={[
        { name: 'store_id', label: 'Store', lookup: { endpoint: '/stores', label: labels.name } },
        { name: 'low_stock', label: 'Level', options: [{ value: 'true', label: 'At / below reorder level' }], width: 220 },
        { name: 'in_stock', label: 'Show', options: [{ value: 'true', label: 'Only items in stock' }] },
      ]}
      searchAs="item"
      searchPlaceholder="Search item code or name"
    />
  )
}

// ---- Ledger ----------------------------------------------------------------------

export function StockLedgerPage() {
  const columns: GridColDef<Rec>[] = [
    { field: 'event_on', headerName: 'Date', width: 115, valueFormatter: (v) => formatDate(v) },
    { field: 'document_no', headerName: 'Document', width: 160, renderCell: ({ value }) => <CodeText>{value ?? '—'}</CodeText> },
    { field: 'movement_type', headerName: 'Movement', width: 150, renderCell: ({ value }) => <Chip label={humanize(value)} variant="outlined" color={['receipt', 'opening', 'return_in', 'adjustment_plus', 'transfer_in'].includes(value) ? 'success' : value === 'reversal' ? 'warning' : 'default'} /> },
    { field: 'item', headerName: 'Item', flex: 1, minWidth: 220, sortable: false, renderCell: ({ row }) => <Box><CodeText>{row.item?.item_code}</CodeText> {row.item?.name_en}</Box> },
    { field: 'store', headerName: 'Store', width: 150, sortable: false, valueGetter: (_, r) => r.store?.name },
    {
      field: 'qty_signed', headerName: 'Quantity', width: 120,
      renderCell: ({ value }) => <Typography sx={{ fontWeight: 700, color: Number(value) > 0 ? 'success.main' : 'error.main', fontVariantNumeric: 'tabular-nums' }}>{Number(value) > 0 ? '+' : ''}{Number(value).toLocaleString()}</Typography>,
    },
    { field: 'officer', headerName: 'Officer', width: 170, sortable: false, valueGetter: (_, r) => `${r.officer?.full_name ?? ''}${r.designation_snapshot ? ` (${r.designation_snapshot})` : ''}` },
    { field: 'created_at', headerName: 'Entered', width: 160, valueFormatter: (v) => formatDateTime(v) },
    { field: 'remarks', headerName: 'Remarks', width: 220 },
  ]

  return (
    <ListPage<Rec>
      title="Stock ledger"
      subtitle="Every movement, never edited or deleted. Corrections appear as linked reversal entries. The entry date can differ from the event date for late entries."
      endpoint="/stock/ledger"
      queryKey={['/stock/ledger']}
      columns={columns}
      sortFields={['event_on']}
      defaultSort={[{ field: 'event_on', sort: 'desc' }]}
      filters={[
        { name: 'store_id', label: 'Store', lookup: { endpoint: '/stores', label: labels.name } },
        { name: 'movement_type', label: 'Movement', options: ['opening', 'receipt', 'issue', 'return_in', 'supplier_return', 'adjustment_plus', 'adjustment_minus', 'reversal'] },
      ]}
      searchPlaceholder="Document no. or remarks"
    />
  )
}

// ---- Adjustments ---------------------------------------------------------------

export function AdjustmentsPage() {
  const { can } = useAuth()
  const run = useRun()
  const [dialog, setDialog] = useState<null | { kind: 'new' } | { kind: 'approve' | 'reject'; row: Rec }>(null)
  const [form, setForm] = useState({ store_id: null as number | null, item: null as ItemOption | null, qty: '', reason: '' })

  const columns: GridColDef<Rec>[] = [
    { field: 'adjustment_no', headerName: 'No.', width: 150, renderCell: ({ value }) => <CodeText>{value}</CodeText> },
    { field: 'item', headerName: 'Item', flex: 1, minWidth: 220, sortable: false, valueGetter: (_, r) => `${r.item?.item_code} — ${r.item?.name_en}` },
    { field: 'store', headerName: 'Store', width: 150, sortable: false, valueGetter: (_, r) => r.store?.name },
    { field: 'qty_delta', headerName: 'Change', width: 100, renderCell: ({ value }) => <Typography sx={{ fontWeight: 700, color: Number(value) > 0 ? 'success.main' : 'error.main' }}>{Number(value) > 0 ? '+' : ''}{Number(value)}</Typography> },
    { field: 'reason', headerName: 'Reason', flex: 1, minWidth: 220 },
    { field: 'recorded_by', headerName: 'Recorded by', width: 160, sortable: false, valueGetter: (_, r) => r.recorded_by?.full_name },
    { field: 'status', headerName: 'Status', width: 120, renderCell: ({ value }) => <StatusChip value={value} /> },
    {
      field: 'actions', headerName: '', width: 190, sortable: false,
      renderCell: ({ row }) => row.status === 'submitted' && can('adjustment.approve') && (
        <Stack direction="row" spacing={1}>
          <Button size="small" variant="contained" color="success" onClick={() => setDialog({ kind: 'approve', row })}>Approve</Button>
          <Button size="small" color="error" onClick={() => setDialog({ kind: 'reject', row })}>Reject</Button>
        </Stack>
      ),
    },
  ]

  const spec = (): ActionSpec | null => {
    if (!dialog) return null
    if (dialog.kind === 'new') {
      return {
        title: 'New stock adjustment',
        description: 'Needs approval by a different officer before it changes the ledger.',
        remarks: 'required',
        remarksLabel: 'Reason (required)',
        disabled: !form.store_id || !form.item || !form.qty || Number(form.qty) === 0,
        body: (
          <Grid container spacing={2}>
            <Grid size={12}><LookupField endpoint="/stores" label="Store" value={form.store_id} onChange={(v) => setForm({ ...form, store_id: v })} optionLabel={labels.coded} required /></Grid>
            <Grid size={12}><ItemPicker value={form.item} onChange={(item) => setForm({ ...form, item })} /></Grid>
            <Grid size={12}><TextField label="Change in base units (+ or −)" type="number" value={form.qty} onChange={(e) => setForm({ ...form, qty: e.target.value })} /></Grid>
          </Grid>
        ),
        confirmLabel: 'Submit for approval',
        run: (r) => run('post', '/stock-adjustments', { store_id: form.store_id, item_id: form.item!.id, qty_delta: form.qty, reason: r }, [['/stock-adjustments']]),
      }
    }
    const { row } = dialog
    return dialog.kind === 'approve'
      ? { title: `Approve ${row.adjustment_no}`, description: `Posts ${row.qty_delta} to the ledger. You cannot approve an adjustment you recorded.`, remarks: 'optional', confirmLabel: 'Approve & post', run: (r) => run('post', `/stock-adjustments/${row.public_id}/approve`, { remarks: r || null }, [['/stock-adjustments'], ['/stock/balances']], { idempotent: true }) }
      : { title: `Reject ${row.adjustment_no}`, remarks: 'required', danger: true, confirmLabel: 'Reject', run: (r) => run('post', `/stock-adjustments/${row.public_id}/reject`, { reason: r }, [['/stock-adjustments']]) }
  }

  return (
    <>
      <ListPage<Rec>
        title="Stock adjustments"
        subtitle="Corrections to stock. The approver must be a different officer from the one who recorded the count or adjustment."
        endpoint="/stock-adjustments"
        queryKey={['/stock-adjustments']}
        columns={columns}
        sortFields={['adjustment_no']}
        defaultSort={[{ field: 'adjustment_no', sort: 'desc' }]}
        filters={[{ name: 'status', label: 'Status', options: ['submitted', 'posted', 'rejected'] }]}
        createLabel="New adjustment"
        onCreate={can('adjustment.create') ? () => { setForm({ store_id: null, item: null, qty: '', reason: '' }); setDialog({ kind: 'new' }) } : undefined}
      />
      <ActionDialog action={spec()} onClose={() => setDialog(null)} />
    </>
  )
}

// ---- Discrepancies ---------------------------------------------------------------

export function DiscrepanciesPage() {
  const { can } = useAuth()
  const run = useRun()
  const [resolving, setResolving] = useState<Rec | null>(null)

  const columns: GridColDef<Rec>[] = [
    { field: 'created_at', headerName: 'Opened', width: 160, valueFormatter: (v) => formatDateTime(v) },
    { field: 'discrepancy_type', headerName: 'Type', width: 170, valueGetter: (v) => humanize(String(v)) },
    { field: 'description', headerName: 'Description', flex: 1, minWidth: 260 },
    { field: 'expected_qty', headerName: 'Expected', width: 100, renderCell: ({ value }) => <Qty value={value} /> },
    { field: 'actual_qty', headerName: 'Actual', width: 100, renderCell: ({ value }) => <Qty value={value} /> },
    { field: 'status', headerName: 'Status', width: 120, renderCell: ({ value }) => <StatusChip value={value === 'resolved' ? 'active' : 'pending'} label={humanize(value)} /> },
    { field: 'resolution', headerName: 'Resolution', width: 220 },
    { field: 'actions', headerName: '', width: 110, sortable: false, renderCell: ({ row }) => row.status !== 'resolved' && can('adjustment.approve') && <Button size="small" variant="outlined" onClick={() => setResolving(row)}>Resolve</Button> },
  ]

  return (
    <>
      <ListPage<Rec>
        title="Discrepancies"
        subtitle="Receipt shortfalls, refused transfers and count variances stay open until someone with authority resolves them. Stock is never silently adjusted."
        endpoint="/discrepancies"
        queryKey={['/discrepancies']}
        columns={columns}
        sortFields={['created_at']}
        defaultSort={[{ field: 'created_at', sort: 'desc' }]}
        filters={[{ name: 'status', label: 'Status', options: ['open', 'under_review', 'resolved'] }]}
      />
      <ActionDialog
        action={resolving && { title: 'Resolve discrepancy', description: resolving.description, remarks: 'required', remarksLabel: 'Resolution (what was found / done)', confirmLabel: 'Resolve', run: (r) => run('post', `/discrepancies/${resolving.id}/resolve`, { resolution: r }, [['/discrepancies']]) }}
        onClose={() => setResolving(null)}
      />
    </>
  )
}

// ---- Counts ----------------------------------------------------------------------

export function StockCountsPage() {
  const { can } = useAuth()
  const run = useRun()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)
  const [store, setStore] = useState<number | null>(null)

  const columns: GridColDef<Rec>[] = [
    { field: 'session_no', headerName: 'Count', width: 150, renderCell: ({ value }) => <CodeText>{value}</CodeText> },
    { field: 'cut_off_at', headerName: 'Cut-off', width: 170, valueFormatter: (v) => formatDateTime(v) },
    { field: 'store', headerName: 'Store', width: 170, sortable: false, valueGetter: (_, r) => r.store?.name },
    { field: 'scope_note', headerName: 'Scope', flex: 1, minWidth: 200 },
    { field: 'lines_count', headerName: 'Lines', width: 80, type: 'number' },
    { field: 'counted_by', headerName: 'Counted by', width: 160, sortable: false, valueGetter: (_, r) => r.counted_by?.full_name },
    { field: 'status', headerName: 'Status', width: 120, renderCell: ({ value }) => <StatusChip value={value} /> },
  ]

  return (
    <>
      <ListPage<Rec>
        title="Stock counts"
        subtitle="Count sheets are generated from the ledger at the cut-off time. A variance needs a recount and an explanation, then independent approval."
        endpoint="/stock-counts"
        queryKey={['/stock-counts']}
        columns={columns}
        sortFields={['session_no', 'cut_off_at']}
        defaultSort={[{ field: 'session_no', sort: 'desc' }]}
        filters={[{ name: 'status', label: 'Status', options: ['counting', 'recount', 'submitted', 'approved'] }]}
        rowLink={(r) => `/stock/counts/${r.public_id}`}
        createLabel="New count"
        onCreate={can('count.create') ? () => setOpen(true) : undefined}
      />
      <ActionDialog
        action={open ? {
          title: 'Start a stock count',
          description: 'Creates a count sheet with the expected quantity of every item in the store.',
          body: <LookupField endpoint="/stores" label="Store" value={store} onChange={setStore} optionLabel={labels.coded} required />,
          remarks: 'optional', remarksLabel: 'Scope note (optional)',
          disabled: !store,
          confirmLabel: 'Create count sheet',
          run: async (r) => { const s = await run<Rec>('post', '/stock-counts', { store_id: store, scope_note: r || null }, [['/stock-counts']]); navigate(`/stock/counts/${s.public_id}`) },
        } : null}
        onClose={() => setOpen(false)}
      />
    </>
  )
}

export function StockCountDetailPage() {
  const { id } = useParams()
  const { can } = useAuth()
  const run = useRun()
  const count = useDoc<Rec>(['/stock-counts', id], `/stock-counts/${id}`)
  const [values, setValues] = useState<Record<number, { counted: string; recount: string; remarks: string }>>({})
  const [dialog, setDialog] = useState<string | null>(null)

  useEffect(() => {
    if (count.data) {
      setValues(Object.fromEntries(count.data.lines.map((l: Rec) => [l.id, { counted: l.counted_qty === null ? '' : String(Number(l.counted_qty)), recount: l.recount_qty === null ? '' : String(Number(l.recount_qty)), remarks: l.remarks ?? '' }])))
    }
  }, [count.data])

  if (count.isLoading) return <Skeleton height={300} />
  if (count.isError || !count.data) return <Alert severity="error">{errorMessage(count.error)}</Alert>
  const c = count.data
  const editable = ['counting', 'recount'].includes(c.status) && can('count.record')
  const inv = [['/stock-counts'], ['/stock-counts', id]]
  const counted = c.lines.filter((l: Rec) => l.counted_qty !== null).length

  const specs: Record<string, ActionSpec> = {
    save: {
      title: 'Save counts',
      confirmLabel: 'Save',
      run: () => run('post', `/stock-counts/${c.public_id}/record`, { lines: c.lines.map((l: Rec) => ({ id: l.id, counted_qty: values[l.id]?.counted === '' ? null : values[l.id]?.counted, recount_qty: values[l.id]?.recount === '' ? null : values[l.id]?.recount, remarks: values[l.id]?.remarks || null })) }, inv),
    },
    submit: { title: 'Submit count', description: 'Every line must be counted; variances need a recount and an explanation.', confirmLabel: 'Submit', run: () => run('post', `/stock-counts/${c.public_id}/submit`, {}, inv) },
    approve: { title: 'Approve count', description: 'Variances become stock adjustments that still need their own approval. You cannot approve a count you recorded.', remarks: 'optional', confirmLabel: 'Approve', run: (r) => run('post', `/stock-counts/${c.public_id}/approve`, { remarks: r || null }, [...inv, ['/stock-adjustments']]) },
  }

  return (
    <>
      <PageHeader
        crumbs={[{ label: 'Stock counts', to: '/stock/counts' }, { label: c.session_no }]}
        title={<Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}><span>{c.session_no}</span><StatusChip value={c.status} /></Stack>}
        subtitle={`${c.store?.name} · cut-off ${formatDateTime(c.cut_off_at)} · counted by ${c.counted_by?.full_name}`}
        actions={
          <>
            {editable && <Button variant="outlined" onClick={() => setDialog('save')}>Save counts</Button>}
            {editable && <Button variant="contained" onClick={() => setDialog('submit')}>Submit</Button>}
            {c.status === 'submitted' && can('count.approve') && <Button variant="contained" color="success" onClick={() => setDialog('approve')}>Approve</Button>}
          </>
        }
      />
      <SectionCard title="Count sheet" icon={<ChecklistOutlinedIcon />} action={<Typography sx={{ fontSize: 13, color: 'text.secondary', fontWeight: 600 }}>{counted} / {c.lines.length} counted</Typography>} noPadding>
        <LinearProgress variant="determinate" value={c.lines.length ? (counted / c.lines.length) * 100 : 0} sx={{ mx: 3, mb: 2, height: 6 }} />
        <Box sx={{ overflowX: 'auto' }}>
          <Table>
            <TableHead>
              <TableRow><TableCell>Item</TableCell><TableCell align="right">Expected</TableCell><TableCell>Counted</TableCell><TableCell>Recount</TableCell><TableCell align="right">Variance</TableCell><TableCell>Explanation</TableCell></TableRow>
            </TableHead>
            <TableBody>
              {c.lines.map((l: Rec) => {
                const v = values[l.id] ?? { counted: '', recount: '', remarks: '' }
                const final = v.recount !== '' ? Number(v.recount) : v.counted !== '' ? Number(v.counted) : null
                const variance = final === null ? null : final - Number(l.expected_qty)
                return (
                  <TableRow key={l.id} sx={variance ? { bgcolor: 'warning.light', '& td': { bgcolor: 'rgba(245,158,11,0.08)' } } : undefined}>
                    <TableCell><CodeText>{l.item?.item_code}</CodeText> {l.item?.name_en}</TableCell>
                    <TableCell align="right"><Qty value={l.expected_qty} /></TableCell>
                    <TableCell sx={{ width: 130 }}>{editable ? <TextField type="number" value={v.counted} onChange={(e) => setValues({ ...values, [l.id]: { ...v, counted: e.target.value } })} /> : <Qty value={l.counted_qty} />}</TableCell>
                    <TableCell sx={{ width: 130 }}>{editable && variance ? <TextField type="number" value={v.recount} onChange={(e) => setValues({ ...values, [l.id]: { ...v, recount: e.target.value } })} /> : <Qty value={l.recount_qty} />}</TableCell>
                    <TableCell align="right">{variance === null ? '—' : <Typography sx={{ fontWeight: 700, color: variance === 0 ? 'success.main' : 'error.main' }}>{variance > 0 ? '+' : ''}{variance}</Typography>}</TableCell>
                    <TableCell sx={{ minWidth: 220 }}>{editable && variance ? <TextField value={v.remarks} placeholder="Why does it differ?" onChange={(e) => setValues({ ...values, [l.id]: { ...v, remarks: e.target.value } })} /> : l.remarks ?? ''}</TableCell>
                  </TableRow>
                )
              })}
            </TableBody>
          </Table>
        </Box>
      </SectionCard>
      <ActionDialog action={dialog ? specs[dialog] : null} onClose={() => setDialog(null)} />
    </>
  )
}

