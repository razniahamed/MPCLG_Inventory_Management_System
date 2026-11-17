import PhotoCameraOutlinedIcon from '@mui/icons-material/PhotoCameraOutlined'
import PrintOutlinedIcon from '@mui/icons-material/PrintOutlined'
import SpeedOutlinedIcon from '@mui/icons-material/SpeedOutlined'
import { Alert, Box, Button, Checkbox, Chip, FormControlLabel, Grid, MenuItem, Skeleton, Stack, Switch, Table, TableBody, TableCell, TableHead, TableRow, TextField, Typography } from '@mui/material'
import type { GridColDef } from '@mui/x-data-grid'
import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { get, getPage, listParams } from '../../api/client'
import { useAuth } from '../../auth/AuthContext'
import { ActionDialog, type ActionSpec } from '../../components/ActionDialog'
import { ListPage } from '../../components/ListPage'
import { errorMessage } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { ItemPicker, LookupField, labels, type ItemOption } from '../../components/Pickers'
import { StatusChip } from '../../components/StatusChip'
import { CodeText, FlagChips, InfoGrid, Qty, SectionCard } from '../../components/ui'
import { useDoc, useRun } from '../../hooks/useApi'
import { useLookup } from '../../hooks/useLookup'
import { formatDate, formatDateTime, humanize } from '../../utils/format'

type Rec = Record<string, any> // eslint-disable-line @typescript-eslint/no-explicit-any
const COUNTERS = ['printed_sides', 'physical_sheets', 'a4_mono', 'a4_colour', 'a3_mono', 'a3_colour']

// ---- Machines --------------------------------------------------------------------

export function MachinesPage() {
  const { can } = useAuth()
  const run = useRun()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState({ code: '', model: '', leased_reference: '', responsible_department_id: null as number | null, location_id: null as number | null, is_shared: false, counter_types: ['printed_sides', 'physical_sheets'] as string[], shared_department_ids: [] as number[] })
  const departments = useLookup('/departments')

  const columns: GridColDef<Rec>[] = [
    { field: 'code', headerName: 'Machine', width: 130, renderCell: ({ value }) => <CodeText>{value}</CodeText> },
    { field: 'model', headerName: 'Model', flex: 1, minWidth: 200 },
    { field: 'ownership', headerName: 'Ownership', width: 200, sortable: false, valueGetter: (_, r) => (r.asset ? `Owned · ${r.asset.asset_no}` : `Leased · ${r.leased_reference}`) },
    { field: 'responsible_department', headerName: 'Responsible', width: 200, sortable: false, valueGetter: (_, r) => r.responsible_department?.name_en },
    { field: 'location', headerName: 'Location', width: 180, sortable: false, valueGetter: (_, r) => r.location?.name ?? '—' },
    { field: 'is_shared', headerName: 'Shared', width: 90, renderCell: ({ value }) => (value ? <Chip label="shared" color="info" variant="outlined" /> : '—') },
    { field: 'counters', headerName: 'Counters', width: 220, sortable: false, valueGetter: (v) => ((v as Rec[]) ?? []).map((c) => humanize(c.counter_type)).join(', ') },
  ]

  return (
    <>
      <ListPage<Rec>
        title="Printers & copiers"
        subtitle="Machines whose meter readings justify A4 paper requests. A machine is an owned asset or a leased unit; shared machines split their usage between departments."
        endpoint="/machines"
        queryKey={['/machines']}
        columns={columns}
        sortFields={['code', 'model']}
        defaultSort={[{ field: 'code', sort: 'asc' }]}
        filters={[{ name: 'is_shared', label: 'Shared', options: [{ value: 'true', label: 'Shared only' }] }]}
        rowLink={(r) => `/a4/machines/${r.id}`}
        createLabel="Register machine"
        onCreate={can('machine.manage') ? () => setOpen(true) : undefined}
      />
      <ActionDialog
        action={open ? {
          title: 'Register a machine',
          confirmLabel: 'Register',
          disabled: !form.code || !form.responsible_department_id || !form.leased_reference || form.counter_types.length === 0,
          body: (
            <Grid container spacing={2}>
              <Grid size={6}><TextField label="Machine code" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} required /></Grid>
              <Grid size={6}><TextField label="Model" value={form.model} onChange={(e) => setForm({ ...form, model: e.target.value })} /></Grid>
              <Grid size={12}><TextField label="Lease reference" value={form.leased_reference} onChange={(e) => setForm({ ...form, leased_reference: e.target.value })} helperText="For owned machines, register them from the asset page instead." required /></Grid>
              <Grid size={6}><LookupField endpoint="/departments" label="Responsible department" value={form.responsible_department_id} onChange={(v) => setForm({ ...form, responsible_department_id: v })} optionLabel={labels.coded} required /></Grid>
              <Grid size={6}><LookupField endpoint="/locations" label="Location" value={form.location_id} onChange={(v) => setForm({ ...form, location_id: v })} optionLabel={labels.coded} /></Grid>
              <Grid size={12}>
                <TextField select label="Counters on the panel" value={form.counter_types} onChange={(e) => setForm({ ...form, counter_types: e.target.value as unknown as string[] })} slotProps={{ select: { multiple: true } }} helperText="Printed sides and physical sheets are separate counters (duplex)">
                  {COUNTERS.map((c) => <MenuItem key={c} value={c}>{humanize(c)}</MenuItem>)}
                </TextField>
              </Grid>
              <Grid size={12}><FormControlLabel control={<Switch checked={form.is_shared} onChange={(e) => setForm({ ...form, is_shared: e.target.checked })} />} label="Shared by several departments" /></Grid>
              {form.is_shared && (
                <Grid size={12}>
                  <TextField select label="Departments sharing it" value={form.shared_department_ids} onChange={(e) => setForm({ ...form, shared_department_ids: e.target.value as unknown as number[] })} slotProps={{ select: { multiple: true } }}>
                    {(departments.data ?? []).map((d) => <MenuItem key={String(d.id)} value={d.id as number}>{String(d.name_en)}</MenuItem>)}
                  </TextField>
                </Grid>
              )}
            </Grid>
          ),
          run: async () => {
            const m = await run<Rec>('post', '/machines', form, [['/machines']])
            navigate(`/a4/machines/${m.id}`)
          },
        } : null}
        onClose={() => setOpen(false)}
      />
    </>
  )
}

export function MachineDetailPage() {
  const { id } = useParams()
  const machine = useDoc<Rec>(['/machines', id], `/machines/${id}`)
  const [recording, setRecording] = useState(false)
  const { can } = useAuth()

  if (machine.isLoading) return <Skeleton height={300} />
  if (machine.isError || !machine.data) return <Alert severity="error">{errorMessage(machine.error)}</Alert>
  const m = machine.data

  return (
    <>
      <PageHeader
        crumbs={[{ label: 'Machines', to: '/a4/machines' }, { label: m.code }]}
        title={<Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}><span>{m.code}</span>{m.is_shared && <Chip label="Shared" color="info" />}</Stack>}
        subtitle={m.model}
        actions={can('meter.record') && <Button variant="contained" startIcon={<SpeedOutlinedIcon />} onClick={() => setRecording(true)}>Record reading</Button>}
      />
      <Stack spacing={2.5}>
        <SectionCard title="Machine" icon={<PrintOutlinedIcon />}>
          <InfoGrid columns={4} items={[
            { label: 'Ownership', value: m.asset ? `Owned asset ${m.asset.asset_no}` : `Leased: ${m.leased_reference}` },
            { label: 'Responsible department', value: m.responsible_department?.name_en },
            { label: 'Location', value: m.location?.name },
            { label: 'Counters', value: (m.counters ?? []).map((c: Rec) => humanize(c.counter_type)).join(', ') },
            { label: 'Shared with', value: (m.department_assignments ?? []).map((a: Rec) => a.department?.name_en).join(', ') || '—', wide: true },
          ]} />
        </SectionCard>
        <SectionCard title="Recent readings" icon={<SpeedOutlinedIcon />} tone="info" noPadding>
          <ReadingsTable rows={m.recent_observations ?? []} counters={m.counters ?? []} />
        </SectionCard>
      </Stack>
      {recording && <RecordReadingDialog machineId={m.id} onClose={() => setRecording(false)} />}
    </>
  )
}

function ReadingsTable({ rows, counters }: { rows: Rec[]; counters: Rec[] }) {
  return (
    <Box sx={{ overflowX: 'auto' }}>
      <Table>
        <TableHead>
          <TableRow>
            <TableCell>Observed</TableCell>
            {counters.map((c) => <TableCell key={c.id} align="right">{humanize(c.counter_type)}</TableCell>)}
            <TableCell>By</TableCell><TableCell>Status</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {rows.map((o) => (
            <TableRow key={o.id}>
              <TableCell>{formatDateTime(o.observed_at)} {o.is_baseline && <Chip label={`baseline${o.baseline_reason ? ` · ${humanize(o.baseline_reason)}` : ''}`} color="info" variant="outlined" sx={{ ml: 1 }} />}</TableCell>
              {counters.map((c) => {
                const r = (o.readings ?? []).find((x: Rec) => x.counter_id === c.id)
                return <TableCell key={c.id} align="right">{!r ? '—' : r.is_unknown ? <Chip label="unknown" variant="outlined" /> : Number(r.reading_value).toLocaleString()}</TableCell>
              })}
              <TableCell>{o.observed_by?.full_name}</TableCell>
              <TableCell><Stack spacing={0.5}><StatusChip value={o.status === 'accepted' ? 'active' : o.status === 'flagged' ? 'deny' : 'pending'} label={humanize(o.status)} /><FlagChips flags={o.flag_reasons} /></Stack></TableCell>
            </TableRow>
          ))}
          {rows.length === 0 && <TableRow><TableCell colSpan={counters.length + 3}><Typography color="text.secondary">No readings yet.</Typography></TableCell></TableRow>}
        </TableBody>
      </Table>
    </Box>
  )
}

function RecordReadingDialog({ machineId, onClose }: { machineId?: number; onClose: () => void }) {
  const run = useRun()
  const [machine, setMachine] = useState<number | null>(machineId ?? null)
  const detail = useQuery({ queryKey: ['/machines', String(machine)], queryFn: () => get<Rec>(`/machines/${machine}`), enabled: machine !== null })
  const [values, setValues] = useState<Record<number, { value: string; unknown: boolean }>>({})
  const [observedAt, setObservedAt] = useState(new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16))
  const [baseline, setBaseline] = useState<'' | 'reset' | 'replacement'>('')
  const [photo, setPhoto] = useState<File | null>(null)
  const counters: Rec[] = detail.data?.counters ?? []

  const spec: ActionSpec = {
    title: 'Record meter reading',
    description: 'Copy the counters exactly as shown. If a counter cannot be read, tick "unknown" — it is never recorded as zero. A reset or a replacement machine needs a new baseline.',
    remarks: 'optional',
    confirmLabel: 'Save reading',
    disabled: machine === null || counters.length === 0 || counters.some((c) => !values[c.id]?.unknown && !values[c.id]?.value),
    body: (
      <Stack spacing={2}>
        {!machineId && <LookupField endpoint="/machines" label="Machine" value={machine} onChange={setMachine} optionLabel={(r) => `${r.code} — ${r.model ?? ''}`} required />}
        <TextField label="Observed at" type="datetime-local" value={observedAt} onChange={(e) => setObservedAt(e.target.value)} slotProps={{ inputLabel: { shrink: true } }} />
        {counters.map((c) => (
          <Stack key={c.id} direction="row" spacing={1.5} sx={{ alignItems: 'center' }}>
            <TextField label={humanize(c.counter_type)} type="number" value={values[c.id]?.value ?? ''} disabled={values[c.id]?.unknown} onChange={(e) => setValues({ ...values, [c.id]: { value: e.target.value, unknown: false } })} />
            <FormControlLabel control={<Checkbox checked={values[c.id]?.unknown ?? false} onChange={(e) => setValues({ ...values, [c.id]: { value: '', unknown: e.target.checked } })} />} label="Unknown" />
          </Stack>
        ))}
        <TextField select label="New baseline?" value={baseline} onChange={(e) => setBaseline(e.target.value as '' | 'reset' | 'replacement')} helperText="Only when the counter was reset or the machine was replaced">
          <MenuItem value="">No — normal reading</MenuItem>
          <MenuItem value="reset">Counter was reset</MenuItem>
          <MenuItem value="replacement">Replacement machine</MenuItem>
        </TextField>
        <Button component="label" variant="outlined" startIcon={<PhotoCameraOutlinedIcon />}>
          {photo ? photo.name : 'Attach photo of the meter'}
          <input hidden type="file" accept="image/*" capture="environment" onChange={(e) => setPhoto(e.target.files?.[0] ?? null)} />
        </Button>
      </Stack>
    ),
    run: (remarks) => {
      const fd = new FormData()
      fd.append('machine_id', String(machine))
      fd.append('observed_at', observedAt.replace('T', ' ') + ':00')
      if (baseline) {
        fd.append('is_baseline', '1')
        fd.append('baseline_reason', baseline)
      }
      if (remarks) fd.append('remarks', remarks)
      counters.forEach((c, i) => {
        fd.append(`readings[${i}][counter_id]`, String(c.id))
        if (values[c.id]?.unknown) fd.append(`readings[${i}][is_unknown]`, '1')
        else fd.append(`readings[${i}][reading_value]`, values[c.id]?.value ?? '')
      })
      if (photo) fd.append('photo', photo)
      return run('post', '/meter-observations', fd, [['/meter-observations'], ['/machines']])
    },
  }

  return <ActionDialog action={spec} onClose={onClose} />
}

// ---- Readings ---------------------------------------------------------------------

export function ReadingsPage() {
  const { can } = useAuth()
  const run = useRun()
  const [recording, setRecording] = useState(false)
  const [verifying, setVerifying] = useState<{ row: Rec; decision: 'accept' | 'flag' } | null>(null)

  const columns: GridColDef<Rec>[] = [
    { field: 'observed_at', headerName: 'Observed', width: 170, valueFormatter: (v) => formatDateTime(v) },
    { field: 'machine', headerName: 'Machine', width: 130, sortable: false, renderCell: ({ row }) => <CodeText>{row.machine?.code}</CodeText> },
    { field: 'readings', headerName: 'Readings', flex: 1, minWidth: 260, sortable: false, valueGetter: (_, r) => (r.readings ?? []).map((x: Rec) => `${humanize(x.counter?.counter_type ?? '')}: ${x.is_unknown ? 'unknown' : Number(x.reading_value).toLocaleString()}`).join(' · ') },
    { field: 'observed_by', headerName: 'By', width: 160, sortable: false, valueGetter: (_, r) => r.observed_by?.full_name },
    { field: 'flag_reasons', headerName: 'Flags', width: 220, sortable: false, renderCell: ({ row }) => <Stack direction="row" spacing={0.5}>{row.is_baseline && <Chip label="baseline" color="info" variant="outlined" />}<FlagChips flags={row.flag_reasons} /></Stack> },
    { field: 'status', headerName: 'Status', width: 120, renderCell: ({ value }) => <StatusChip value={value === 'accepted' ? 'active' : value === 'flagged' ? 'deny' : 'pending'} label={humanize(value)} /> },
    {
      field: 'actions', headerName: '', width: 170, sortable: false,
      renderCell: ({ row }) => ['submitted', 'flagged'].includes(row.status) && can('meter.verify') && (
        <Stack direction="row" spacing={1}>
          <Button size="small" variant="contained" color="success" onClick={() => setVerifying({ row, decision: 'accept' })}>Accept</Button>
          {row.status !== 'flagged' && <Button size="small" color="warning" onClick={() => setVerifying({ row, decision: 'flag' })}>Flag</Button>}
        </Stack>
      ),
    },
  ]

  return (
    <>
      <ListPage<Rec>
        title="Meter readings"
        subtitle="Readings lower than the last one, overlapping dates, missing photos and unusual jumps are flagged automatically for the verifier."
        endpoint="/meter-observations"
        queryKey={['/meter-observations']}
        columns={columns}
        sortFields={['observed_at']}
        defaultSort={[{ field: 'observed_at', sort: 'desc' }]}
        filters={[{ name: 'status', label: 'Status', options: ['submitted', 'flagged', 'accepted'] }, { name: 'machine_id', label: 'Machine', lookup: { endpoint: '/machines', label: (r) => String(r.code) } }]}
        createLabel="Record reading"
        onCreate={can('meter.record') ? () => setRecording(true) : undefined}
      />
      {recording && <RecordReadingDialog onClose={() => setRecording(false)} />}
      <ActionDialog
        action={verifying && {
          title: verifying.decision === 'accept' ? 'Accept reading' : 'Flag reading',
          description: verifying.decision === 'accept' ? 'Accepting a baseline approves the reset / replacement. You cannot verify a reading you recorded.' : 'Explain what is wrong so the department can correct it.',
          remarks: verifying.decision === 'flag' ? 'required' : 'optional',
          confirmLabel: verifying.decision === 'accept' ? 'Accept' : 'Flag',
          run: (r) => run('post', `/meter-observations/${verifying.row.id}/verify`, { decision: verifying.decision, remarks: r || null }, [['/meter-observations']]),
        }}
        onClose={() => setVerifying(null)}
      />
    </>
  )
}

// ---- Paper statements ---------------------------------------------------------------

export function StatementsPage() {
  const { can } = useAuth()
  const run = useRun()
  const [open, setOpen] = useState(false)
  const [certifying, setCertifying] = useState<Rec | null>(null)
  const [form, setForm] = useState({ department_id: null as number | null, item: null as ItemOption | null, period_from_on: '', period_to_on: '', opening_balance: '', receipts: '', transfers: '', returns: '', usage_or_loss: '', counted_closing: '', explanation: '' })
  const n = (v: string) => Number(v || 0)
  const expected = n(form.opening_balance) + n(form.receipts) + n(form.transfers) - n(form.returns) - n(form.usage_or_loss)
  const difference = n(form.counted_closing) - expected

  const columns: GridColDef<Rec>[] = [
    { field: 'department', headerName: 'Department', flex: 1, minWidth: 180, sortable: false, valueGetter: (_, r) => r.department?.name_en },
    { field: 'item', headerName: 'Paper', width: 180, sortable: false, valueGetter: (_, r) => r.item?.name_en },
    { field: 'period_from_on', headerName: 'Period', width: 210, valueGetter: (_, r) => `${formatDate(r.period_from_on)} – ${formatDate(r.period_to_on)}` },
    { field: 'opening_balance', headerName: 'Opening', width: 100, renderCell: ({ value }) => <Qty value={value} /> },
    { field: 'receipts', headerName: 'Received', width: 100, renderCell: ({ value }) => <Qty value={value} /> },
    { field: 'usage_or_loss', headerName: 'Used', width: 100, renderCell: ({ value }) => <Qty value={value} /> },
    { field: 'counted_closing', headerName: 'Closing', width: 100, renderCell: ({ value }) => <Qty value={value} /> },
    { field: 'status', headerName: 'Status', width: 120, renderCell: ({ value }) => <StatusChip value={value === 'certified' ? 'active' : 'pending'} label={humanize(value)} /> },
    { field: 'actions', headerName: '', width: 110, sortable: false, renderCell: ({ row }) => row.status === 'submitted' && can('request.certify') && <Button size="small" variant="outlined" onClick={() => setCertifying(row)}>Certify</Button> },
  ]

  return (
    <>
      <ListPage<Rec>
        title="Paper statements"
        subtitle="Each department's paper balance for a period: opening + received + transfers − returns − used must equal the counted closing balance, or the difference must be explained."
        endpoint="/paper-statements"
        queryKey={['/paper-statements']}
        columns={columns}
        sortFields={['period_from_on']}
        defaultSort={[{ field: 'period_from_on', sort: 'desc' }]}
        filters={[{ name: 'department_id', label: 'Department', lookup: { endpoint: '/departments', label: labels.name } }]}
        createLabel="New statement"
        onCreate={can('paper_statement.manage') ? () => setOpen(true) : undefined}
      />
      <ActionDialog
        action={open ? {
          title: 'Paper balance statement',
          confirmLabel: 'Submit statement',
          disabled: !form.department_id || !form.item || !form.period_from_on || !form.period_to_on || (difference !== 0 && form.explanation.length < 3),
          body: (
            <Grid container spacing={2}>
              <Grid size={12}><LookupField endpoint="/departments" label="Department" value={form.department_id} onChange={(v) => setForm({ ...form, department_id: v })} optionLabel={labels.coded} required /></Grid>
              <Grid size={12}><ItemPicker label="Paper item" value={form.item} onChange={(item) => setForm({ ...form, item })} filters={{ is_paper: true }} /></Grid>
              <Grid size={6}><TextField label="From" type="date" value={form.period_from_on} onChange={(e) => setForm({ ...form, period_from_on: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} /></Grid>
              <Grid size={6}><TextField label="To" type="date" value={form.period_to_on} onChange={(e) => setForm({ ...form, period_to_on: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} /></Grid>
              {(['opening_balance', 'receipts', 'transfers', 'returns', 'usage_or_loss', 'counted_closing'] as const).map((k) => (
                <Grid key={k} size={4}><TextField label={humanize(k)} type="number" value={form[k]} onChange={(e) => setForm({ ...form, [k]: e.target.value })} helperText="in base units (sheets)" /></Grid>
              ))}
              <Grid size={12}>
                <Alert severity={difference === 0 ? 'success' : 'warning'}>Expected closing {expected.toLocaleString()} · counted {n(form.counted_closing).toLocaleString()} · difference {difference.toLocaleString()}</Alert>
              </Grid>
              <Grid size={12}><TextField label="Explanation" value={form.explanation} onChange={(e) => setForm({ ...form, explanation: e.target.value })} multiline minRows={2} required={difference !== 0} /></Grid>
            </Grid>
          ),
          run: () => run('post', '/paper-statements', { ...form, item_id: form.item?.id, item: undefined }, [['/paper-statements']]),
        } : null}
        onClose={() => setOpen(false)}
      />
      <ActionDialog
        action={certifying && { title: 'Certify statement', description: 'Confirms the balances. You cannot certify a statement you prepared.', remarks: 'optional', confirmLabel: 'Certify', run: (r) => run('post', `/paper-statements/${certifying.id}/certify`, { remarks: r || null }, [['/paper-statements']]) }}
        onClose={() => setCertifying(null)}
      />
    </>
  )
}

// ---- Shared usage --------------------------------------------------------------------

export function SharedUsagePage() {
  const { can } = useAuth()
  const run = useRun()
  const [open, setOpen] = useState(false)
  const [reviewing, setReviewing] = useState<{ row: Rec; approve: boolean } | null>(null)
  const [form, setForm] = useState({ machine_id: null as number | null, period_from_on: '', period_to_on: '', counter_type: 'printed_sides', lines: {} as Record<number, string> })
  const machine = useQuery({ queryKey: ['/machines', String(form.machine_id)], queryFn: () => get<Rec>(`/machines/${form.machine_id}`), enabled: form.machine_id !== null })
  const sharing: Rec[] = machine.data?.department_assignments ?? []

  const columns: GridColDef<Rec>[] = [
    { field: 'machine', headerName: 'Machine', width: 130, sortable: false, renderCell: ({ row }) => <CodeText>{row.machine?.code}</CodeText> },
    { field: 'period_from_on', headerName: 'Period', width: 210, valueGetter: (_, r) => `${formatDate(r.period_from_on)} – ${formatDate(r.period_to_on)}` },
    { field: 'total_usage', headerName: 'Measured', width: 120, type: 'number' },
    { field: 'lines', headerName: 'Allocation', flex: 1, minWidth: 260, sortable: false, valueGetter: (_, r) => (r.lines ?? []).map((l: Rec) => `${l.department?.code}: ${Number(l.allocated_usage).toLocaleString()} (${l.basis})`).join(' · ') },
    { field: 'status', headerName: 'Status', width: 120, renderCell: ({ value }) => <StatusChip value={value === 'approved' ? 'active' : value === 'returned' ? 'deny' : 'pending'} label={humanize(value)} /> },
    {
      field: 'actions', headerName: '', width: 180, sortable: false,
      renderCell: ({ row }) => row.status === 'submitted' && can('shared_usage.review') && (
        <Stack direction="row" spacing={1}>
          <Button size="small" variant="contained" color="success" onClick={() => setReviewing({ row, approve: true })}>Approve</Button>
          <Button size="small" onClick={() => setReviewing({ row, approve: false })}>Return</Button>
        </Stack>
      ),
    },
  ]

  return (
    <>
      <ListPage<Rec>
        title="Shared machine usage"
        subtitle="The measured usage of a shared machine is split between the departments that use it. The total allocated can never exceed what was measured — usage is never counted twice."
        endpoint="/shared-usage-allocations"
        queryKey={['/shared-usage-allocations']}
        columns={columns}
        sortFields={['period_from_on']}
        defaultSort={[{ field: 'period_from_on', sort: 'desc' }]}
        createLabel="Allocate usage"
        onCreate={can('meter.record') ? () => setOpen(true) : undefined}
      />
      <ActionDialog
        action={open ? {
          title: 'Allocate shared usage',
          description: 'The measured total is worked out from the accepted readings in the period.',
          confirmLabel: 'Submit allocation',
          disabled: !form.machine_id || !form.period_from_on || !form.period_to_on,
          body: (
            <Grid container spacing={2}>
              <Grid size={12}><LookupField endpoint="/machines" label="Shared machine" value={form.machine_id} onChange={(v) => setForm({ ...form, machine_id: v, lines: {} })} optionLabel={(r) => `${r.code} — ${r.model ?? ''}`} filters={{ is_shared: true }} required /></Grid>
              <Grid size={6}><TextField label="From" type="date" value={form.period_from_on} onChange={(e) => setForm({ ...form, period_from_on: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} /></Grid>
              <Grid size={6}><TextField label="To" type="date" value={form.period_to_on} onChange={(e) => setForm({ ...form, period_to_on: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} /></Grid>
              {sharing.map((a) => (
                <Grid key={a.id} size={6}><TextField label={`${a.department?.name_en} (sides)`} type="number" value={form.lines[a.department_id] ?? ''} onChange={(e) => setForm({ ...form, lines: { ...form.lines, [a.department_id]: e.target.value } })} /></Grid>
              ))}
            </Grid>
          ),
          run: () => run('post', '/shared-usage-allocations', {
            machine_id: form.machine_id, period_from_on: form.period_from_on, period_to_on: form.period_to_on, counter_type: form.counter_type,
            lines: Object.entries(form.lines).filter(([, v]) => v !== '').map(([dept, v]) => ({ department_id: Number(dept), allocated_usage: Number(v), basis: 'declared' })),
          }, [['/shared-usage-allocations']]),
        } : null}
        onClose={() => setOpen(false)}
      />
      <ActionDialog
        action={reviewing && { title: reviewing.approve ? 'Approve allocation' : 'Return allocation', remarks: reviewing.approve ? 'optional' : 'required', confirmLabel: reviewing.approve ? 'Approve' : 'Return', run: (r) => run('post', `/shared-usage-allocations/${reviewing.row.id}/review`, { approve: reviewing.approve, remarks: r || null }, [['/shared-usage-allocations']]) }}
        onClose={() => setReviewing(null)}
      />
    </>
  )
}

// ---- Exceptions ------------------------------------------------------------------------

export function ExceptionsPage() {
  const { can } = useAuth()
  const run = useRun()
  const [open, setOpen] = useState(false)
  const [deciding, setDeciding] = useState<{ row: Rec; approve: boolean } | null>(null)
  const [form, setForm] = useState({ request_id: null as number | null, exception_type: 'breakdown' })
  const a4Requests = useQuery({
    queryKey: ['requests', 'a4-open'],
    queryFn: async () => (await getPage<Rec>('/requests', listParams({ perPage: 50, filters: { request_type: 'a4_paper', status: 'draft,returned' } }))).data,
    enabled: open,
  })

  const columns: GridColDef<Rec>[] = [
    { field: 'created_at', headerName: 'Raised', width: 160, valueFormatter: (v) => formatDateTime(v) },
    { field: 'request', headerName: 'Request', width: 160, sortable: false, valueGetter: (_, r) => r.request?.request_no },
    { field: 'department', headerName: 'Department', width: 200, sortable: false, valueGetter: (_, r) => r.request?.department_name_snapshot },
    { field: 'exception_type', headerName: 'Type', width: 160, valueGetter: (v) => humanize(String(v)) },
    { field: 'explanation', headerName: 'Explanation', flex: 1, minWidth: 260 },
    { field: 'status', headerName: 'Status', width: 120, renderCell: ({ value }) => <StatusChip value={value === 'approved' ? 'active' : value === 'rejected' ? 'deny' : 'pending'} label={humanize(value)} /> },
    {
      field: 'actions', headerName: '', width: 180, sortable: false,
      renderCell: ({ row }) => row.status === 'pending' && can('a4.exception_approve') && (
        <Stack direction="row" spacing={1}>
          <Button size="small" variant="contained" color="success" onClick={() => setDeciding({ row, approve: true })}>Approve</Button>
          <Button size="small" color="error" onClick={() => setDeciding({ row, approve: false })}>Reject</Button>
        </Stack>
      ),
    },
  ]

  return (
    <>
      <ListPage<Rec>
        title="A4 exceptions"
        subtitle="When meter evidence cannot be given — no machine, a reset, a replacement, a breakdown, manual or external printing — an approved exception lets the A4 request go ahead."
        endpoint="/a4-exceptions"
        queryKey={['/a4-exceptions']}
        columns={columns}
        sortFields={['created_at']}
        defaultSort={[{ field: 'created_at', sort: 'desc' }]}
        filters={[{ name: 'status', label: 'Status', options: ['pending', 'approved', 'rejected'] }]}
        createLabel="Raise exception"
        onCreate={can('request.create') ? () => setOpen(true) : undefined}
      />
      <ActionDialog
        action={open ? {
          title: 'Raise an A4 exception',
          remarks: 'required',
          remarksLabel: 'Explanation',
          confirmLabel: 'Raise',
          disabled: !form.request_id,
          body: (
            <Stack spacing={2}>
              <TextField select label="A4 request (draft / returned)" value={form.request_id ?? ''} onChange={(e) => setForm({ ...form, request_id: Number(e.target.value) })}>
                {(a4Requests.data ?? []).map((r) => <MenuItem key={r.id} value={r.id}>{r.request_no} · {r.department_name_snapshot}</MenuItem>)}
              </TextField>
              <TextField select label="Reason" value={form.exception_type} onChange={(e) => setForm({ ...form, exception_type: e.target.value })}>
                {['no_machine', 'reset', 'replacement', 'breakdown', 'manual', 'external_printing'].map((t) => <MenuItem key={t} value={t}>{humanize(t)}</MenuItem>)}
              </TextField>
            </Stack>
          ),
          run: (r) => run('post', '/a4-exceptions', { ...form, explanation: r }, [['/a4-exceptions']]),
        } : null}
        onClose={() => setOpen(false)}
      />
      <ActionDialog
        action={deciding && { title: deciding.approve ? 'Approve exception' : 'Reject exception', description: deciding.row.explanation, remarks: deciding.approve ? 'optional' : 'required', confirmLabel: deciding.approve ? 'Approve' : 'Reject', danger: !deciding.approve, run: (r) => run('post', `/a4-exceptions/${deciding.row.id}/decide`, { approve: deciding.approve, remarks: r || null }, [['/a4-exceptions']]) }}
        onClose={() => setDeciding(null)}
      />
    </>
  )
}
