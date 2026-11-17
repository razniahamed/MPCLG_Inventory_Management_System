import AssignmentOutlinedIcon from '@mui/icons-material/AssignmentOutlined'
import FactCheckOutlinedIcon from '@mui/icons-material/FactCheckOutlined'
import OutboxOutlinedIcon from '@mui/icons-material/OutboxOutlined'
import PrintOutlinedIcon from '@mui/icons-material/PrintOutlined'
import { Alert, Box, Button, Chip, Grid, MenuItem, Skeleton, Stack, Tab, Table, TableBody, TableCell, TableHead, TableRow, Tabs, TextField, Typography } from '@mui/material'
import type { GridColDef } from '@mui/x-data-grid'
import { useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { getPage, listParams } from '../../api/client'
import { useAuth } from '../../auth/AuthContext'
import { ActionDialog, type ActionSpec } from '../../components/ActionDialog'
import { ApprovalTimeline } from '../../components/ApprovalTimeline'
import { LineItemsEditor, linesValid, newLine, type LineDraft } from '../../components/LineItemsEditor'
import { errorMessage } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { LookupField, labels, type ItemOption } from '../../components/Pickers'
import { ServerTable } from '../../components/ServerTable'
import { StatusChip } from '../../components/StatusChip'
import { CodeText, InfoGrid, Qty, SectionCard } from '../../components/ui'
import { useDoc, useRun } from '../../hooks/useApi'
import { formatDate, formatDateTime, humanize } from '../../utils/format'

type Rec = Record<string, any> // eslint-disable-line @typescript-eslint/no-explicit-any

const columns: GridColDef<Rec>[] = [
  { field: 'request_no', headerName: 'Request', width: 160, renderCell: ({ value }) => <CodeText>{value}</CodeText> },
  { field: 'request_type', headerName: 'Type', width: 110, renderCell: ({ value }) => <Chip label={value === 'a4_paper' ? 'A4 paper' : 'Goods'} color={value === 'a4_paper' ? 'secondary' : 'default'} variant="outlined" /> },
  { field: 'department_name_snapshot', headerName: 'Department', flex: 1, minWidth: 180 },
  { field: 'purpose', headerName: 'Purpose', flex: 1, minWidth: 200 },
  { field: 'store', headerName: 'Store', width: 150, sortable: false, valueGetter: (_, r) => r.store?.name },
  { field: 'submitted_at', headerName: 'Submitted', width: 150, valueFormatter: (v) => (v ? formatDateTime(v) : '—') },
  { field: 'status', headerName: 'Status', width: 140, renderCell: ({ value }) => <StatusChip value={value} /> },
  { field: 'receipt_status', headerName: 'Receipt', width: 120, renderCell: ({ value }) => (value === 'pending' ? '—' : <StatusChip value={value === 'confirmed' ? 'active' : 'deny'} label={humanize(value)} />) },
]

const TABS = [
  { key: '', label: 'All' },
  { key: 'certify', label: 'To certify', perm: 'request.certify' },
  { key: 'approve', label: 'To approve', perm: 'request.approve' },
  { key: 'issue', label: 'To issue', perm: 'issue.create' },
]

export function RequestsPage() {
  const { can } = useAuth()
  const navigate = useNavigate()
  const [params, setParams] = useSearchParams()
  const awaiting = params.get('awaiting') ?? ''
  const [status, setStatus] = useState('')
  const [type, setType] = useState('')
  const tabs = TABS.filter((t) => !t.perm || can(t.perm))

  return (
    <>
      <PageHeader
        title="Requests"
        subtitle="Department requests for goods and A4 paper. Submitting does not reduce stock; approval reserves it; only the Storekeeper's posted issue deducts it."
        actions={can('request.create') && <Button variant="contained" onClick={() => navigate('/requests/new')}>New request</Button>}
      />
      {tabs.length > 1 && (
        <Tabs value={awaiting} onChange={(_, v) => setParams(v ? { awaiting: v } : {})} sx={{ mb: 2 }}>
          {tabs.map((t) => <Tab key={t.key} value={t.key} label={t.label} />)}
        </Tabs>
      )}
      <ServerTable<Rec>
        queryKey={['/requests']}
        endpoint="/requests"
        columns={columns}
        sortFields={['request_no', 'submitted_at']}
        defaultSort={[{ field: 'request_no', sort: 'desc' }]}
        filters={{ awaiting, status, request_type: type }}
        searchPlaceholder="Request no., purpose, department"
        onRowClick={(r) => navigate(`/requests/${r.public_id}`)}
        toolbar={
          <>
            <TextField select label="Status" value={status} onChange={(e) => setStatus(e.target.value)} sx={{ width: { xs: '100%', sm: 170 } }}>
              <MenuItem value="">All</MenuItem>
              {['draft', 'submitted', 'under_review', 'returned', 'approved', 'partly_issued', 'fully_issued', 'rejected', 'cancelled'].map((s) => <MenuItem key={s} value={s}>{humanize(s)}</MenuItem>)}
            </TextField>
            <TextField select label="Type" value={type} onChange={(e) => setType(e.target.value)} sx={{ width: { xs: '100%', sm: 140 } }}>
              <MenuItem value="">All</MenuItem>
              <MenuItem value="goods">Goods</MenuItem>
              <MenuItem value="a4_paper">A4 paper</MenuItem>
            </TextField>
          </>
        }
      />
    </>
  )
}

/** Create or revise a request. */
export function RequestFormPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const run = useRun()
  const { me } = useAuth()
  const existing = useDoc<Rec>(['/requests', id], id ? `/requests/${id}` : null)
  const [form, setForm] = useState({ request_type: 'goods', department_id: null as number | null, store_id: null as number | null, purpose: '', required_on: '', staffing_count: '', paper_statement_id: null as number | null })
  const [observationIds, setObservationIds] = useState<number[]>([])
  const [lines, setLines] = useState<LineDraft[]>([newLine()])
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const isA4 = form.request_type === 'a4_paper'

  useEffect(() => {
    if (!existing.data) {
      const dept = me?.permission_scopes['request.create']?.find((s) => s.scope_type === 'department')?.scope_id
      if (dept && !form.department_id) setForm((f) => ({ ...f, department_id: dept }))
      return
    }
    const r = existing.data
    setForm({ request_type: r.request_type, department_id: r.department_id, store_id: r.store_id, purpose: r.purpose ?? '', required_on: r.required_on?.slice(0, 10) ?? '', staffing_count: r.staffing_count ?? '', paper_statement_id: r.paper_statement_id })
    setObservationIds((r.meter_observations ?? []).map((o: Rec) => o.id))
    setLines(r.lines.map((l: Rec) => ({ key: crypto.randomUUID(), item: { ...l.item, base_unit_id: l.unit_id, purchase_unit_id: l.unit_id, issue_unit_id: l.unit_id } as ItemOption, unit_id: l.unit_id, qty: String(Number(l.requested_qty)), remarks: l.remarks ?? '' })))
  }, [existing.data]) // eslint-disable-line react-hooks/exhaustive-deps

  const observations = useQuery({
    queryKey: ['meter-observations', 'for-request'],
    queryFn: async () => (await getPage<Rec>('/meter-observations', listParams({ perPage: 50, sort: '-observed_at' }))).data,
    enabled: isA4,
  })
  const statements = useQuery({
    queryKey: ['paper-statements', form.department_id],
    queryFn: async () => (await getPage<Rec>('/paper-statements', listParams({ perPage: 50, filters: { department_id: form.department_id ?? undefined } }))).data,
    enabled: isA4 && Boolean(form.department_id),
  })

  const save = async (andSubmit: boolean) => {
    setBusy(true)
    setError(null)
    try {
      const body = {
        ...(id ? {} : { request_type: form.request_type, department_id: form.department_id }),
        store_id: form.store_id,
        purpose: form.purpose || null,
        required_on: form.required_on || null,
        staffing_count: form.staffing_count === '' ? null : Number(form.staffing_count),
        paper_statement_id: isA4 ? form.paper_statement_id : null,
        observation_ids: isA4 ? observationIds : undefined,
        lines: lines.map((l) => ({ item_id: l.item!.id, unit_id: l.unit_id, requested_qty: l.qty, remarks: l.remarks || null })),
      }
      const saved = await run<Rec>(id ? 'patch' : 'post', id ? `/requests/${id}` : '/requests', body, [['/requests']])
      if (andSubmit) await run('post', `/requests/${saved.public_id}/submit`, {}, [['/requests']])
      navigate(`/requests/${saved.public_id}`)
    } catch (e) {
      setError(e)
    } finally {
      setBusy(false)
    }
  }

  const valid = form.department_id && form.store_id && linesValid(lines)

  return (
    <>
      <PageHeader
        crumbs={[{ label: 'Requests', to: '/requests' }, { label: id ? `Revise ${existing.data?.request_no ?? ''}` : 'New request' }]}
        title={id ? 'Revise request' : 'New request'}
        subtitle={id && existing.data?.status === 'returned' ? 'This request was returned. Saving creates a new version and approval starts again.' : 'Save as draft or submit straight away for the department head to certify.'}
      />
      <Stack spacing={2.5}>
        <SectionCard title="Request" icon={<AssignmentOutlinedIcon />}>
          <Grid container spacing={2}>
            <Grid size={{ xs: 12, md: 3 }}>
              <TextField select label="Type" value={form.request_type} onChange={(e) => { setForm({ ...form, request_type: e.target.value }); setLines([newLine()]) }} disabled={Boolean(id)}>
                <MenuItem value="goods">Goods / stationery</MenuItem>
                <MenuItem value="a4_paper">A4 paper</MenuItem>
              </TextField>
            </Grid>
            <Grid size={{ xs: 12, md: 5 }}>
              <LookupField endpoint="/departments" label="Department" value={form.department_id} onChange={(v) => setForm({ ...form, department_id: v })} optionLabel={labels.coded} required disabled={Boolean(id)} />
            </Grid>
            <Grid size={{ xs: 12, md: 4 }}>
              <LookupField endpoint="/stores" label="Supplying store" value={form.store_id} onChange={(v) => setForm({ ...form, store_id: v })} optionLabel={labels.coded} required />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}><TextField label="Purpose" value={form.purpose} onChange={(e) => setForm({ ...form, purpose: e.target.value })} /></Grid>
            <Grid size={{ xs: 6, md: 3 }}><TextField label="Required by" type="date" value={form.required_on} onChange={(e) => setForm({ ...form, required_on: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} /></Grid>
            <Grid size={{ xs: 6, md: 3 }}><TextField label="Staff in the unit" type="number" value={form.staffing_count} onChange={(e) => setForm({ ...form, staffing_count: e.target.value })} /></Grid>
          </Grid>
        </SectionCard>

        {isA4 && (
          <SectionCard title="A4 evidence" icon={<FactCheckOutlinedIcon />} tone="secondary">
            <Alert severity="info" sx={{ mb: 2 }}>An A4 request needs the department's paper balance statement and recent meter readings — or an approved A4 exception (raise one after saving).</Alert>
            <Grid container spacing={2}>
              <Grid size={{ xs: 12, md: 6 }}>
                <TextField select label="Paper statement" value={form.paper_statement_id ?? ''} onChange={(e) => setForm({ ...form, paper_statement_id: e.target.value ? Number(e.target.value) : null })}>
                  <MenuItem value=""><em>None</em></MenuItem>
                  {(statements.data ?? []).map((s) => <MenuItem key={s.id} value={s.id}>{formatDate(s.period_from_on)} – {formatDate(s.period_to_on)} · closing {Number(s.counted_closing)}</MenuItem>)}
                </TextField>
              </Grid>
              <Grid size={{ xs: 12, md: 6 }}>
                <TextField select label="Meter readings" value={observationIds} onChange={(e) => setObservationIds(e.target.value as unknown as number[])} slotProps={{ select: { multiple: true } }}>
                  {(observations.data ?? []).map((o) => <MenuItem key={o.id} value={o.id}>{o.machine?.code} · {formatDateTime(o.observed_at)} · {humanize(o.status)}</MenuItem>)}
                </TextField>
              </Grid>
            </Grid>
          </SectionCard>
        )}

        <SectionCard title="Items" icon={<OutboxOutlinedIcon />}>
          <LineItemsEditor lines={lines} onChange={setLines} qtyLabel="Requested" withRemarks itemFilters={isA4 ? { is_paper: true } : undefined} />
        </SectionCard>

        {error !== null && <Alert severity="error">{errorMessage(error)}</Alert>}
        <Stack direction="row" spacing={1} sx={{ justifyContent: 'flex-end' }}>
          <Button onClick={() => navigate(-1)}>Cancel</Button>
          <Button variant="outlined" disabled={busy || !valid} onClick={() => save(false)}>Save draft</Button>
          <Button variant="contained" size="large" disabled={busy || !valid} onClick={() => save(true)}>{busy ? 'Working…' : 'Save & submit'}</Button>
        </Stack>
      </Stack>
    </>
  )
}

export function RequestDetailPage() {
  const { id } = useParams()
  const { can } = useAuth()
  const run = useRun()
  const navigate = useNavigate()
  const req = useDoc<Rec>(['/requests', id], `/requests/${id}`)
  const [dialog, setDialog] = useState<string | null>(null)
  const [approveQty, setApproveQty] = useState<Record<number, string>>({})
  const [issueQty, setIssueQty] = useState<Record<number, string>>({})
  const [issueAssets, setIssueAssets] = useState<Record<number, number[]>>({})

  const assetLines = (req.data?.lines ?? []).filter((l: Rec) => l.item?.tracking_method === 'asset')
  const assets = useQuery({
    queryKey: ['assets', 'for-issue', req.data?.store_id, assetLines.map((l: Rec) => l.item_id).join()],
    queryFn: async () => (await getPage<Rec>('/assets', listParams({ perPage: 100, filters: { operational_status: 'in_store', store_id: req.data!.store_id } }))).data,
    enabled: dialog === 'issue' && assetLines.length > 0,
  })

  useEffect(() => {
    if (req.data) {
      setApproveQty(Object.fromEntries(req.data.lines.map((l: Rec) => [l.id, String(Number(l.requested_qty))])))
      setIssueQty(Object.fromEntries(req.data.lines.map((l: Rec) => [l.id, String(Math.min(Number(l.outstanding_qty), Math.max(0, Number(l.available_in_store))))])))
    }
  }, [req.data])

  if (req.isLoading) return <Skeleton height={300} />
  if (req.isError || !req.data) return <Alert severity="error">{errorMessage(req.error)}</Alert>
  const r = req.data
  const inv = [['/requests'], ['/requests', id], ['/issues'], ['/stock/balances']]
  const reduced = r.lines.some((l: Rec) => Number(approveQty[l.id]) < Number(l.requested_qty))
  const step = r.current_step?.step_no

  const specs: Record<string, ActionSpec> = {
    submit: { title: 'Submit request', description: 'Sends the request to the department head for certification. Stock is not touched.', confirmLabel: 'Submit', run: () => run('post', `/requests/${r.public_id}/submit`, {}, inv) },
    certify: { title: 'Certify request', description: 'Confirms the request is genuine and needed by the department. You cannot certify a request you submitted.', remarks: 'optional', confirmLabel: 'Certify', run: (x) => run('post', `/requests/${r.public_id}/certify`, { remarks: x || null }, inv) },
    approve: {
      title: 'Approve request',
      description: 'Set the approved quantity for each line. Reducing a quantity needs remarks. Approval reserves available stock.',
      remarks: reduced ? 'required' : 'optional',
      confirmLabel: reduced ? 'Approve reduced quantities' : 'Approve',
      body: (
        <Table size="small">
          <TableHead><TableRow><TableCell>Item</TableCell><TableCell align="right">Requested</TableCell><TableCell align="right">Available</TableCell><TableCell width={140}>Approve</TableCell></TableRow></TableHead>
          <TableBody>
            {r.lines.map((l: Rec) => (
              <TableRow key={l.id}>
                <TableCell>{l.item?.name_en}</TableCell>
                <TableCell align="right"><Qty value={l.requested_qty} unit={l.unit?.code} /></TableCell>
                <TableCell align="right"><Qty value={l.available_in_store} unit={l.base_unit} /></TableCell>
                <TableCell><TextField type="number" value={approveQty[l.id] ?? ''} onChange={(e) => setApproveQty({ ...approveQty, [l.id]: e.target.value })} /></TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      ),
      run: (x) => run('post', `/requests/${r.public_id}/approve`, { remarks: x || null, lines: r.lines.map((l: Rec) => ({ id: l.id, approved_qty: approveQty[l.id] || 0 })) }, inv),
    },
    return: { title: 'Return for clarification', description: 'The department can revise and resubmit as a new version.', remarks: 'required', confirmLabel: 'Return', run: (x) => run('post', `/requests/${r.public_id}/return`, { remarks: x }, inv) },
    reject: { title: 'Reject request', remarks: 'required', danger: true, confirmLabel: 'Reject', run: (x) => run('post', `/requests/${r.public_id}/reject`, { remarks: x }, inv) },
    cancel: { title: 'Cancel request', description: 'Releases any reserved stock.', remarks: 'required', danger: true, confirmLabel: 'Cancel request', run: (x) => run('post', `/requests/${r.public_id}/cancel`, { reason: x }, inv) },
    issue: {
      title: 'Prepare an issue',
      description: 'Choose what to hand over now. Saving prepares the issue; posting it (next step) deducts stock. Asset-tracked items need a specific asset per unit.',
      confirmLabel: 'Prepare issue',
      body: (
        <Table size="small">
          <TableHead><TableRow><TableCell>Item</TableCell><TableCell align="right">Outstanding</TableCell><TableCell align="right">In store</TableCell><TableCell width={220}>Issue now</TableCell></TableRow></TableHead>
          <TableBody>
            {r.lines.filter((l: Rec) => Number(l.outstanding_qty) > 0).map((l: Rec) => (
              <TableRow key={l.id}>
                <TableCell>{l.item?.name_en}</TableCell>
                <TableCell align="right"><Qty value={l.outstanding_qty} unit={l.unit?.code} /></TableCell>
                <TableCell align="right"><Qty value={l.available_in_store} unit={l.base_unit} /></TableCell>
                <TableCell>
                  {l.item?.tracking_method === 'asset' ? (
                    <TextField select label="Assets" value={issueAssets[l.id] ?? []} onChange={(e) => setIssueAssets({ ...issueAssets, [l.id]: e.target.value as unknown as number[] })} slotProps={{ select: { multiple: true } }}>
                      {(assets.data ?? []).filter((a) => a.item_id === l.item_id).map((a) => <MenuItem key={a.id} value={a.id}>{a.asset_no} {a.serial_no ? `· ${a.serial_no}` : ''}</MenuItem>)}
                    </TextField>
                  ) : (
                    <TextField type="number" value={issueQty[l.id] ?? ''} onChange={(e) => setIssueQty({ ...issueQty, [l.id]: e.target.value })} />
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      ),
      run: async () => {
        const lines = r.lines.flatMap((l: Rec) =>
          l.item?.tracking_method === 'asset'
            ? (issueAssets[l.id] ?? []).map((assetId) => ({ request_line_id: l.id, qty: 1, asset_id: assetId }))
            : Number(issueQty[l.id]) > 0 ? [{ request_line_id: l.id, qty: issueQty[l.id] }] : [],
        )
        const issue = await run<Rec>('post', '/issues', { request_id: r.public_id, lines }, inv)
        navigate(`/issues/${issue.public_id}`)
      },
    },
  }

  const canEdit = ['draft', 'returned'].includes(r.status) && can('request.create')

  return (
    <>
      <PageHeader
        crumbs={[{ label: 'Requests', to: '/requests' }, { label: r.request_no }]}
        title={
          <Stack direction="row" spacing={1.5} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
            <span>{r.request_no}</span>
            <StatusChip value={r.status} />
            {r.request_type === 'a4_paper' && <Chip label="A4 paper" color="secondary" />}
            {r.current_version_no > 1 && <Chip label={`Version ${r.current_version_no}`} />}
          </Stack>
        }
        subtitle={`${r.department_name_snapshot} → ${r.store?.name}`}
        actions={
          <>
            {canEdit && <Button variant="outlined" onClick={() => navigate(`/requests/${r.public_id}/edit`)}>{r.status === 'returned' ? 'Revise' : 'Edit'}</Button>}
            {canEdit && can('request.submit') && <Button variant="contained" onClick={() => setDialog('submit')}>Submit</Button>}
            {r.status === 'submitted' && can('request.certify') && <Button variant="contained" onClick={() => setDialog('certify')}>Certify</Button>}
            {r.status === 'under_review' && can('request.approve') && <Button variant="contained" color="success" onClick={() => setDialog('approve')}>Approve</Button>}
            {['submitted', 'under_review'].includes(r.status) && can('request.return') && <Button variant="outlined" onClick={() => setDialog('return')}>Return</Button>}
            {['submitted', 'under_review'].includes(r.status) && can('request.reject') && <Button variant="outlined" color="error" onClick={() => setDialog('reject')}>Reject</Button>}
            {['approved', 'partly_issued'].includes(r.status) && can('issue.create') && <Button variant="contained" startIcon={<OutboxOutlinedIcon />} onClick={() => setDialog('issue')}>Issue goods</Button>}
            {['draft', 'submitted', 'under_review', 'returned', 'approved'].includes(r.status) && can('request.cancel') && <Button color="error" onClick={() => setDialog('cancel')}>Cancel</Button>}
            <Button variant="text" startIcon={<PrintOutlinedIcon />} onClick={() => window.print()}>Print</Button>
          </>
        }
      />

      <Grid container spacing={2.5}>
        <Grid size={{ xs: 12, lg: 8 }}>
          <Stack spacing={2.5}>
            <SectionCard>
              <InfoGrid columns={4} items={[
                { label: 'Submitted by', value: `${r.submitted_by?.full_name ?? ''}${r.submitted_designation_snapshot ? ` (${r.submitted_designation_snapshot})` : ''}` },
                { label: 'Submitted', value: r.submitted_at ? formatDateTime(r.submitted_at) : 'Not yet' },
                { label: 'Certified by', value: r.certified_by?.full_name },
                { label: 'Required by', value: formatDate(r.required_on) },
                { label: 'Purpose', value: r.purpose, wide: true },
                ...(r.request_type === 'a4_paper' ? [
                  { label: 'Paper statement', value: r.paper_statement ? `${formatDate(r.paper_statement.period_from_on)} – ${formatDate(r.paper_statement.period_to_on)} · closing ${Number(r.paper_statement.counted_closing)}` : 'Missing' },
                  { label: 'Meter readings', value: (r.meter_observations ?? []).map((o: Rec) => `${o.machine?.code} ${formatDate(o.observed_at)}`).join(', ') || 'None' },
                  { label: 'A4 exceptions', value: (r.a4_exceptions ?? []).map((e: Rec) => `${humanize(e.exception_type)} (${e.status})`).join(', ') || 'None' },
                ] : []),
              ]} />
            </SectionCard>

            <SectionCard title="Lines" icon={<AssignmentOutlinedIcon />} noPadding>
              <Box sx={{ overflowX: 'auto' }}>
                <Table>
                  <TableHead>
                    <TableRow><TableCell>#</TableCell><TableCell>Item</TableCell><TableCell align="right">Requested</TableCell><TableCell align="right">Approved</TableCell><TableCell align="right">Issued</TableCell><TableCell align="right">Outstanding</TableCell><TableCell align="right">In store</TableCell></TableRow>
                  </TableHead>
                  <TableBody>
                    {r.lines.map((l: Rec) => (
                      <TableRow key={l.id}>
                        <TableCell>{l.line_no}</TableCell>
                        <TableCell>
                          <CodeText>{l.item?.item_code}</CodeText> {l.item?.name_en}
                          {l.remarks && <Typography sx={{ fontSize: 12, color: 'text.secondary' }}>{l.remarks}</Typography>}
                        </TableCell>
                        <TableCell align="right"><Qty value={l.requested_qty} unit={l.unit?.code} /></TableCell>
                        <TableCell align="right">{l.approved_qty === null ? '—' : <Qty value={l.approved_qty} />}</TableCell>
                        <TableCell align="right"><Qty value={l.issued_qty} /></TableCell>
                        <TableCell align="right"><Qty value={l.outstanding_qty} /></TableCell>
                        <TableCell align="right"><Qty value={l.available_in_store} unit={l.base_unit} /></TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </Box>
            </SectionCard>

            {r.issues?.length > 0 && (
              <SectionCard title="Issues" icon={<OutboxOutlinedIcon />} tone="success">
                <Stack spacing={1}>
                  {r.issues.map((i: Rec) => (
                    <Stack key={i.id} direction="row" onClick={() => navigate(`/issues/${i.public_id}`)} sx={{ alignItems: 'center', gap: 2, p: 1.5, borderRadius: 3, border: 1, borderColor: 'divider', cursor: 'pointer', '&:hover': { bgcolor: 'action.hover' } }}>
                      <CodeText>{i.issue_no}</CodeText>
                      <Typography sx={{ flex: 1, fontSize: 14 }}>{formatDate(i.issue_on)}</Typography>
                      <StatusChip value={i.status} />
                    </Stack>
                  ))}
                </Stack>
              </SectionCard>
            )}
          </Stack>
        </Grid>
        <Grid size={{ xs: 12, lg: 4 }}>
          <SectionCard title="Approval trail" icon={<FactCheckOutlinedIcon />} tone="success" action={step && <Chip label={`Now: ${r.current_step.name}`} color="primary" variant="outlined" />}>
            <ApprovalTimeline steps={r.approvals} />
          </SectionCard>
        </Grid>
      </Grid>
      <ActionDialog action={dialog ? specs[dialog] : null} onClose={() => setDialog(null)} />
    </>
  )
}
