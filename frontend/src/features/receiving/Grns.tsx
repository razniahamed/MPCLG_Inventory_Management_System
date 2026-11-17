import FactCheckOutlinedIcon from '@mui/icons-material/FactCheckOutlined'
import ListAltOutlinedIcon from '@mui/icons-material/ListAltOutlined'
import { Alert, Box, Button, Chip, Grid, MenuItem, Skeleton, Stack, Table, TableBody, TableCell, TableHead, TableRow, TextField, Typography } from '@mui/material'
import type { GridColDef } from '@mui/x-data-grid'
import { useEffect, useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { get } from '../../api/client'
import { useAuth } from '../../auth/AuthContext'
import { ActionDialog, type ActionSpec } from '../../components/ActionDialog'
import { ApprovalTimeline } from '../../components/ApprovalTimeline'
import { LineItemsEditor, linesValid, newLine, type LineDraft } from '../../components/LineItemsEditor'
import { ListPage } from '../../components/ListPage'
import { errorMessage } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { LookupField, labels } from '../../components/Pickers'
import { StatusChip } from '../../components/StatusChip'
import { CodeText, InfoGrid, Money, Qty, SectionCard } from '../../components/ui'
import { useDoc, useRun } from '../../hooks/useApi'
import { formatDate, formatDateTime, humanize } from '../../utils/format'

type Rec = Record<string, any> // eslint-disable-line @typescript-eslint/no-explicit-any

const STATUSES = ['draft', 'awaiting_inspection', 'awaiting_approval', 'posted', 'rejected', 'reversed']

const columns: GridColDef<Rec>[] = [
  { field: 'grn_no', headerName: 'GRN no.', width: 160, renderCell: ({ value }) => <CodeText>{value}</CodeText> },
  { field: 'receipt_on', headerName: 'Received', width: 120, valueFormatter: (v) => formatDate(v) },
  { field: 'receipt_authority_type', headerName: 'Authority', width: 140, valueGetter: (v) => humanize(String(v)) },
  { field: 'supplier', headerName: 'Supplier', flex: 1, minWidth: 180, sortable: false, valueGetter: (_, r) => r.supplier?.name ?? '—' },
  { field: 'store', headerName: 'Store', width: 160, sortable: false, valueGetter: (_, r) => r.store?.name },
  { field: 'invoice_no', headerName: 'Invoice', width: 150 },
  { field: 'grand_total', headerName: 'Value', width: 140, renderCell: ({ value }) => <Money value={value} /> },
  { field: 'status', headerName: 'Status', width: 170, renderCell: ({ value }) => <StatusChip value={value} /> },
]

export function GrnsPage() {
  const { can } = useAuth()
  const navigate = useNavigate()

  return (
    <ListPage<Rec>
      title="Goods received notes"
      subtitle="Receipts into Stores. Inspected, independently approved and only then posted — rejected or pending quantities never become usable stock."
      endpoint="/grns"
      queryKey={['/grns']}
      columns={columns}
      sortFields={['grn_no', 'receipt_on']}
      defaultSort={[{ field: 'grn_no', sort: 'desc' }]}
      filters={[{ name: 'status', label: 'Status', options: STATUSES, width: 200 }, { name: 'store_id', label: 'Store', lookup: { endpoint: '/stores', label: labels.name } }]}
      searchPlaceholder="GRN, invoice or delivery note"
      rowLink={(r) => `/grns/${r.public_id}`}
      createLabel="New GRN"
      onCreate={can('grn.create') ? () => navigate('/grns/new') : undefined}
    />
  )
}

export function NewGrnPage() {
  const run = useRun()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const [form, setForm] = useState({
    receipt_authority_type: params.get('po') ? 'po' : 'po',
    purchase_order_id: null as number | null,
    receipt_authority_ref: '',
    supplier_id: null as number | null,
    store_id: null as number | null,
    receipt_on: new Date().toISOString().slice(0, 10),
    invoice_no: '',
    invoice_on: '',
    delivery_note_no: '',
  })
  const [poPublicId, setPoPublicId] = useState<string | null>(params.get('po'))
  const [poLines, setPoLines] = useState<Rec[]>([])
  const [receive, setReceive] = useState<Record<number, string>>({})
  const [lines, setLines] = useState<LineDraft[]>([newLine()])
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const isPo = form.receipt_authority_type === 'po'

  useEffect(() => {
    if (!isPo || !poPublicId) {
      setPoLines([])
      return
    }
    get<Rec>(`/purchase-orders/${poPublicId}`)
      .then((po) => {
        setPoLines(po.lines ?? [])
        setForm((f) => ({ ...f, purchase_order_id: po.id, supplier_id: po.supplier_id }))
      })
      .catch(() => setPoLines([]))
  }, [poPublicId, isPo])

  const save = async () => {
    setBusy(true)
    setError(null)
    try {
      const body = {
        ...form,
        purchase_order_id: isPo ? form.purchase_order_id : null,
        supplier_id: isPo ? null : form.supplier_id,
        invoice_on: form.invoice_on || null,
        lines: isPo
          ? poLines.filter((l) => Number(receive[l.id]) > 0).map((l) => ({ item_id: l.item_id, unit_id: l.unit_id, po_line_id: l.id, delivered_qty: receive[l.id], unit_price: l.unit_price }))
          : lines.map((l) => ({ item_id: l.item!.id, unit_id: l.unit_id, delivered_qty: l.qty, unit_price: l.unit_price || null })),
      }
      const grn = await run<Rec>('post', '/grns', body, [['/grns']])
      navigate(`/grns/${grn.public_id}`)
    } catch (e) {
      setError(e)
    } finally {
      setBusy(false)
    }
  }

  const valid = form.store_id && form.receipt_on && (isPo ? form.purchase_order_id && poLines.some((l) => Number(receive[l.id]) > 0) : form.receipt_authority_ref && linesValid(lines))

  return (
    <>
      <PageHeader crumbs={[{ label: 'Goods received', to: '/grns' }, { label: 'New GRN' }]} title="Receive goods" subtitle="Record what was delivered. Inspection and approval follow before anything is posted to stock." />
      <Stack spacing={2.5}>
        <SectionCard title="Receipt" icon={<FactCheckOutlinedIcon />}>
          <Grid container spacing={2}>
            <Grid size={{ xs: 12, md: 4 }}>
              <TextField select label="Receipt authority" value={form.receipt_authority_type} onChange={(e) => setForm({ ...form, receipt_authority_type: e.target.value })}>
                <MenuItem value="po">Purchase order</MenuItem>
                <MenuItem value="donation">Donation</MenuItem>
                <MenuItem value="transfer_in">Transfer in</MenuItem>
                <MenuItem value="opening_balance">Opening balance</MenuItem>
              </TextField>
            </Grid>
            {isPo ? (
              <Grid size={{ xs: 12, md: 8 }}>
                <LookupField endpoint="/purchase-orders" label="Purchase order" valueKey="public_id" value={poPublicId} onChange={(v) => setPoPublicId(v as unknown as string)} optionLabel={(r) => `${r.po_number} — ${(r.supplier as Rec)?.name ?? ''} (${humanize(String(r.status))})`} filters={{ is_active: undefined, status: 'open,partly_received' }} required />
              </Grid>
            ) : (
              <>
                <Grid size={{ xs: 12, md: 4 }}><TextField label="Authority reference" value={form.receipt_authority_ref} onChange={(e) => setForm({ ...form, receipt_authority_ref: e.target.value })} required helperText="Donation letter, transfer note or opening-count approval" /></Grid>
                <Grid size={{ xs: 12, md: 4 }}><LookupField endpoint="/suppliers" label="Supplier / donor (optional)" value={form.supplier_id} onChange={(v) => setForm({ ...form, supplier_id: v })} optionLabel={labels.coded} /></Grid>
              </>
            )}
            <Grid size={{ xs: 12, md: 4 }}><LookupField endpoint="/stores" label="Receiving store" value={form.store_id} onChange={(v) => setForm({ ...form, store_id: v })} optionLabel={labels.coded} required /></Grid>
            <Grid size={{ xs: 6, md: 2 }}><TextField label="Received on" type="date" value={form.receipt_on} onChange={(e) => setForm({ ...form, receipt_on: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} /></Grid>
            <Grid size={{ xs: 6, md: 2 }}><TextField label="Invoice no." value={form.invoice_no} onChange={(e) => setForm({ ...form, invoice_no: e.target.value })} /></Grid>
            <Grid size={{ xs: 6, md: 2 }}><TextField label="Invoice date" type="date" value={form.invoice_on} onChange={(e) => setForm({ ...form, invoice_on: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} /></Grid>
            <Grid size={{ xs: 6, md: 2 }}><TextField label="Delivery note" value={form.delivery_note_no} onChange={(e) => setForm({ ...form, delivery_note_no: e.target.value })} /></Grid>
          </Grid>
        </SectionCard>

        <SectionCard title="Delivered items" icon={<ListAltOutlinedIcon />}>
          {isPo ? (
            poLines.length === 0 ? (
              <Typography color="text.secondary">Choose a purchase order to list its lines.</Typography>
            ) : (
              <Table size="small">
                <TableHead>
                  <TableRow><TableCell>Item</TableCell><TableCell align="right">Ordered</TableCell><TableCell align="right">Outstanding</TableCell><TableCell width={180}>Delivered now</TableCell></TableRow>
                </TableHead>
                <TableBody>
                  {poLines.map((l) => (
                    <TableRow key={l.id}>
                      <TableCell><CodeText>{l.item?.item_code}</CodeText> {l.item?.name_en}</TableCell>
                      <TableCell align="right"><Qty value={l.ordered_qty} unit={l.unit?.code} /></TableCell>
                      <TableCell align="right"><Qty value={l.outstanding_qty} /></TableCell>
                      <TableCell><TextField type="number" value={receive[l.id] ?? ''} onChange={(e) => setReceive({ ...receive, [l.id]: e.target.value })} placeholder="0" /></TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )
          ) : (
            <LineItemsEditor lines={lines} onChange={setLines} qtyLabel="Delivered" withPrice defaultUnit="purchase" />
          )}
        </SectionCard>

        {error !== null && <Alert severity="error">{errorMessage(error)}</Alert>}
        <Stack direction="row" spacing={1} sx={{ justifyContent: 'flex-end' }}>
          <Button onClick={() => navigate('/grns')}>Cancel</Button>
          <Button variant="contained" size="large" onClick={save} disabled={busy || !valid}>{busy ? 'Saving…' : 'Save GRN as draft'}</Button>
        </Stack>
      </Stack>
    </>
  )
}

export function GrnDetailPage() {
  const { id } = useParams()
  const { can } = useAuth()
  const run = useRun()
  const navigate = useNavigate()
  const grn = useDoc<Rec>(['/grns', id], `/grns/${id}`)
  const [dialog, setDialog] = useState<string | null>(null)
  const [inspection, setInspection] = useState<Record<number, { accepted: string; rejected: string; reason: string }>>({})

  useEffect(() => {
    if (grn.data) {
      setInspection(Object.fromEntries(grn.data.lines.map((l: Rec) => [l.id, { accepted: String(Number(l.accepted_qty) || Number(l.delivered_qty)), rejected: String(Number(l.rejected_qty) || 0), reason: l.rejection_reason ?? '' }])))
    }
  }, [grn.data])

  if (grn.isLoading) return <Skeleton height={300} />
  if (grn.isError || !grn.data) return <Alert severity="error">{errorMessage(grn.error)}</Alert>
  const g = grn.data
  const inv = [['/grns'], ['/grns', id], ['/purchase-orders']]
  const inspecting = ['awaiting_inspection', 'awaiting_approval'].includes(g.status) && can('grn.inspect')

  const specs: Record<string, ActionSpec> = {
    submit: { title: 'Submit for inspection', description: 'The receiving officer will check quantities and condition.', confirmLabel: 'Submit', run: () => run('post', `/grns/${g.public_id}/submit`, {}, inv) },
    inspect: {
      title: 'Record inspection',
      description: 'Accepted + rejected must not exceed delivered; anything left stays pending. Rejected quantities need a reason.',
      remarks: 'optional',
      confirmLabel: 'Save inspection',
      run: (r) => run('post', `/grns/${g.public_id}/inspect`, { remarks: r || null, lines: g.lines.map((l: Rec) => ({ id: l.id, accepted_qty: inspection[l.id]?.accepted || 0, rejected_qty: inspection[l.id]?.rejected || 0, rejection_reason: inspection[l.id]?.reason || null })) }, inv),
    },
    approve: { title: 'Approve and post to stock', description: 'Posts the ACCEPTED quantities to the stock ledger. You cannot approve a GRN you received.', remarks: 'optional', confirmLabel: 'Approve & post', run: (r) => run('post', `/grns/${g.public_id}/approve-post`, { remarks: r || null }, inv, { idempotent: true }) },
    reject: { title: 'Reject GRN', remarks: 'required', danger: true, confirmLabel: 'Reject', run: (r) => run('post', `/grns/${g.public_id}/reject`, { reason: r }, inv) },
    reverse: { title: 'Reverse posted GRN', description: 'Creates linked reversal entries in the ledger. The stock must still be available.', remarks: 'required', danger: true, confirmLabel: 'Reverse', run: (r) => run('post', `/grns/${g.public_id}/reverse`, { reason: r }, inv) },
  }

  return (
    <>
      <PageHeader
        crumbs={[{ label: 'Goods received', to: '/grns' }, { label: g.grn_no }]}
        title={<Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}><span>{g.grn_no}</span><StatusChip value={g.status} /></Stack>}
        subtitle={`${humanize(g.receipt_authority_type)} · ${g.store?.name}${g.supplier ? ' · ' + g.supplier.name : ''}`}
        actions={
          <>
            {g.status === 'draft' && can('grn.create') && <Button variant="contained" onClick={() => setDialog('submit')}>Submit for inspection</Button>}
            {inspecting && <Button variant={g.status === 'awaiting_inspection' ? 'contained' : 'outlined'} onClick={() => setDialog('inspect')}>Save inspection</Button>}
            {g.status === 'awaiting_approval' && can('grn.approve_post') && <Button variant="contained" color="success" onClick={() => setDialog('approve')}>Approve & post</Button>}
            {['awaiting_inspection', 'awaiting_approval'].includes(g.status) && can('grn.approve_post') && <Button variant="outlined" color="error" onClick={() => setDialog('reject')}>Reject</Button>}
            {g.status === 'posted' && can('grn.reverse') && <Button variant="outlined" color="error" onClick={() => setDialog('reverse')}>Reverse</Button>}
            {g.status === 'posted' && can('supplier_return.manage') && g.supplier && <Button variant="outlined" onClick={() => navigate(`/supplier-returns?grn=${g.public_id}`)}>Return to supplier</Button>}
          </>
        }
      />
      <Grid container spacing={2.5}>
        <Grid size={{ xs: 12, lg: 8 }}>
          <Stack spacing={2.5}>
            <SectionCard>
              <InfoGrid columns={4} items={[
                { label: 'Received on', value: formatDate(g.receipt_on) },
                { label: 'Posted', value: g.posted_at ? formatDateTime(g.posted_at) : '—' },
                { label: 'Purchase order', value: g.purchase_order?.po_number ?? '—' },
                { label: 'Authority ref.', value: g.receipt_authority_ref },
                { label: 'Invoice', value: g.invoice_no ? `${g.invoice_no} (${formatDate(g.invoice_on)})` : '—' },
                { label: 'Delivery note', value: g.delivery_note_no },
                { label: 'Received by', value: g.received_by?.full_name },
                { label: 'Inspected by', value: g.inspected_by?.full_name },
                { label: 'Approved by', value: g.authorised_by ? `${g.authorised_by.full_name} (${g.authorised_designation_snapshot ?? ''})` : '—' },
                { label: 'Grand total', value: <Money value={g.grand_total} /> },
              ]} />
            </SectionCard>
            <SectionCard title="Lines" icon={<ListAltOutlinedIcon />} noPadding>
              <Box sx={{ overflowX: 'auto' }}>
                <Table>
                  <TableHead>
                    <TableRow>
                      <TableCell>#</TableCell><TableCell>Item</TableCell><TableCell align="right">Delivered</TableCell>
                      <TableCell align="right">Accepted</TableCell><TableCell align="right">Rejected</TableCell><TableCell align="right">Pending</TableCell><TableCell align="right">Value</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {g.lines.map((l: Rec) => (
                      <TableRow key={l.id}>
                        <TableCell>{l.line_no}</TableCell>
                        <TableCell>
                          <CodeText>{l.item?.item_code}</CodeText> {l.item?.name_en}
                          {l.item?.tracking_method === 'asset' && <Chip label="asset" color="info" variant="outlined" sx={{ ml: 1 }} />}
                          {l.rejection_reason && <Typography sx={{ fontSize: 12, color: 'error.main' }}>Rejected: {l.rejection_reason}</Typography>}
                        </TableCell>
                        <TableCell align="right"><Qty value={l.delivered_qty} unit={l.unit?.code} /></TableCell>
                        {inspecting ? (
                          <>
                            <TableCell align="right" sx={{ minWidth: 110 }}><TextField type="number" value={inspection[l.id]?.accepted ?? ''} onChange={(e) => setInspection({ ...inspection, [l.id]: { ...inspection[l.id], accepted: e.target.value } })} /></TableCell>
                            <TableCell align="right" sx={{ minWidth: 200 }}>
                              <Stack spacing={1}>
                                <TextField type="number" value={inspection[l.id]?.rejected ?? ''} onChange={(e) => setInspection({ ...inspection, [l.id]: { ...inspection[l.id], rejected: e.target.value } })} />
                                {Number(inspection[l.id]?.rejected) > 0 && <TextField placeholder="Reason" value={inspection[l.id]?.reason ?? ''} onChange={(e) => setInspection({ ...inspection, [l.id]: { ...inspection[l.id], reason: e.target.value } })} />}
                              </Stack>
                            </TableCell>
                          </>
                        ) : (
                          <>
                            <TableCell align="right"><Qty value={l.accepted_qty} /></TableCell>
                            <TableCell align="right"><Qty value={l.rejected_qty} /></TableCell>
                          </>
                        )}
                        <TableCell align="right"><Qty value={l.pending_qty} /></TableCell>
                        <TableCell align="right"><Money value={l.line_total} /></TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </Box>
              {g.status === 'posted' && g.lines.some((l: Rec) => l.item?.tracking_method === 'asset') && can('asset.create') && (
                <Alert severity="info" sx={{ m: 2 }} action={<Button onClick={() => navigate(`/assets?register_grn=${g.id}`)}>Register assets</Button>}>
                  This GRN includes asset-tracked items. Register each unit to give it an asset number and QR label.
                </Alert>
              )}
            </SectionCard>
          </Stack>
        </Grid>
        <Grid size={{ xs: 12, lg: 4 }}>
          <SectionCard title="Approval trail" icon={<FactCheckOutlinedIcon />} tone="success">
            <ApprovalTimeline steps={g.approvals} />
          </SectionCard>
        </Grid>
      </Grid>
      <ActionDialog action={dialog ? specs[dialog] : null} onClose={() => setDialog(null)} />
    </>
  )
}
