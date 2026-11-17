import AssignmentReturnOutlinedIcon from '@mui/icons-material/AssignmentReturnOutlined'
import OutboxOutlinedIcon from '@mui/icons-material/OutboxOutlined'
import { Alert, Box, Button, Chip, MenuItem, Skeleton, Stack, Table, TableBody, TableCell, TableHead, TableRow, TextField } from '@mui/material'
import type { GridColDef } from '@mui/x-data-grid'
import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'
import { ActionDialog, type ActionSpec } from '../../components/ActionDialog'
import { ListPage } from '../../components/ListPage'
import { errorMessage } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { labels } from '../../components/Pickers'
import { StatusChip } from '../../components/StatusChip'
import { CodeText, InfoGrid, Qty, SectionCard } from '../../components/ui'
import { useDoc, useRun } from '../../hooks/useApi'
import { formatDate, formatDateTime, humanize } from '../../utils/format'

type Rec = Record<string, any> // eslint-disable-line @typescript-eslint/no-explicit-any

export function IssuesPage() {
  const columns: GridColDef<Rec>[] = [
    { field: 'issue_no', headerName: 'Issue', width: 160, renderCell: ({ value }) => <CodeText>{value}</CodeText> },
    { field: 'issue_on', headerName: 'Date', width: 120, valueFormatter: (v) => formatDate(v) },
    { field: 'request', headerName: 'Request', width: 160, sortable: false, valueGetter: (_, r) => r.request?.request_no },
    { field: 'department', headerName: 'Department', flex: 1, minWidth: 180, sortable: false, valueGetter: (_, r) => r.request?.department_name_snapshot },
    { field: 'store', headerName: 'Store', width: 150, sortable: false, valueGetter: (_, r) => r.store?.name },
    { field: 'issued_by', headerName: 'Issued by', width: 160, sortable: false, valueGetter: (_, r) => r.issued_by?.full_name },
    { field: 'status', headerName: 'Status', width: 110, renderCell: ({ value }) => <StatusChip value={value} /> },
    { field: 'ack', headerName: 'Receipt', width: 130, sortable: false, renderCell: ({ row }) => (row.acknowledgement ? <StatusChip value={row.acknowledgement.status === 'confirmed' ? 'active' : 'deny'} label={humanize(row.acknowledgement.status)} /> : row.status === 'posted' ? <Chip label="awaiting" variant="outlined" /> : '—') },
  ]

  return (
    <ListPage<Rec>
      title="Issues"
      subtitle="Goods handed over against approved requests. Saving prepares an issue; posting deducts stock. The receiving department confirms receipt separately."
      endpoint="/issues"
      queryKey={['/issues']}
      columns={columns}
      sortFields={['issue_no', 'issue_on']}
      defaultSort={[{ field: 'issue_no', sort: 'desc' }]}
      filters={[
        { name: 'status', label: 'Status', options: ['draft', 'posted', 'reversed'] },
        { name: 'unacknowledged', label: 'Receipt', options: [{ value: 'true', label: 'Not yet confirmed' }] },
        { name: 'store_id', label: 'Store', lookup: { endpoint: '/stores', label: labels.name } },
      ]}
      searchPlaceholder="Issue number"
      rowLink={(r) => `/issues/${r.public_id}`}
    />
  )
}

export function IssueDetailPage() {
  const { id } = useParams()
  const { can } = useAuth()
  const run = useRun()
  const navigate = useNavigate()
  const issue = useDoc<Rec>(['/issues', id], `/issues/${id}`)
  const [dialog, setDialog] = useState<string | null>(null)
  const [ackStatus, setAckStatus] = useState<'confirmed' | 'discrepancy'>('confirmed')
  const [received, setReceived] = useState<Record<number, string>>({})
  const [returnQty, setReturnQty] = useState<Record<number, string>>({})

  useEffect(() => {
    if (issue.data) setReceived(Object.fromEntries(issue.data.lines.map((l: Rec) => [l.id, String(Number(l.qty))])))
  }, [issue.data])

  if (issue.isLoading) return <Skeleton height={300} />
  if (issue.isError || !issue.data) return <Alert severity="error">{errorMessage(issue.error)}</Alert>
  const i = issue.data
  const inv = [['/issues'], ['/issues', id], ['/requests'], ['/stock/balances']]

  const specs: Record<string, ActionSpec> = {
    post: { title: `Post ${i.issue_no}`, description: 'Deducts the quantities from stock and records the hand-over. Repeating this is safe — it never deducts twice.', confirmLabel: 'Post issue', run: () => run('post', `/issues/${i.public_id}/post`, {}, inv, { idempotent: true }) },
    reverse: { title: `Reverse ${i.issue_no}`, description: 'Puts the goods back into stock with linked reversal entries.', remarks: 'required', danger: true, confirmLabel: 'Reverse', run: (r) => run('post', `/issues/${i.public_id}/reverse`, { reason: r }, inv) },
    acknowledge: {
      title: 'Confirm receipt',
      description: 'Confirm what the department actually received. A shortfall opens a discrepancy for review — stock is never silently adjusted.',
      remarks: ackStatus === 'discrepancy' ? 'required' : 'optional',
      remarksLabel: ackStatus === 'discrepancy' ? 'Describe the discrepancy' : 'Remarks (optional)',
      confirmLabel: ackStatus === 'confirmed' ? 'Confirm receipt' : 'Report discrepancy',
      body: (
        <Stack spacing={2}>
          <TextField select label="Result" value={ackStatus} onChange={(e) => setAckStatus(e.target.value as 'confirmed' | 'discrepancy')}>
            <MenuItem value="confirmed">Everything received in good order</MenuItem>
            <MenuItem value="discrepancy">Something is short or damaged</MenuItem>
          </TextField>
          {ackStatus === 'discrepancy' && (
            <Table size="small">
              <TableHead><TableRow><TableCell>Item</TableCell><TableCell align="right">Issued</TableCell><TableCell width={140}>Received</TableCell></TableRow></TableHead>
              <TableBody>
                {i.lines.map((l: Rec) => (
                  <TableRow key={l.id}>
                    <TableCell>{l.item?.name_en}</TableCell>
                    <TableCell align="right"><Qty value={l.qty} unit={l.unit?.code} /></TableCell>
                    <TableCell><TextField type="number" value={received[l.id] ?? ''} onChange={(e) => setReceived({ ...received, [l.id]: e.target.value })} /></TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </Stack>
      ),
      run: (r) => run('post', `/issues/${i.public_id}/acknowledge`, { status: ackStatus, remarks: r || null, lines: ackStatus === 'discrepancy' ? i.lines.map((l: Rec) => ({ issue_line_id: l.id, received_qty: received[l.id] || 0 })) : [] }, inv),
    },
    return: {
      title: 'Return goods to Stores',
      description: 'Stores will inspect them; only the accepted quantity goes back into stock.',
      remarks: 'required',
      remarksLabel: 'Reason for returning',
      confirmLabel: 'Submit return',
      body: (
        <Table size="small">
          <TableHead><TableRow><TableCell>Item</TableCell><TableCell align="right">Issued</TableCell><TableCell width={140}>Return</TableCell></TableRow></TableHead>
          <TableBody>
            {i.lines.map((l: Rec) => (
              <TableRow key={l.id}>
                <TableCell>{l.item?.name_en}{l.asset ? ` (${l.asset.asset_no})` : ''}</TableCell>
                <TableCell align="right"><Qty value={l.qty} unit={l.unit?.code} /></TableCell>
                <TableCell><TextField type="number" value={returnQty[l.id] ?? ''} onChange={(e) => setReturnQty({ ...returnQty, [l.id]: e.target.value })} /></TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      ),
      run: async (r) => {
        const ret = await run<Rec>('post', '/department-returns', { issue_id: i.id, reason: r, lines: i.lines.filter((l: Rec) => Number(returnQty[l.id]) > 0).map((l: Rec) => ({ issue_line_id: l.id, qty_returned: returnQty[l.id] })) }, [['/department-returns']])
        navigate(`/department-returns/${ret.public_id}`)
      },
    },
  }

  return (
    <>
      <PageHeader
        crumbs={[{ label: 'Issues', to: '/issues' }, { label: i.issue_no }]}
        title={<Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}><span>{i.issue_no}</span><StatusChip value={i.status} /></Stack>}
        subtitle={`${i.request?.department_name_snapshot} · from ${i.store?.name}`}
        actions={
          <>
            {i.status === 'draft' && can('issue.post') && <Button variant="contained" color="success" onClick={() => setDialog('post')}>Post issue</Button>}
            {i.status === 'posted' && !i.acknowledgement && can('issue.acknowledge') && <Button variant="contained" onClick={() => setDialog('acknowledge')}>Confirm receipt</Button>}
            {i.status === 'posted' && can('return.create') && <Button variant="outlined" startIcon={<AssignmentReturnOutlinedIcon />} onClick={() => setDialog('return')}>Return goods</Button>}
            {i.status === 'posted' && can('issue.reverse') && <Button variant="outlined" color="error" onClick={() => setDialog('reverse')}>Reverse</Button>}
            <Button onClick={() => navigate(`/requests/${i.request?.public_id}`)}>Open request</Button>
          </>
        }
      />
      <Stack spacing={2.5}>
        <SectionCard>
          <InfoGrid columns={4} items={[
            { label: 'Request', value: i.request?.request_no },
            { label: 'Issue date', value: formatDate(i.issue_on) },
            { label: 'Issued by', value: i.issued_by?.full_name },
            { label: 'Posted', value: i.posted_at ? formatDateTime(i.posted_at) : 'Not posted' },
            { label: 'Receipt', value: i.acknowledgement ? `${humanize(i.acknowledgement.status)} by ${i.acknowledgement.received_by?.full_name ?? ''} · ${formatDateTime(i.acknowledgement.received_at)}` : 'Not confirmed yet', wide: true },
          ]} />
        </SectionCard>
        <SectionCard title="Items handed over" icon={<OutboxOutlinedIcon />} noPadding>
          <Box sx={{ overflowX: 'auto' }}>
            <Table>
              <TableHead><TableRow><TableCell>Item</TableCell><TableCell>Asset</TableCell><TableCell>Batch</TableCell><TableCell align="right">Quantity</TableCell></TableRow></TableHead>
              <TableBody>
                {i.lines.map((l: Rec) => (
                  <TableRow key={l.id}>
                    <TableCell><CodeText>{l.item?.item_code}</CodeText> {l.item?.name_en}</TableCell>
                    <TableCell>{l.asset ? <Button size="small" onClick={() => navigate(`/assets/${l.asset.public_id}`)}>{l.asset.asset_no}</Button> : '—'}</TableCell>
                    <TableCell>{l.batch_no ?? '—'}</TableCell>
                    <TableCell align="right"><Qty value={l.qty} unit={l.unit?.code} /></TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </Box>
        </SectionCard>
      </Stack>
      <ActionDialog action={dialog ? specs[dialog] : null} onClose={() => setDialog(null)} />
    </>
  )
}

export function DepartmentReturnsPage() {
  const columns: GridColDef<Rec>[] = [
    { field: 'return_no', headerName: 'Return', width: 160, renderCell: ({ value }) => <CodeText>{value}</CodeText> },
    { field: 'return_on', headerName: 'Date', width: 120, valueFormatter: (v) => formatDate(v) },
    { field: 'department', headerName: 'Department', flex: 1, minWidth: 180, sortable: false, valueGetter: (_, r) => r.department?.name_en },
    { field: 'original_issue', headerName: 'From issue', width: 160, sortable: false, valueGetter: (_, r) => r.original_issue?.issue_no },
    { field: 'reason', headerName: 'Reason', flex: 1, minWidth: 200 },
    { field: 'status', headerName: 'Status', width: 120, renderCell: ({ value }) => <StatusChip value={value} /> },
  ]

  return (
    <ListPage<Rec>
      title="Returns to stores"
      subtitle="Goods sent back by departments. Stores inspects them; only the accepted quantity goes back into usable stock."
      endpoint="/department-returns"
      queryKey={['/department-returns']}
      columns={columns}
      sortFields={['return_no', 'return_on']}
      defaultSort={[{ field: 'return_no', sort: 'desc' }]}
      filters={[{ name: 'status', label: 'Status', options: ['submitted', 'inspected', 'posted'] }]}
      rowLink={(r) => `/department-returns/${r.public_id}`}
    />
  )
}

export function DepartmentReturnDetailPage() {
  const { id } = useParams()
  const { can } = useAuth()
  const run = useRun()
  const ret = useDoc<Rec>(['/department-returns', id], `/department-returns/${id}`)
  const [dialog, setDialog] = useState<string | null>(null)
  const [accepted, setAccepted] = useState<Record<number, { qty: string; condition: string }>>({})

  useEffect(() => {
    if (ret.data) setAccepted(Object.fromEntries(ret.data.lines.map((l: Rec) => [l.id, { qty: String(Number(l.qty_accepted ?? l.qty_returned)), condition: l.condition ?? 'good' }])))
  }, [ret.data])

  if (ret.isLoading) return <Skeleton height={300} />
  if (ret.isError || !ret.data) return <Alert severity="error">{errorMessage(ret.error)}</Alert>
  const d = ret.data
  const inv = [['/department-returns'], ['/department-returns', id], ['/stock/balances']]
  const inspecting = d.status === 'submitted' && can('return.inspect')

  const specs: Record<string, ActionSpec> = {
    inspect: { title: 'Record inspection', confirmLabel: 'Save', run: () => run('post', `/department-returns/${d.public_id}/inspect`, { lines: d.lines.map((l: Rec) => ({ id: l.id, qty_accepted: accepted[l.id]?.qty || 0, condition: accepted[l.id]?.condition })) }, inv) },
    post: { title: `Post ${d.return_no}`, description: 'Puts the accepted quantities back into usable stock.', confirmLabel: 'Post', run: () => run('post', `/department-returns/${d.public_id}/post`, {}, inv, { idempotent: true }) },
  }

  return (
    <>
      <PageHeader
        crumbs={[{ label: 'Returns to stores', to: '/department-returns' }, { label: d.return_no }]}
        title={<Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}><span>{d.return_no}</span><StatusChip value={d.status} /></Stack>}
        subtitle={`${d.department?.name_en} → ${d.store?.name} · ${d.reason ?? ''}`}
        actions={
          <>
            {inspecting && <Button variant="contained" onClick={() => setDialog('inspect')}>Save inspection</Button>}
            {d.status === 'inspected' && can('return.inspect') && <Button variant="contained" color="success" onClick={() => setDialog('post')}>Post to stock</Button>}
          </>
        }
      />
      <SectionCard title="Returned items" icon={<AssignmentReturnOutlinedIcon />} noPadding>
        <Table>
          <TableHead><TableRow><TableCell>Item</TableCell><TableCell align="right">Returned</TableCell><TableCell>Accepted</TableCell><TableCell>Condition</TableCell></TableRow></TableHead>
          <TableBody>
            {d.lines.map((l: Rec) => (
              <TableRow key={l.id}>
                <TableCell><CodeText>{l.item?.item_code}</CodeText> {l.item?.name_en}</TableCell>
                <TableCell align="right"><Qty value={l.qty_returned} /></TableCell>
                <TableCell sx={{ width: 140 }}>{inspecting ? <TextField type="number" value={accepted[l.id]?.qty ?? ''} onChange={(e) => setAccepted({ ...accepted, [l.id]: { ...accepted[l.id], qty: e.target.value } })} /> : <Qty value={l.qty_accepted} />}</TableCell>
                <TableCell sx={{ width: 180 }}>
                  {inspecting ? (
                    <TextField select value={accepted[l.id]?.condition ?? 'good'} onChange={(e) => setAccepted({ ...accepted, [l.id]: { ...accepted[l.id], condition: e.target.value } })}>
                      {['new', 'good', 'fair', 'poor', 'damaged'].map((c) => <MenuItem key={c} value={c}>{humanize(c)}</MenuItem>)}
                    </TextField>
                  ) : humanize(l.condition ?? '—')}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </SectionCard>
      <ActionDialog action={dialog ? specs[dialog] : null} onClose={() => setDialog(null)} />
    </>
  )
}
