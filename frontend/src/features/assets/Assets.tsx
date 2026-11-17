import BuildOutlinedIcon from '@mui/icons-material/BuildOutlined'
import HistoryOutlinedIcon from '@mui/icons-material/HistoryOutlined'
import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined'
import PrintOutlinedIcon from '@mui/icons-material/PrintOutlined'
import QrCodeScannerOutlinedIcon from '@mui/icons-material/QrCodeScannerOutlined'
import SwapHorizOutlinedIcon from '@mui/icons-material/SwapHorizOutlined'
import VerifiedUserOutlinedIcon from '@mui/icons-material/VerifiedUserOutlined'
import { Alert, Box, Button, Chip, Dialog, DialogContent, Grid, MenuItem, Skeleton, Stack, Table, TableBody, TableCell, TableHead, TableRow, TextField, Typography } from '@mui/material'
import type { GridColDef } from '@mui/x-data-grid'
import { useQuery } from '@tanstack/react-query'
import { QRCodeSVG } from 'qrcode.react'
import { useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { get } from '../../api/client'
import { useAuth } from '../../auth/AuthContext'
import { ActionDialog, type ActionSpec } from '../../components/ActionDialog'
import { ListPage } from '../../components/ListPage'
import { errorMessage } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { ItemPicker, LookupField, labels, type ItemOption } from '../../components/Pickers'
import { StatusChip } from '../../components/StatusChip'
import { CodeText, InfoGrid, Money, SectionCard } from '../../components/ui'
import { useDoc, useRun } from '../../hooks/useApi'
import { brand } from '../../theme'
import { formatDate, formatDateTime, humanize } from '../../utils/format'

type Rec = Record<string, any> // eslint-disable-line @typescript-eslint/no-explicit-any

const STATUS_TONE: Record<string, string> = { in_store: 'pending', in_use: 'active', in_transit: 'transferred', under_repair: 'suspended', on_loan: 'transferred', lost: 'deny', disposed: 'deactivated' }

export const AssetStatus = ({ value }: { value: string }) => <StatusChip value={STATUS_TONE[value] ?? value} label={humanize(value)} />

const columns: GridColDef<Rec>[] = [
  { field: 'asset_no', headerName: 'Asset no.', width: 160, renderCell: ({ value }) => <CodeText>{value}</CodeText> },
  { field: 'item', headerName: 'Description', flex: 1, minWidth: 220, sortable: false, renderCell: ({ row }) => <Box><Typography sx={{ fontSize: 14, fontWeight: 600 }}>{row.item?.name_en}</Typography><Typography sx={{ fontSize: 12, color: 'text.secondary' }}>{[row.brand, row.model, row.serial_no].filter(Boolean).join(' · ')}</Typography></Box> },
  { field: 'responsible_department', headerName: 'Responsible', width: 190, sortable: false, valueGetter: (_, r) => r.responsible_department?.name_en ?? r.store?.name ?? '—' },
  { field: 'custodian', headerName: 'Custodian', width: 160, sortable: false, valueGetter: (_, r) => r.custodian?.full_name ?? '—' },
  { field: 'location', headerName: 'Location', width: 170, sortable: false, valueGetter: (_, r) => r.location?.name ?? '—' },
  { field: 'condition', headerName: 'Condition', width: 110, valueGetter: (v) => humanize(String(v)) },
  { field: 'operational_status', headerName: 'Status', width: 140, renderCell: ({ value }) => <AssetStatus value={value} /> },
]

function RegisterAssetDialog({ grnPublicId, onClose }: { grnPublicId: string | null; onClose: () => void }) {
  const run = useRun()
  const navigate = useNavigate()
  const grn = useQuery({ queryKey: ['/grns', grnPublicId], queryFn: () => get<Rec>(`/grns/${grnPublicId}`), enabled: Boolean(grnPublicId) })
  const assetLines: Rec[] = (grn.data?.lines ?? []).filter((l: Rec) => l.item?.tracking_method === 'asset')
  const [form, setForm] = useState({ item: null as ItemOption | null, grn_line_id: '' as string | number, serial_no: '', model: '', source_type: grnPublicId ? 'new_purchase' : 'existing_in_house', store_id: null as number | null, responsible_department_id: null as number | null, location_id: null as number | null, acquired_on: '', condition: 'good', legacy_code: '' })

  const spec: ActionSpec = {
    title: grnPublicId ? `Register assets from ${grn.data?.grn_no ?? 'GRN'}` : 'Register an asset',
    description: 'The asset number and QR code are permanent for the asset’s life. Leave the acquisition date empty if it is not known — it is never guessed.',
    confirmLabel: 'Register',
    disabled: grnPublicId ? !form.grn_line_id : !form.item,
    body: (
      <Grid container spacing={2}>
        {grnPublicId ? (
          <Grid size={12}>
            <TextField select label="GRN line" value={form.grn_line_id} onChange={(e) => setForm({ ...form, grn_line_id: e.target.value })}>
              {assetLines.map((l) => <MenuItem key={l.id} value={l.id}>{l.item?.name_en} · {Number(l.accepted_qty)} accepted</MenuItem>)}
            </TextField>
          </Grid>
        ) : (
          <>
            <Grid size={12}><ItemPicker label="Item (asset-tracked)" value={form.item} onChange={(item) => setForm({ ...form, item })} filters={{ tracking_method: 'asset' }} /></Grid>
            <Grid size={6}>
              <TextField select label="Source" value={form.source_type} onChange={(e) => setForm({ ...form, source_type: e.target.value })}>
                {['existing_in_house', 'donation', 'transfer_in', 'import', 'new_purchase'].map((s) => <MenuItem key={s} value={s}>{humanize(s)}</MenuItem>)}
              </TextField>
            </Grid>
            <Grid size={6}><TextField label="Acquired on (if known)" type="date" value={form.acquired_on} onChange={(e) => setForm({ ...form, acquired_on: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} /></Grid>
            <Grid size={6}><LookupField endpoint="/departments" label="Responsible department" value={form.responsible_department_id} onChange={(v) => setForm({ ...form, responsible_department_id: v })} optionLabel={labels.coded} helperText="Leave empty if it is in a store" /></Grid>
            <Grid size={6}><LookupField endpoint="/stores" label="Or in store" value={form.store_id} onChange={(v) => setForm({ ...form, store_id: v })} optionLabel={labels.coded} /></Grid>
            <Grid size={6}><LookupField endpoint="/locations" label="Location" value={form.location_id} onChange={(v) => setForm({ ...form, location_id: v })} optionLabel={labels.coded} /></Grid>
            <Grid size={6}><TextField label="Old register code" value={form.legacy_code} onChange={(e) => setForm({ ...form, legacy_code: e.target.value })} /></Grid>
          </>
        )}
        <Grid size={6}><TextField label="Serial number" value={form.serial_no} onChange={(e) => setForm({ ...form, serial_no: e.target.value })} helperText="Optional — the asset number is enough" /></Grid>
        <Grid size={6}><TextField label="Model" value={form.model} onChange={(e) => setForm({ ...form, model: e.target.value })} /></Grid>
        <Grid size={6}>
          <TextField select label="Condition" value={form.condition} onChange={(e) => setForm({ ...form, condition: e.target.value })}>
            {['new', 'good', 'fair', 'poor', 'damaged'].map((c) => <MenuItem key={c} value={c}>{humanize(c)}</MenuItem>)}
          </TextField>
        </Grid>
      </Grid>
    ),
    run: async () => {
      const line = assetLines.find((l) => String(l.id) === String(form.grn_line_id))
      const asset = await run<Rec>('post', '/assets', {
        item_id: grnPublicId ? line?.item_id : form.item?.id,
        grn_line_id: grnPublicId ? form.grn_line_id : null,
        source_type: form.source_type,
        serial_no: form.serial_no || null,
        model: form.model || null,
        condition: form.condition,
        store_id: form.store_id,
        responsible_department_id: form.responsible_department_id,
        location_id: form.location_id,
        acquired_on: form.acquired_on || null,
        legacy_code: form.legacy_code || null,
      }, [['/assets']])
      navigate(`/assets/${asset.public_id}`)
    },
  }

  return <ActionDialog action={spec} onClose={onClose} />
}

export function AssetsPage() {
  const { can } = useAuth()
  const [params, setParams] = useSearchParams()
  const [registering, setRegistering] = useState(false)
  const grnPublicId = params.get('register_grn')

  return (
    <>
      <ListPage<Rec>
        title="Asset register"
        subtitle="Every controlled asset with its permanent number and QR code — WHO is accountable (department), WHERE it is (location) and WHO holds it (custodian)."
        endpoint="/assets"
        queryKey={['/assets']}
        columns={columns}
        sortFields={['asset_no', 'last_verified_at']}
        defaultSort={[{ field: 'asset_no', sort: 'desc' }]}
        filters={[
          { name: 'operational_status', label: 'Status', options: ['in_store', 'in_use', 'in_transit', 'under_repair', 'on_loan', 'lost', 'disposed'] },
          { name: 'responsible_department_id', label: 'Department', lookup: { endpoint: '/departments', label: labels.name } },
          { name: 'category_id', label: 'Category', lookup: { endpoint: '/item-categories', label: labels.name } },
        ]}
        searchPlaceholder="Asset no., serial, model"
        rowLink={(r) => `/assets/${r.public_id}`}
        createLabel="Register asset"
        onCreate={can('asset.create') ? () => setRegistering(true) : undefined}
      />
      {(registering || grnPublicId) && <RegisterAssetDialog grnPublicId={grnPublicId} onClose={() => { setRegistering(false); setParams({}) }} />}
    </>
  )
}

/** Printable QR label. */
function QrLabel({ asset, size = 132 }: { asset: Rec; size?: number }) {
  return (
    <Box sx={{ p: 2, borderRadius: 4, bgcolor: '#fff', color: '#0f1b3d', display: 'inline-flex', flexDirection: 'column', alignItems: 'center', gap: 1, border: '1px solid #dbe3f3' }}>
      <QRCodeSVG value={asset.qr?.url ?? asset.qr_token} size={size} level="M" fgColor="#061a4d" />
      <Typography sx={{ fontWeight: 800, fontFamily: 'monospace', fontSize: 14 }}>{asset.asset_no}</Typography>
      <Typography sx={{ fontSize: 10, color: '#5b6785', textAlign: 'center', maxWidth: size + 20 }}>MPCLG · Property of the Ministry</Typography>
    </Box>
  )
}

export function AssetDetailPage() {
  const { id } = useParams()
  const { can } = useAuth()
  const run = useRun()
  const navigate = useNavigate()
  const asset = useDoc<Rec>(['/assets', id], `/assets/${id}`)
  const [dialog, setDialog] = useState<string | null>(null)
  const [printing, setPrinting] = useState(false)
  const [transfer, setTransfer] = useState({ transfer_type: 'transfer', to_department_id: null as number | null, to_location_id: null as number | null, to_custodian_officer_id: null as number | null, expected_return_on: '', accessories: '', authority_ref: '' })
  const [warranty, setWarranty] = useState({ starts_on: '', ends_on: '', provider_supplier_id: null as number | null, coverage_type: '' })

  if (asset.isLoading) return <Skeleton height={300} />
  if (asset.isError || !asset.data) return <Alert severity="error">{errorMessage(asset.error)}</Alert>
  const a = asset.data
  const inv = [['/assets'], ['/assets', id], ['/asset-transfers'], ['/repair-cases'], ['/warranties']]
  const active = !['disposed', 'lost'].includes(a.operational_status)

  const specs: Record<string, ActionSpec> = {
    transfer: {
      title: 'Transfer or lend this asset',
      description: 'Needs approval, then dispatch. The location and custodian only change when the receiver confirms.',
      remarks: 'optional', remarksLabel: 'Reason',
      confirmLabel: 'Submit for approval',
      disabled: !transfer.to_department_id && !transfer.to_location_id && !transfer.to_custodian_officer_id,
      body: (
        <Grid container spacing={2}>
          <Grid size={6}>
            <TextField select label="Type" value={transfer.transfer_type} onChange={(e) => setTransfer({ ...transfer, transfer_type: e.target.value })}>
              <MenuItem value="transfer">Permanent transfer</MenuItem>
              <MenuItem value="loan">Temporary loan</MenuItem>
            </TextField>
          </Grid>
          {transfer.transfer_type === 'loan' && <Grid size={6}><TextField label="Expected back" type="date" value={transfer.expected_return_on} onChange={(e) => setTransfer({ ...transfer, expected_return_on: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} /></Grid>}
          <Grid size={12}><LookupField endpoint="/departments" label="To department" value={transfer.to_department_id} onChange={(v) => setTransfer({ ...transfer, to_department_id: v })} optionLabel={labels.coded} /></Grid>
          <Grid size={6}><LookupField endpoint="/locations" label="To location" value={transfer.to_location_id} onChange={(v) => setTransfer({ ...transfer, to_location_id: v })} optionLabel={labels.coded} /></Grid>
          <Grid size={6}><LookupField endpoint="/officers" label="New custodian" value={transfer.to_custodian_officer_id} onChange={(v) => setTransfer({ ...transfer, to_custodian_officer_id: v })} optionLabel={labels.officer} /></Grid>
          <Grid size={6}><TextField label="Accessories" value={transfer.accessories} onChange={(e) => setTransfer({ ...transfer, accessories: e.target.value })} /></Grid>
          <Grid size={6}><TextField label="Authority reference" value={transfer.authority_ref} onChange={(e) => setTransfer({ ...transfer, authority_ref: e.target.value })} /></Grid>
        </Grid>
      ),
      run: async (r) => {
        const t = await run<Rec>('post', '/asset-transfers', { asset_id: a.public_id, ...transfer, expected_return_on: transfer.expected_return_on || null, reason: r || null }, inv)
        navigate(`/assets/transfers/${t.public_id}`)
      },
    },
    repair: {
      title: 'Report a fault',
      description: a.warranties?.some((w: Rec) => w.status === 'active' && new Date(w.ends_on) >= new Date()) ? 'This asset is under warranty — the case will be marked accordingly.' : undefined,
      remarks: 'required', remarksLabel: 'What is wrong?',
      confirmLabel: 'Open repair case',
      run: async (r) => {
        const c = await run<Rec>('post', '/repair-cases', { asset_id: a.public_id, fault_description: r }, inv)
        navigate(`/maintenance/repairs/${c.public_id}`)
      },
    },
    warranty: {
      title: 'Add warranty',
      confirmLabel: 'Save',
      disabled: !warranty.starts_on || !warranty.ends_on,
      body: (
        <Grid container spacing={2}>
          <Grid size={6}><TextField label="Starts" type="date" value={warranty.starts_on} onChange={(e) => setWarranty({ ...warranty, starts_on: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} /></Grid>
          <Grid size={6}><TextField label="Ends" type="date" value={warranty.ends_on} onChange={(e) => setWarranty({ ...warranty, ends_on: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} /></Grid>
          <Grid size={12}><LookupField endpoint="/suppliers" label="Provider" value={warranty.provider_supplier_id} onChange={(v) => setWarranty({ ...warranty, provider_supplier_id: v })} optionLabel={labels.coded} /></Grid>
          <Grid size={12}><TextField label="Coverage" value={warranty.coverage_type} onChange={(e) => setWarranty({ ...warranty, coverage_type: e.target.value })} /></Grid>
        </Grid>
      ),
      run: () => run('post', '/warranties', { asset_id: a.public_id, ...warranty }, inv),
    },
  }

  return (
    <>
      <Box sx={{ borderRadius: 6, p: { xs: 3, md: 4 }, mb: 3, color: '#fff', background: brand.hero, boxShadow: '0 20px 50px rgba(6, 26, 77, 0.3)' }}>
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={3} sx={{ alignItems: { md: 'center' } }}>
          <QrLabel asset={a} size={112} />
          <Box sx={{ flex: 1, minWidth: 0 }}>
            <Typography variant="overline" sx={{ color: brand.sky }}>{a.category?.name ?? 'ASSET'}</Typography>
            <Typography sx={{ fontSize: { xs: 24, md: 30 }, fontWeight: 800, lineHeight: 1.15 }}>{a.item?.name_en}</Typography>
            <Typography sx={{ color: 'rgba(220,232,255,0.75)', mt: 0.5 }}>{[a.brand, a.model, a.serial_no && `S/N ${a.serial_no}`].filter(Boolean).join(' · ')}</Typography>
            <Stack direction="row" spacing={1} useFlexGap sx={{ mt: 2, flexWrap: 'wrap' }}>
              <Chip label={a.asset_no} sx={{ bgcolor: 'rgba(255,255,255,0.12)', color: '#fff', fontFamily: 'monospace' }} />
              <Chip label={humanize(a.operational_status)} sx={{ bgcolor: 'rgba(47,111,237,0.45)', color: '#fff' }} />
              <Chip label={`Condition: ${humanize(a.condition)}`} sx={{ bgcolor: 'rgba(255,255,255,0.12)', color: '#fff' }} />
              {a.last_verified_at && <Chip label={`Verified ${formatDate(a.last_verified_at)}`} sx={{ bgcolor: 'rgba(34,197,94,0.3)', color: '#fff' }} />}
            </Stack>
          </Box>
          <Stack spacing={1} sx={{ minWidth: 200 }}>
            {active && can('transfer.create') && <Button onClick={() => setDialog('transfer')} startIcon={<SwapHorizOutlinedIcon />} sx={{ bgcolor: '#fff', color: brand.deep, fontWeight: 700, '&:hover': { bgcolor: '#e8f0ff' } }}>Transfer / lend</Button>}
            {active && can('repair.create') && <Button onClick={() => setDialog('repair')} startIcon={<BuildOutlinedIcon />} sx={{ color: '#fff', border: '1px solid rgba(255,255,255,0.4)' }}>Report fault</Button>}
            {can('asset.print_qr') && <Button onClick={() => setPrinting(true)} startIcon={<PrintOutlinedIcon />} sx={{ color: '#fff', border: '1px solid rgba(255,255,255,0.4)' }}>Print QR label</Button>}
          </Stack>
        </Stack>
      </Box>

      <Grid container spacing={2.5}>
        <Grid size={{ xs: 12, lg: 7 }}>
          <Stack spacing={2.5}>
            <SectionCard title="Accountability" icon={<InfoOutlinedIcon />}>
              <InfoGrid columns={3} items={[
                { label: 'Responsible department', value: a.responsible_department?.name_en ?? (a.store ? `In store: ${a.store.name}` : '—') },
                { label: 'Location', value: a.location?.name },
                { label: 'Custodian', value: a.custodian?.full_name },
                { label: 'Source', value: humanize(a.source_type) },
                { label: 'Acquired', value: a.acquired_on ? `${formatDate(a.acquired_on)} (${a.acquired_on_quality})` : 'Unknown' },
                { label: 'In service since', value: formatDate(a.in_service_on) },
                { label: 'Old register', value: [a.legacy_code, a.book_no && `book ${a.book_no}`, a.page_no && `p. ${a.page_no}`].filter(Boolean).join(', ') || '—' },
                { label: 'Value reference', value: a.value_reference },
                { label: 'Replaces', value: a.replaces?.asset_no },
              ]} />
            </SectionCard>

            <SectionCard title="Transfers & loans" icon={<SwapHorizOutlinedIcon />} tone="info">
              {(a.transfers ?? []).length === 0 ? <Typography color="text.secondary" sx={{ fontSize: 14 }}>None.</Typography> : (
                <Stack spacing={1}>
                  {a.transfers.map((t: Rec) => (
                    <Stack key={t.id} direction="row" onClick={() => navigate(`/assets/transfers/${t.public_id}`)} sx={{ alignItems: 'center', gap: 1.5, p: 1.25, borderRadius: 3, border: 1, borderColor: 'divider', cursor: 'pointer', '&:hover': { bgcolor: 'action.hover' } }}>
                      <CodeText>{t.transfer_no}</CodeText>
                      <Typography sx={{ flex: 1, fontSize: 14 }}>{humanize(t.transfer_type)} → {t.to_department?.name_en ?? '—'}</Typography>
                      <StatusChip value={t.status} />
                    </Stack>
                  ))}
                </Stack>
              )}
            </SectionCard>

            <SectionCard title="Repairs" icon={<BuildOutlinedIcon />} tone="warning">
              {(a.repairs ?? []).length === 0 ? <Typography color="text.secondary" sx={{ fontSize: 14 }}>None.</Typography> : (
                <Stack spacing={1}>
                  {a.repairs.map((r: Rec) => (
                    <Stack key={r.id} direction="row" onClick={() => navigate(`/maintenance/repairs/${r.public_id}`)} sx={{ alignItems: 'center', gap: 1.5, p: 1.25, borderRadius: 3, border: 1, borderColor: 'divider', cursor: 'pointer', '&:hover': { bgcolor: 'action.hover' } }}>
                      <CodeText>{r.case_no}</CodeText>
                      <Typography sx={{ flex: 1, fontSize: 14 }} noWrap>{r.fault_description}</Typography>
                      {r.under_warranty && <Chip label="warranty" color="success" variant="outlined" />}
                      <StatusChip value={r.status === 'closed' ? 'active' : 'pending'} label={humanize(r.status)} />
                    </Stack>
                  ))}
                </Stack>
              )}
            </SectionCard>
          </Stack>
        </Grid>

        <Grid size={{ xs: 12, lg: 5 }}>
          <Stack spacing={2.5}>
            <SectionCard title="Custody history" icon={<HistoryOutlinedIcon />} tone="secondary">
              <Stack spacing={0}>
                {(a.custody_history ?? []).map((h: Rec, i: number) => (
                  <Stack key={h.id} direction="row" spacing={1.5}>
                    <Stack sx={{ alignItems: 'center' }}>
                      <Box sx={{ width: 12, height: 12, borderRadius: '50%', bgcolor: i === 0 ? 'primary.main' : 'divider', mt: 0.5 }} />
                      {i < a.custody_history.length - 1 && <Box sx={{ flex: 1, width: 2, bgcolor: 'divider' }} />}
                    </Stack>
                    <Box sx={{ pb: 2 }}>
                      <Typography sx={{ fontWeight: 700, fontSize: 14 }}>{h.reason} · {humanize(h.operational_status ?? '')}</Typography>
                      <Typography sx={{ fontSize: 13, color: 'text.secondary' }}>{[h.department_snapshot, h.location_snapshot, h.custodian_name_snapshot].filter(Boolean).join(' · ') || 'Stores'}</Typography>
                      <Typography sx={{ fontSize: 12, color: 'text.secondary' }}>{formatDateTime(h.from_at)}</Typography>
                    </Box>
                  </Stack>
                ))}
              </Stack>
            </SectionCard>

            <SectionCard title="Warranty" icon={<VerifiedUserOutlinedIcon />} tone="success" action={can('warranty.manage') && active && <Button size="small" onClick={() => setDialog('warranty')}>Add</Button>}>
              {(a.warranties ?? []).length === 0 ? <Typography color="text.secondary" sx={{ fontSize: 14 }}>No warranty recorded.</Typography> : (
                <Stack spacing={1}>
                  {a.warranties.map((w: Rec) => (
                    <Box key={w.id} sx={{ p: 1.25, borderRadius: 3, border: 1, borderColor: 'divider' }}>
                      <Typography sx={{ fontSize: 14, fontWeight: 600 }}>{formatDate(w.starts_on)} – {formatDate(w.ends_on)} {w.extends_warranty_id && <Chip label="extension" sx={{ ml: 1 }} />}</Typography>
                      <Typography sx={{ fontSize: 12, color: 'text.secondary' }}>{w.provider?.name ?? ''} {w.coverage_type ? `· ${w.coverage_type}` : ''}</Typography>
                    </Box>
                  ))}
                </Stack>
              )}
            </SectionCard>

            {(a.valuations ?? []).length > 0 && (
              <SectionCard title="Valuations" icon={<InfoOutlinedIcon />}>
                <Table size="small">
                  <TableHead><TableRow><TableCell>Effective</TableCell><TableCell align="right">Value</TableCell><TableCell>Status</TableCell></TableRow></TableHead>
                  <TableBody>
                    {a.valuations.map((v: Rec) => (
                      <TableRow key={v.id}><TableCell>{formatDate(v.effective_on)}</TableCell><TableCell align="right"><Money value={v.new_value} /></TableCell><TableCell><StatusChip value={v.status === 'approved' ? 'active' : 'pending'} label={humanize(v.status)} /></TableCell></TableRow>
                    ))}
                  </TableBody>
                </Table>
              </SectionCard>
            )}
          </Stack>
        </Grid>
      </Grid>

      <ActionDialog action={dialog ? specs[dialog] : null} onClose={() => setDialog(null)} />
      <Dialog open={printing} onClose={() => setPrinting(false)}>
        <DialogContent sx={{ textAlign: 'center' }}>
          <Box className="print-area"><QrLabel asset={a} size={200} /></Box>
          <Stack direction="row" spacing={1} sx={{ justifyContent: 'center', mt: 2 }} className="no-print">
            <Button onClick={() => setPrinting(false)}>Close</Button>
            <Button variant="contained" startIcon={<PrintOutlinedIcon />} onClick={() => window.print()}>Print</Button>
          </Stack>
        </DialogContent>
      </Dialog>
    </>
  )
}

/** QR landing: /scan/:token (from the label) or type / paste the token. */
export function ScanPage() {
  const { token } = useParams()
  const navigate = useNavigate()
  const [input, setInput] = useState('')
  const scan = useQuery({ queryKey: ['scan', token], queryFn: () => get<Rec>(`/assets/scan/${token}`), enabled: Boolean(token), retry: false })

  const go = () => {
    const match = input.match(/[a-f0-9]{32}/)
    if (match) navigate(`/scan/${match[0]}`)
  }

  return (
    <>
      <PageHeader title="Scan an asset QR" subtitle="Point a phone camera at the label to open this page, or paste the code from the label link." />
      <Grid container spacing={2.5}>
        <Grid size={{ xs: 12, md: 5 }}>
          <SectionCard title="Find by QR code" icon={<QrCodeScannerOutlinedIcon />}>
            <Stack direction="row" spacing={1}>
              <TextField label="QR link or code" value={input} onChange={(e) => setInput(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && go()} />
              <Button variant="contained" onClick={go}>Open</Button>
            </Stack>
          </SectionCard>
        </Grid>
        <Grid size={{ xs: 12, md: 7 }}>
          {token && (scan.isLoading ? <Skeleton height={200} /> : scan.isError ? <Alert severity="error">{errorMessage(scan.error)}</Alert> : scan.data && (
            <SectionCard title={scan.data.asset_no} icon={<InfoOutlinedIcon />} action={<Button onClick={() => navigate(`/assets/${scan.data!.id}`)}>Full record</Button>}>
              <InfoGrid columns={2} items={[
                { label: 'Description', value: scan.data.description },
                { label: 'Model', value: [scan.data.brand, scan.data.model].filter(Boolean).join(' ') },
                { label: 'Responsible department', value: scan.data.responsible_department },
                { label: 'Recorded location', value: scan.data.recorded_location },
                { label: 'Status', value: <AssetStatus value={scan.data.operational_status} /> },
                { label: 'Last update', value: formatDateTime(scan.data.last_update) },
              ]} />
            </SectionCard>
          ))}
        </Grid>
      </Grid>
    </>
  )
}
