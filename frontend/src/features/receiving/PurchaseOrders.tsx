import ListAltOutlinedIcon from '@mui/icons-material/ListAltOutlined'
import { Alert, Button, Dialog, DialogActions, DialogContent, DialogTitle, Grid, Skeleton, Stack, Table, TableBody, TableCell, TableHead, TableRow, TextField } from '@mui/material'
import type { GridColDef } from '@mui/x-data-grid'
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'
import { ActionDialog, type ActionSpec } from '../../components/ActionDialog'
import { LineItemsEditor, linesValid, newLine, type LineDraft } from '../../components/LineItemsEditor'
import { ListPage } from '../../components/ListPage'
import { errorMessage } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { LookupField, labels } from '../../components/Pickers'
import { StatusChip } from '../../components/StatusChip'
import { CodeText, InfoGrid, Money, Qty, SectionCard } from '../../components/ui'
import { useDoc, useRun } from '../../hooks/useApi'
import { formatDate } from '../../utils/format'

type Rec = Record<string, any> // eslint-disable-line @typescript-eslint/no-explicit-any

const columns: GridColDef<Rec>[] = [
  { field: 'po_number', headerName: 'PO number', width: 170, renderCell: ({ value }) => <CodeText>{value}</CodeText> },
  { field: 'po_date', headerName: 'Date', width: 120, valueFormatter: (v) => formatDate(v) },
  { field: 'supplier', headerName: 'Supplier', flex: 1, minWidth: 200, sortable: false, valueGetter: (_, r) => r.supplier?.name },
  { field: 'lines_count', headerName: 'Lines', width: 80, type: 'number' },
  { field: 'approval_doc_ref', headerName: 'Approval ref.', width: 160 },
  { field: 'status', headerName: 'Status', width: 150, renderCell: ({ value }) => <StatusChip value={value} /> },
]

function NewPurchaseOrder({ open, onClose }: { open: boolean; onClose: () => void }) {
  const run = useRun()
  const navigate = useNavigate()
  const [form, setForm] = useState({ po_number: '', po_date: new Date().toISOString().slice(0, 10), supplier_id: null as number | null, approval_doc_ref: '', expected_delivery_on: '' })
  const [lines, setLines] = useState<LineDraft[]>([newLine()])
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<unknown>(null)

  const save = async () => {
    setBusy(true)
    setError(null)
    try {
      const po = await run<Rec>('post', '/purchase-orders', {
        ...form,
        expected_delivery_on: form.expected_delivery_on || null,
        lines: lines.map((l) => ({ item_id: l.item!.id, unit_id: l.unit_id, ordered_qty: l.qty, unit_price: l.unit_price || null })),
      }, [['/purchase-orders']])
      onClose()
      navigate(`/purchase-orders/${po.public_id}`)
    } catch (e) {
      setError(e)
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open={open} onClose={() => !busy && onClose()} maxWidth="lg" fullWidth>
      <DialogTitle sx={{ fontWeight: 700 }}>Record an approved purchase order</DialogTitle>
      <DialogContent dividers>
        <Grid container spacing={2} sx={{ mb: 2 }}>
          <Grid size={{ xs: 12, md: 4 }}><TextField label="PO number" value={form.po_number} onChange={(e) => setForm({ ...form, po_number: e.target.value })} required /></Grid>
          <Grid size={{ xs: 6, md: 2 }}><TextField label="PO date" type="date" value={form.po_date} onChange={(e) => setForm({ ...form, po_date: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} /></Grid>
          <Grid size={{ xs: 6, md: 2 }}><TextField label="Expected delivery" type="date" value={form.expected_delivery_on} onChange={(e) => setForm({ ...form, expected_delivery_on: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} /></Grid>
          <Grid size={{ xs: 12, md: 4 }}><LookupField endpoint="/suppliers" label="Supplier" value={form.supplier_id} onChange={(v) => setForm({ ...form, supplier_id: v })} optionLabel={labels.coded} required /></Grid>
          <Grid size={12}><TextField label="Approval document reference" value={form.approval_doc_ref} onChange={(e) => setForm({ ...form, approval_doc_ref: e.target.value })} helperText="Procurement committee / tender approval" /></Grid>
        </Grid>
        <LineItemsEditor lines={lines} onChange={setLines} qtyLabel="Ordered" withPrice defaultUnit="purchase" />
        {error !== null && <Alert severity="error" sx={{ mt: 2 }}>{errorMessage(error)}</Alert>}
      </DialogContent>
      <DialogActions sx={{ px: 3, py: 2 }}>
        <Button onClick={onClose} disabled={busy}>Cancel</Button>
        <Button variant="contained" onClick={save} disabled={busy || !form.po_number || !form.supplier_id || !linesValid(lines)}>{busy ? 'Saving…' : 'Save purchase order'}</Button>
      </DialogActions>
    </Dialog>
  )
}

export function PurchaseOrdersPage() {
  const { can } = useAuth()
  const [creating, setCreating] = useState(false)

  return (
    <>
      <ListPage<Rec>
        title="Purchase orders"
        subtitle="Reference copies of approved POs. Goods are received against them on GRNs; outstanding quantities are calculated."
        endpoint="/purchase-orders"
        queryKey={['/purchase-orders']}
        columns={columns}
        sortFields={['po_number', 'po_date']}
        defaultSort={[{ field: 'po_date', sort: 'desc' }]}
        filters={[{ name: 'status', label: 'Status', options: ['open', 'partly_received', 'fully_received', 'closed', 'cancelled'] }, { name: 'supplier_id', label: 'Supplier', lookup: { endpoint: '/suppliers', label: labels.name } }]}
        searchPlaceholder="Search PO number"
        rowLink={(r) => `/purchase-orders/${r.public_id}`}
        createLabel="New purchase order"
        onCreate={can('po.manage') ? () => setCreating(true) : undefined}
      />
      <NewPurchaseOrder open={creating} onClose={() => setCreating(false)} />
    </>
  )
}

export function PurchaseOrderDetailPage() {
  const { id } = useParams()
  const { can } = useAuth()
  const run = useRun()
  const navigate = useNavigate()
  const po = useDoc<Rec>(['/purchase-orders', id], `/purchase-orders/${id}`)
  const [dialog, setDialog] = useState<'closed' | 'cancelled' | null>(null)

  if (po.isLoading) return <Skeleton height={300} />
  if (po.isError || !po.data) return <Alert severity="error">{errorMessage(po.error)}</Alert>
  const p = po.data
  const open = ['open', 'partly_received'].includes(p.status)

  const spec: ActionSpec | null = dialog && {
    title: dialog === 'closed' ? 'Close purchase order' : 'Cancel purchase order',
    description: dialog === 'closed' ? 'No more goods will be received against this order.' : 'Only orders with nothing received can be cancelled.',
    remarks: 'required',
    danger: dialog === 'cancelled',
    confirmLabel: dialog === 'closed' ? 'Close order' : 'Cancel order',
    run: (r) => run('patch', `/purchase-orders/${p.public_id}/status`, { status: dialog, reason: r }, [['/purchase-orders']]),
  }

  return (
    <>
      <PageHeader
        crumbs={[{ label: 'Purchase orders', to: '/purchase-orders' }, { label: p.po_number }]}
        title={<Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}><span>{p.po_number}</span><StatusChip value={p.status} /></Stack>}
        subtitle={p.supplier?.name}
        actions={
          <>
            {can('grn.create') && open && <Button variant="contained" onClick={() => navigate(`/grns/new?po=${p.public_id}`)}>Receive goods (GRN)</Button>}
            {can('po.manage') && open && <Button variant="outlined" onClick={() => setDialog('closed')}>Close</Button>}
            {can('po.manage') && p.status === 'open' && <Button variant="outlined" color="error" onClick={() => setDialog('cancelled')}>Cancel</Button>}
          </>
        }
      />
      <Stack spacing={2.5}>
        <SectionCard>
          <InfoGrid columns={4} items={[
            { label: 'PO date', value: formatDate(p.po_date) },
            { label: 'Expected delivery', value: formatDate(p.expected_delivery_on) },
            { label: 'Supplier', value: p.supplier?.name },
            { label: 'Approval reference', value: p.approval_doc_ref },
          ]} />
        </SectionCard>
        <SectionCard title="Lines" icon={<ListAltOutlinedIcon />} noPadding>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>#</TableCell><TableCell>Item</TableCell><TableCell align="right">Ordered</TableCell><TableCell align="right">Received</TableCell><TableCell align="right">Outstanding</TableCell><TableCell align="right">Unit price</TableCell><TableCell align="right">Line total</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {p.lines.map((l: Rec) => (
                <TableRow key={l.id}>
                  <TableCell>{l.line_no}</TableCell>
                  <TableCell><CodeText>{l.item?.item_code}</CodeText> {l.item?.name_en}</TableCell>
                  <TableCell align="right"><Qty value={l.ordered_qty} unit={l.unit?.code} /></TableCell>
                  <TableCell align="right"><Qty value={l.received_qty} /></TableCell>
                  <TableCell align="right"><Qty value={l.outstanding_qty} /></TableCell>
                  <TableCell align="right"><Money value={l.unit_price} /></TableCell>
                  <TableCell align="right"><Money value={l.line_total} /></TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </SectionCard>
      </Stack>
      <ActionDialog action={spec} onClose={() => setDialog(null)} />
    </>
  )
}
