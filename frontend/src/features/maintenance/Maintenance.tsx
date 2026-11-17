import BuildOutlinedIcon from '@mui/icons-material/BuildOutlined'
import FactCheckOutlinedIcon from '@mui/icons-material/FactCheckOutlined'
import TimelineOutlinedIcon from '@mui/icons-material/TimelineOutlined'
import { Alert, Box, Button, Chip, Grid, MenuItem, Skeleton, Stack, Table, TableBody, TableCell, TableHead, TableRow, TextField, Typography } from '@mui/material'
import type { GridColDef } from '@mui/x-data-grid'
import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { getPage, listParams } from '../../api/client'
import { useAuth } from '../../auth/AuthContext'
import { ActionDialog, type ActionSpec } from '../../components/ActionDialog'
import { ApprovalTimeline } from '../../components/ApprovalTimeline'
import { ListPage } from '../../components/ListPage'
import { errorMessage } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { LookupField, labels } from '../../components/Pickers'
import { StatusChip } from '../../components/StatusChip'
import { CodeText, InfoGrid, Money, SectionCard } from '../../components/ui'
import { useDoc, useRun } from '../../hooks/useApi'
import { formatDate, formatDateTime, humanize } from '../../utils/format'
import { AssetStatus } from '../assets/Assets'

type Rec = Record<string, any> // eslint-disable-line @typescript-eslint/no-explicit-any

const assetCol: GridColDef<Rec> = { field: 'asset', headerName: 'Asset', flex: 1, minWidth: 220, sortable: false, valueGetter: (_, r) => `${r.asset?.asset_no} · ${r.asset?.item?.name_en ?? ''}` }

/** Picks an asset by searching the register. */
function AssetSelect({ value, onChange, filters }: { value: string | null; onChange: (publicId: string) => void; filters?: Record<string, string> }) {
  const [search, setSearch] = useState('')
  const assets = useQuery({
    queryKey: ['asset-select', search, filters],
    queryFn: async () => (await getPage<Rec>('/assets', listParams({ perPage: 30, search, filters }))).data,
  })

  return (
    <Stack spacing={1}>
      <TextField label="Search asset (number, serial, model)" value={search} onChange={(e) => setSearch(e.target.value)} />
      <TextField select label="Asset" value={value ?? ''} onChange={(e) => onChange(e.target.value)}>
        {(assets.data ?? []).map((a) => <MenuItem key={a.public_id} value={a.public_id}>{a.asset_no} · {a.item?.name_en} {a.serial_no ? `· ${a.serial_no}` : ''}</MenuItem>)}
      </TextField>
    </Stack>
  )
}

// ---- Repairs ----------------------------------------------------------------------

export function RepairsPage() {
  const { can } = useAuth()
  const run = useRun()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)
  const [asset, setAsset] = useState<string | null>(null)

  const columns: GridColDef<Rec>[] = [
    { field: 'case_no', headerName: 'Case', width: 150, renderCell: ({ value }) => <CodeText>{value}</CodeText> },
    assetCol,
    { field: 'fault_description', headerName: 'Fault', flex: 1, minWidth: 220 },
    { field: 'under_warranty', headerName: 'Warranty', width: 110, renderCell: ({ value }) => (value ? <Chip label="under warranty" color="success" variant="outlined" /> : '—') },
    { field: 'provider', headerName: 'Repairer', width: 170, sortable: false, valueGetter: (_, r) => r.provider?.name ?? '—' },
    { field: 'status', headerName: 'Status', width: 150, renderCell: ({ value }) => <StatusChip value={value === 'closed' ? 'active' : value === 'test_failed' ? 'deny' : 'pending'} label={humanize(value)} /> },
  ]

  return (
    <>
      <ListPage<Rec>
        title="Repairs"
        subtitle="Repair changes who physically holds an asset, not which department is accountable. A repair never restarts the warranty, and a case closes only after the returned asset passes its test."
        endpoint="/repair-cases"
        queryKey={['/repair-cases']}
        columns={columns}
        sortFields={['case_no']}
        defaultSort={[{ field: 'case_no', sort: 'desc' }]}
        filters={[{ name: 'open', label: 'Show', options: [{ value: 'true', label: 'Open cases' }] }, { name: 'status', label: 'Status', options: ['reported', 'awaiting_approval', 'approved', 'in_repair', 'testing', 'test_failed', 'returned', 'closed'] }]}
        searchPlaceholder="Case no. or fault"
        rowLink={(r) => `/maintenance/repairs/${r.public_id}`}
        createLabel="Report fault"
        onCreate={can('repair.create') ? () => setOpen(true) : undefined}
      />
      <ActionDialog
        action={open ? { title: 'Report a fault', remarks: 'required', remarksLabel: 'Describe the fault', confirmLabel: 'Open case', disabled: !asset, body: <AssetSelect value={asset} onChange={setAsset} />, run: async (r) => { const c = await run<Rec>('post', '/repair-cases', { asset_id: asset, fault_description: r }, [['/repair-cases']]); navigate(`/maintenance/repairs/${c.public_id}`) } } : null}
        onClose={() => setOpen(false)}
      />
    </>
  )
}

export function RepairDetailPage() {
  const { id } = useParams()
  const { can } = useAuth()
  const run = useRun()
  const navigate = useNavigate()
  const doc = useDoc<Rec>(['/repair-cases', id], `/repair-cases/${id}`)
  const [dialog, setDialog] = useState<string | null>(null)
  const [assess, setAssess] = useState({ provider_supplier_id: null as number | null, quotation_amount: '', expected_return_on: '' })
  const [update, setUpdate] = useState({ cost: '', part_description: '' })
  const [approvalRef, setApprovalRef] = useState('')

  if (doc.isLoading) return <Skeleton height={300} />
  if (doc.isError || !doc.data) return <Alert severity="error">{errorMessage(doc.error)}</Alert>
  const c = doc.data
  const inv = [['/repair-cases'], ['/repair-cases', id], ['/assets']]
  const base = `/repair-cases/${c.public_id}`

  const specs: Record<string, ActionSpec> = {
    assess: {
      title: 'Assess the fault',
      description: c.under_warranty ? 'Under warranty: no expenditure approval is needed.' : 'A quotation above zero sends the case for expenditure approval.',
      remarks: 'required', remarksLabel: 'Assessment',
      confirmLabel: 'Save assessment',
      body: (
        <Grid container spacing={2}>
          <Grid size={12}><LookupField endpoint="/suppliers" label="Repairer" value={assess.provider_supplier_id} onChange={(v) => setAssess({ ...assess, provider_supplier_id: v })} optionLabel={labels.coded} filters={{ supplier_type: 'service,both' }} /></Grid>
          <Grid size={6}><TextField label="Quotation (Rs.)" type="number" value={assess.quotation_amount} onChange={(e) => setAssess({ ...assess, quotation_amount: e.target.value })} disabled={c.under_warranty} /></Grid>
          <Grid size={6}><TextField label="Expected back" type="date" value={assess.expected_return_on} onChange={(e) => setAssess({ ...assess, expected_return_on: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} /></Grid>
        </Grid>
      ),
      run: (r) => run('post', `${base}/assess`, { assessment: r, provider_supplier_id: assess.provider_supplier_id, quotation_amount: assess.quotation_amount || null, expected_return_on: assess.expected_return_on || null }, inv),
    },
    approve: { title: 'Approve repair expenditure', description: `Quotation ${c.quotation_amount ? 'Rs. ' + Number(c.quotation_amount).toLocaleString() : ''}`, body: <TextField label="Expenditure approval reference" value={approvalRef} onChange={(e) => setApprovalRef(e.target.value)} />, remarks: 'optional', confirmLabel: 'Approve', run: (r) => run('post', `${base}/approve`, { remarks: r || null, expenditure_approval_ref: approvalRef || null }, inv) },
    dispatch: { title: 'Send for repair', description: 'The asset becomes Under Repair; the responsible department does not change.', remarks: 'optional', remarksLabel: 'Accessories sent', confirmLabel: 'Dispatch', run: (r) => run('post', `${base}/dispatch`, { accessories_sent: r || null }, inv) },
    update: {
      title: 'Add progress update',
      remarks: 'required', remarksLabel: 'Note',
      body: <Grid container spacing={2}><Grid size={8}><TextField label="Part replaced" value={update.part_description} onChange={(e) => setUpdate({ ...update, part_description: e.target.value })} /></Grid><Grid size={4}><TextField label="Cost (Rs.)" type="number" value={update.cost} onChange={(e) => setUpdate({ ...update, cost: e.target.value })} /></Grid></Grid>,
      confirmLabel: 'Add',
      run: (r) => run('post', `${base}/updates`, { note: r, part_description: update.part_description || null, cost: update.cost || null }, inv),
    },
    return: { title: 'Back from repair', description: 'Records the return; the asset must then be tested.', confirmLabel: 'Record return', run: () => run('post', `${base}/return`, {}, inv) },
    pass: { title: 'Test passed', remarks: 'optional', confirmLabel: 'Passed', run: (r) => run('post', `${base}/test`, { passed: true, remarks: r || null }, inv) },
    fail: { title: 'Test failed', description: 'Send it back to the repairer and record the return again when it comes back.', remarks: 'required', danger: true, confirmLabel: 'Failed', run: (r) => run('post', `${base}/test`, { passed: false, remarks: r }, inv) },
    close: { title: 'Close repair case', description: c.test_result === 'passed' ? 'The asset returns to service.' : 'Close without repair (e.g. no fault found).', remarks: 'optional', remarksLabel: 'Outcome', confirmLabel: 'Close case', run: (r) => run('post', `${base}/close`, { outcome: r || null }, inv) },
  }

  return (
    <>
      <PageHeader
        crumbs={[{ label: 'Repairs', to: '/maintenance/repairs' }, { label: c.case_no }]}
        title={<Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}><span>{c.case_no}</span><StatusChip value={c.status === 'closed' ? 'active' : 'pending'} label={humanize(c.status)} />{c.under_warranty && <Chip label="Under warranty" color="success" />}</Stack>}
        subtitle={`${c.asset?.asset_no} · ${c.asset?.item?.name_en ?? ''}`}
        actions={
          <>
            {['reported', 'assessed'].includes(c.status) && can('repair.update') && <Button variant="contained" onClick={() => setDialog('assess')}>Assess</Button>}
            {c.status === 'awaiting_approval' && can('repair.approve') && <Button variant="contained" color="success" onClick={() => setDialog('approve')}>Approve expenditure</Button>}
            {c.status === 'approved' && can('repair.dispatch') && <Button variant="contained" onClick={() => setDialog('dispatch')}>Send for repair</Button>}
            {['in_repair', 'test_failed'].includes(c.status) && can('repair.update') && <Button variant="contained" onClick={() => setDialog('return')}>Back from repair</Button>}
            {c.status === 'testing' && can('repair.update') && <><Button variant="contained" color="success" onClick={() => setDialog('pass')}>Test passed</Button><Button variant="outlined" color="error" onClick={() => setDialog('fail')}>Test failed</Button></>}
            {(c.status === 'returned' || ['reported', 'assessed'].includes(c.status)) && can('repair.close') && <Button variant="outlined" onClick={() => setDialog('close')}>Close case</Button>}
            {c.status !== 'closed' && can('repair.update') && <Button onClick={() => setDialog('update')}>Add update</Button>}
            <Button onClick={() => navigate(`/assets/${c.asset?.public_id}`)}>Open asset</Button>
          </>
        }
      />
      <Grid container spacing={2.5}>
        <Grid size={{ xs: 12, lg: 8 }}>
          <Stack spacing={2.5}>
            <SectionCard title="Case" icon={<BuildOutlinedIcon />}>
              <InfoGrid columns={3} items={[
                { label: 'Fault', value: c.fault_description, wide: true },
                { label: 'Assessment', value: c.assessment, wide: true },
                { label: 'Asset status', value: <AssetStatus value={c.asset?.operational_status} /> },
                { label: 'Reported by', value: c.reported_by?.full_name },
                { label: 'Repairer', value: c.provider?.name },
                { label: 'Quotation', value: <Money value={c.quotation_amount} /> },
                { label: 'Total cost', value: <Money value={c.total_cost} /> },
                { label: 'Expected back', value: formatDate(c.expected_return_on) },
                { label: 'Dispatched', value: formatDateTime(c.dispatched_at) },
                { label: 'Returned', value: formatDateTime(c.returned_at) },
                { label: 'Test', value: c.test_result ? humanize(c.test_result) : '—' },
              ]} />
            </SectionCard>
            <SectionCard title="Progress" icon={<TimelineOutlinedIcon />} tone="info">
              <Stack spacing={1.25}>
                {(c.updates ?? []).map((u: Rec) => (
                  <Stack key={u.id} direction="row" spacing={1.5} sx={{ p: 1.25, borderRadius: 3, border: 1, borderColor: 'divider' }}>
                    <Box sx={{ minWidth: 150, fontSize: 12, color: 'text.secondary' }}>{formatDateTime(u.event_at)}</Box>
                    <Box sx={{ flex: 1 }}>
                      <Typography sx={{ fontSize: 14 }}>{u.note}</Typography>
                      <Typography sx={{ fontSize: 12, color: 'text.secondary' }}>{[u.officer?.full_name, u.update_source === 'provider' && 'from repairer', u.part_description, u.cost && `Rs. ${Number(u.cost).toLocaleString()}`].filter(Boolean).join(' · ')}</Typography>
                    </Box>
                  </Stack>
                ))}
              </Stack>
            </SectionCard>
          </Stack>
        </Grid>
        <Grid size={{ xs: 12, lg: 4 }}>
          <SectionCard title="Expenditure approval" icon={<FactCheckOutlinedIcon />} tone="success"><ApprovalTimeline steps={c.approvals} /></SectionCard>
        </Grid>
      </Grid>
      <ActionDialog action={dialog ? specs[dialog] : null} onClose={() => setDialog(null)} />
    </>
  )
}

// ---- Warranties --------------------------------------------------------------------

export function WarrantiesPage() {
  const { can } = useAuth()
  const run = useRun()
  const [extending, setExtending] = useState<Rec | null>(null)
  const [endsOn, setEndsOn] = useState('')

  const columns: GridColDef<Rec>[] = [
    assetCol,
    { field: 'starts_on', headerName: 'From', width: 120, valueFormatter: (v) => formatDate(v) },
    { field: 'ends_on', headerName: 'Until', width: 120, valueFormatter: (v) => formatDate(v) },
    { field: 'provider', headerName: 'Provider', width: 200, sortable: false, valueGetter: (_, r) => r.provider?.name ?? '—' },
    { field: 'coverage_type', headerName: 'Coverage', width: 200 },
    { field: 'extends_warranty_id', headerName: 'Kind', width: 110, renderCell: ({ value }) => (value ? <Chip label="extension" color="info" variant="outlined" /> : 'original') },
    { field: 'status', headerName: 'Status', width: 110, renderCell: ({ value }) => <StatusChip value={value === 'active' ? 'active' : 'expired'} label={humanize(value)} /> },
    { field: 'actions', headerName: '', width: 110, sortable: false, renderCell: ({ row }) => can('warranty.manage') && <Button size="small" onClick={() => { setEndsOn(''); setExtending(row) }}>Extend</Button> },
  ]

  return (
    <>
      <ListPage<Rec>
        title="Warranties"
        subtitle="An extension is recorded as a new linked warranty — the original coverage is kept as it was. Add warranties from the asset page."
        endpoint="/warranties"
        queryKey={['/warranties']}
        columns={columns}
        sortFields={['ends_on', 'starts_on']}
        defaultSort={[{ field: 'ends_on', sort: 'asc' }]}
        filters={[{ name: 'expiring', label: 'Show', options: [{ value: 'true', label: 'Ending within 60 days' }] }, { name: 'status', label: 'Status', options: ['active', 'expired', 'void'] }]}
      />
      <ActionDialog
        action={extending && { title: 'Extend warranty', description: `Current coverage ends ${formatDate(extending.ends_on)}. The extension starts the day after.`, body: <TextField label="New end date" type="date" value={endsOn} onChange={(e) => setEndsOn(e.target.value)} slotProps={{ inputLabel: { shrink: true } }} />, disabled: !endsOn, confirmLabel: 'Record extension', run: () => run('post', `/warranties/${extending.id}/extend`, { ends_on: endsOn }, [['/warranties']]) }}
        onClose={() => setExtending(null)}
      />
    </>
  )
}

// ---- Servicing -------------------------------------------------------------------------

export function ServicingPage() {
  const { can } = useAuth()
  const run = useRun()
  const [open, setOpen] = useState(false)
  const [recording, setRecording] = useState<Rec | null>(null)
  const [form, setForm] = useState({ asset: null as string | null, service_type: '', interval_days: '90', last_service_on: '' })
  const [servicedOn, setServicedOn] = useState(new Date().toISOString().slice(0, 10))

  const columns: GridColDef<Rec>[] = [
    assetCol,
    { field: 'service_type', headerName: 'Service', width: 200 },
    { field: 'interval_days', headerName: 'Every (days)', width: 120, type: 'number' },
    { field: 'last_service_on', headerName: 'Last done', width: 120, valueFormatter: (v) => formatDate(v) },
    { field: 'next_due_on', headerName: 'Next due', width: 140, renderCell: ({ value }) => <Chip label={formatDate(value)} color={value && new Date(value) < new Date() ? 'error' : 'default'} variant="outlined" /> },
    { field: 'responsible_officer', headerName: 'Responsible', width: 170, sortable: false, valueGetter: (_, r) => r.responsible_officer?.full_name ?? '—' },
    { field: 'actions', headerName: '', width: 140, sortable: false, renderCell: ({ row }) => can('service.manage') && <Button size="small" variant="outlined" onClick={() => setRecording(row)}>Record service</Button> },
  ]

  return (
    <>
      <ListPage<Rec>
        title="Servicing schedules"
        subtitle="Preventive maintenance by interval. Recording a service moves the next due date forward."
        endpoint="/service-schedules"
        queryKey={['/service-schedules']}
        columns={columns}
        sortFields={['next_due_on']}
        defaultSort={[{ field: 'next_due_on', sort: 'asc' }]}
        filters={[{ name: 'due', label: 'Show', options: [{ value: 'true', label: 'Due within 14 days' }] }]}
        createLabel="New schedule"
        onCreate={can('service.manage') ? () => setOpen(true) : undefined}
      />
      <ActionDialog
        action={open ? {
          title: 'New service schedule',
          confirmLabel: 'Save',
          disabled: !form.asset || !form.service_type,
          body: (
            <Stack spacing={2}>
              <AssetSelect value={form.asset} onChange={(v) => setForm({ ...form, asset: v })} />
              <TextField label="Service" value={form.service_type} onChange={(e) => setForm({ ...form, service_type: e.target.value })} placeholder="e.g. Preventive maintenance" />
              <Stack direction="row" spacing={2}>
                <TextField label="Every (days)" type="number" value={form.interval_days} onChange={(e) => setForm({ ...form, interval_days: e.target.value })} />
                <TextField label="Last done" type="date" value={form.last_service_on} onChange={(e) => setForm({ ...form, last_service_on: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} />
              </Stack>
            </Stack>
          ),
          run: () => {
            return run('post', '/service-schedules', { asset_id: form.asset, service_type: form.service_type, interval_days: Number(form.interval_days) || null, last_service_on: form.last_service_on || null }, [['/service-schedules']])
          },
        } : null}
        onClose={() => setOpen(false)}
      />
      <ActionDialog
        action={recording && { title: 'Record service done', body: <TextField label="Serviced on" type="date" value={servicedOn} onChange={(e) => setServicedOn(e.target.value)} slotProps={{ inputLabel: { shrink: true } }} />, confirmLabel: 'Record', run: () => run('post', `/service-schedules/${recording.id}/record`, { serviced_on: servicedOn }, [['/service-schedules']]) }}
        onClose={() => setRecording(null)}
      />
    </>
  )
}

// ---- Verification ----------------------------------------------------------------------

export function VerificationPage() {
  const run = useRun()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)

  const columns: GridColDef<Rec>[] = [
    { field: 'session_no', headerName: 'Session', width: 150, renderCell: ({ value }) => <CodeText>{value}</CodeText> },
    { field: 'conducted_on', headerName: 'Date', width: 120, valueFormatter: (v) => formatDate(v) },
    { field: 'scope_note', headerName: 'Scope', flex: 1, minWidth: 240 },
    { field: 'lead_officer', headerName: 'Lead', width: 170, sortable: false, valueGetter: (_, r) => r.lead_officer?.full_name },
    { field: 'verifications_count', headerName: 'Checked', width: 100, type: 'number' },
    { field: 'status', headerName: 'Status', width: 110, renderCell: ({ value }) => <StatusChip value={value === 'open' ? 'pending' : 'active'} label={humanize(value)} /> },
  ]

  return (
    <>
      <ListPage<Rec>
        title="Asset verification"
        subtitle="Board of survey / physical verification: record where each asset was found, who held it and its condition. Missing assets are marked lost."
        endpoint="/asset-verifications"
        queryKey={['/asset-verifications']}
        columns={columns}
        sortFields={['conducted_on']}
        defaultSort={[{ field: 'conducted_on', sort: 'desc' }]}
        rowLink={(r) => `/maintenance/verification/${r.id}`}
        createLabel="New session"
        onCreate={() => setOpen(true)}
      />
      <ActionDialog
        action={open ? { title: 'Start verification session', remarks: 'optional', remarksLabel: 'Scope (e.g. 2nd floor, IT equipment)', confirmLabel: 'Start', run: async (r) => { const s = await run<Rec>('post', '/asset-verifications', { scope_note: r || null }, [['/asset-verifications']]); navigate(`/maintenance/verification/${s.id}`) } } : null}
        onClose={() => setOpen(false)}
      />
    </>
  )
}

export function VerificationDetailPage() {
  const { id } = useParams()
  const run = useRun()
  const doc = useDoc<Rec>(['/asset-verifications', id], `/asset-verifications/${id}`)
  const [dialog, setDialog] = useState<string | null>(null)
  const [form, setForm] = useState({ asset: null as string | null, found_location_id: null as number | null, condition_found: 'good', result: '' })

  if (doc.isLoading) return <Skeleton height={300} />
  if (doc.isError || !doc.data) return <Alert severity="error">{errorMessage(doc.error)}</Alert>
  const s = doc.data
  const inv = [['/asset-verifications'], ['/asset-verifications', id], ['/assets']]

  const specs: Record<string, ActionSpec> = {
    verify: {
      title: 'Record an asset',
      description: 'Leave the result empty to compare automatically with the register.',
      remarks: 'optional', remarksLabel: 'Follow-up',
      confirmLabel: 'Record',
      disabled: !form.asset,
      body: (
        <Stack spacing={2}>
          <AssetSelect value={form.asset} onChange={(v) => setForm({ ...form, asset: v })} />
          <LookupField endpoint="/locations" label="Found at" value={form.found_location_id} onChange={(v) => setForm({ ...form, found_location_id: v })} optionLabel={labels.coded} />
          <Stack direction="row" spacing={2}>
            <TextField select label="Condition" value={form.condition_found} onChange={(e) => setForm({ ...form, condition_found: e.target.value })}>
              {['new', 'good', 'fair', 'poor', 'damaged'].map((c) => <MenuItem key={c} value={c}>{humanize(c)}</MenuItem>)}
            </TextField>
            <TextField select label="Result" value={form.result} onChange={(e) => setForm({ ...form, result: e.target.value })}>
              <MenuItem value="">Compare automatically</MenuItem>
              {['match', 'mismatch', 'missing', 'found_unrecorded'].map((r) => <MenuItem key={r} value={r}>{humanize(r)}</MenuItem>)}
            </TextField>
          </Stack>
        </Stack>
      ),
      run: (r) => run('post', `/asset-verifications/${s.id}/verify`, { asset_id: form.asset, found_location_id: form.found_location_id, condition_found: form.condition_found, result: form.result || null, follow_up: r || null }, inv),
    },
    close: { title: 'Close session', confirmLabel: 'Close', run: () => run('post', `/asset-verifications/${s.id}/close`, {}, inv) },
  }

  return (
    <>
      <PageHeader
        crumbs={[{ label: 'Verification', to: '/maintenance/verification' }, { label: s.session_no }]}
        title={<Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}><span>{s.session_no}</span><StatusChip value={s.status === 'open' ? 'pending' : 'active'} label={humanize(s.status)} /></Stack>}
        subtitle={`${s.scope_note ?? ''} · led by ${s.lead_officer?.full_name ?? ''}`}
        actions={s.status === 'open' && <><Button variant="contained" onClick={() => { setForm({ asset: null, found_location_id: null, condition_found: 'good', result: '' }); setDialog('verify') }}>Record asset</Button><Button variant="outlined" onClick={() => setDialog('close')}>Close session</Button></>}
      />
      <SectionCard title={`Assets checked (${s.verifications?.length ?? 0})`} icon={<FactCheckOutlinedIcon />} noPadding>
        <Table>
          <TableHead><TableRow><TableCell>Asset</TableCell><TableCell>Found at</TableCell><TableCell>Condition</TableCell><TableCell>Result</TableCell><TableCell>When</TableCell><TableCell>Follow-up</TableCell></TableRow></TableHead>
          <TableBody>
            {(s.verifications ?? []).map((v: Rec) => (
              <TableRow key={v.id}>
                <TableCell><CodeText>{v.asset?.asset_no}</CodeText></TableCell>
                <TableCell>{v.found_location?.name ?? '—'}</TableCell>
                <TableCell>{humanize(v.condition_found ?? '—')}</TableCell>
                <TableCell><StatusChip value={v.result === 'match' ? 'active' : v.result === 'missing' ? 'deny' : 'pending'} label={humanize(v.result)} /></TableCell>
                <TableCell>{formatDateTime(v.verified_at)}</TableCell>
                <TableCell>{v.follow_up ?? ''}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </SectionCard>
      <ActionDialog action={dialog ? specs[dialog] : null} onClose={() => setDialog(null)} />
    </>
  )
}

// ---- Valuations & disposals ----------------------------------------------------------

export function ValuationsPage() {
  const { can } = useAuth()
  const run = useRun()
  const [open, setOpen] = useState(false)
  const [deciding, setDeciding] = useState<{ row: Rec; approve: boolean } | null>(null)
  const [form, setForm] = useState({ asset: null as string | null, new_value: '', effective_on: new Date().toISOString().slice(0, 10), decision_ref: '' })

  const columns: GridColDef<Rec>[] = [
    assetCol,
    { field: 'effective_on', headerName: 'Effective', width: 120, valueFormatter: (v) => formatDate(v) },
    { field: 'previous_value', headerName: 'Previous', width: 140, renderCell: ({ value }) => <Money value={value} /> },
    { field: 'new_value', headerName: 'New value', width: 150, renderCell: ({ value }) => <Money value={value} /> },
    { field: 'decision_ref', headerName: 'Decision ref.', width: 200 },
    { field: 'assessed_by', headerName: 'Assessed by', width: 160, sortable: false, valueGetter: (_, r) => r.assessed_by?.full_name },
    { field: 'status', headerName: 'Status', width: 120, renderCell: ({ value }) => <StatusChip value={value === 'approved' ? 'active' : value === 'rejected' ? 'deny' : 'pending'} label={humanize(value)} /> },
    {
      field: 'actions', headerName: '', width: 180, sortable: false,
      renderCell: ({ row }) => row.status === 'submitted' && can('valuation.approve') && <Stack direction="row" spacing={1}><Button size="small" variant="contained" color="success" onClick={() => setDeciding({ row, approve: true })}>Approve</Button><Button size="small" color="error" onClick={() => setDeciding({ row, approve: false })}>Reject</Button></Stack>,
    },
  ]

  return (
    <>
      <ListPage<Rec>
        title="Valuations"
        subtitle="A new valuation is a new record — earlier values are never overwritten. Costing and depreciation rules stay with Finance."
        endpoint="/valuations"
        queryKey={['/valuations']}
        columns={columns}
        sortFields={['effective_on']}
        defaultSort={[{ field: 'effective_on', sort: 'desc' }]}
        filters={[{ name: 'status', label: 'Status', options: ['submitted', 'approved', 'rejected'] }]}
        createLabel="New valuation"
        onCreate={can('valuation.manage') ? () => setOpen(true) : undefined}
      />
      <ActionDialog
        action={open ? {
          title: 'Propose a valuation', confirmLabel: 'Submit', disabled: !form.asset || !form.new_value || !form.decision_ref,
          body: <Stack spacing={2}><AssetSelect value={form.asset} onChange={(v) => setForm({ ...form, asset: v })} /><Stack direction="row" spacing={2}><TextField label="Value (Rs.)" type="number" value={form.new_value} onChange={(e) => setForm({ ...form, new_value: e.target.value })} /><TextField label="Effective" type="date" value={form.effective_on} onChange={(e) => setForm({ ...form, effective_on: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} /></Stack><TextField label="Decision / assessment reference" value={form.decision_ref} onChange={(e) => setForm({ ...form, decision_ref: e.target.value })} /></Stack>,
          run: () => run('post', '/valuations', { asset_id: form.asset, new_value: form.new_value, effective_on: form.effective_on, decision_ref: form.decision_ref }, [['/valuations']]),
        } : null}
        onClose={() => setOpen(false)}
      />
      <ActionDialog
        action={deciding && { title: deciding.approve ? 'Approve valuation' : 'Reject valuation', description: 'You cannot approve a valuation you assessed.', remarks: deciding.approve ? 'optional' : 'required', confirmLabel: deciding.approve ? 'Approve' : 'Reject', danger: !deciding.approve, run: (r) => run('post', `/valuations/${deciding.row.id}/decide`, { approve: deciding.approve, remarks: r || null }, [['/valuations']]) }}
        onClose={() => setDeciding(null)}
      />
    </>
  )
}

export function DisposalsPage() {
  const { can } = useAuth()
  const run = useRun()
  const [open, setOpen] = useState(false)
  const [acting, setActing] = useState<{ row: Rec; kind: 'approve' | 'reject' | 'complete' } | null>(null)
  const [form, setForm] = useState({ asset: null as string | null, method: 'auction', decision_ref: '' })

  const columns: GridColDef<Rec>[] = [
    assetCol,
    { field: 'method', headerName: 'Method', width: 130, valueGetter: (v) => humanize(String(v)) },
    { field: 'decision_ref', headerName: 'Decision ref.', width: 220 },
    { field: 'reason', headerName: 'Reason', flex: 1, minWidth: 200 },
    { field: 'disposed_on', headerName: 'Disposed', width: 120, valueFormatter: (v) => formatDate(v) },
    { field: 'status', headerName: 'Status', width: 120, renderCell: ({ value }) => <StatusChip value={value === 'completed' ? 'deactivated' : value === 'approved' ? 'active' : value === 'rejected' ? 'deny' : 'pending'} label={humanize(value)} /> },
    {
      field: 'actions', headerName: '', width: 200, sortable: false,
      renderCell: ({ row }) => (
        <Stack direction="row" spacing={1}>
          {row.status === 'proposed' && can('disposal.approve') && <><Button size="small" variant="contained" color="success" onClick={() => setActing({ row, kind: 'approve' })}>Approve</Button><Button size="small" color="error" onClick={() => setActing({ row, kind: 'reject' })}>Reject</Button></>}
          {row.status === 'approved' && can('disposal.manage') && <Button size="small" variant="outlined" onClick={() => setActing({ row, kind: 'complete' })}>Complete</Button>}
        </Stack>
      ),
    },
  ]

  return (
    <>
      <ListPage<Rec>
        title="Disposals"
        subtitle="Recording damage and formally retiring an asset are separate decisions. A disposal needs an approved decision (e.g. Board of Survey) before the asset is retired."
        endpoint="/disposals"
        queryKey={['/disposals']}
        columns={columns}
        sortFields={['created_at', 'disposed_on']}
        defaultSort={[{ field: 'created_at', sort: 'desc' }]}
        filters={[{ name: 'status', label: 'Status', options: ['proposed', 'approved', 'rejected', 'completed'] }]}
        createLabel="Propose disposal"
        onCreate={can('disposal.manage') ? () => setOpen(true) : undefined}
      />
      <ActionDialog
        action={open ? {
          title: 'Propose disposal', remarks: 'optional', remarksLabel: 'Reason', confirmLabel: 'Propose', disabled: !form.asset || !form.decision_ref,
          body: <Stack spacing={2}><AssetSelect value={form.asset} onChange={(v) => setForm({ ...form, asset: v })} /><TextField select label="Method" value={form.method} onChange={(e) => setForm({ ...form, method: e.target.value })}>{['auction', 'transfer_out', 'scrap', 'donation', 'write_off'].map((m) => <MenuItem key={m} value={m}>{humanize(m)}</MenuItem>)}</TextField><TextField label="Decision reference" value={form.decision_ref} onChange={(e) => setForm({ ...form, decision_ref: e.target.value })} /></Stack>,
          run: (r) => run('post', '/disposals', { asset_id: form.asset, method: form.method, decision_ref: form.decision_ref, reason: r || null }, [['/disposals']]),
        } : null}
        onClose={() => setOpen(false)}
      />
      <ActionDialog
        action={acting && (acting.kind === 'complete'
          ? { title: 'Complete disposal', description: 'The asset will be marked Disposed. Open transfers and repairs must be closed first.', remarks: 'optional', remarksLabel: 'Outcome', confirmLabel: 'Dispose', danger: true, run: (r) => run('post', `/disposals/${acting.row.id}/complete`, { outcome: r || null }, [['/disposals'], ['/assets']]) }
          : { title: acting.kind === 'approve' ? 'Approve disposal' : 'Reject disposal', remarks: acting.kind === 'approve' ? 'optional' : 'required', confirmLabel: acting.kind === 'approve' ? 'Approve' : 'Reject', danger: acting.kind === 'reject', run: (r) => run('post', `/disposals/${acting.row.id}/decide`, { approve: acting.kind === 'approve', remarks: r || null }, [['/disposals']]) })}
        onClose={() => setActing(null)}
      />
    </>
  )
}
