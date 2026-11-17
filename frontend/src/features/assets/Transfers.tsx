import FactCheckOutlinedIcon from '@mui/icons-material/FactCheckOutlined'
import SwapHorizOutlinedIcon from '@mui/icons-material/SwapHorizOutlined'
import { Alert, Button, Checkbox, Chip, FormControlLabel, Grid, MenuItem, Skeleton, Stack, TextField } from '@mui/material'
import type { GridColDef } from '@mui/x-data-grid'
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useAuth } from '../../auth/AuthContext'
import { ActionDialog, type ActionSpec } from '../../components/ActionDialog'
import { ApprovalTimeline } from '../../components/ApprovalTimeline'
import { ListPage } from '../../components/ListPage'
import { errorMessage } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { StatusChip } from '../../components/StatusChip'
import { CodeText, InfoGrid, SectionCard } from '../../components/ui'
import { useDoc, useRun } from '../../hooks/useApi'
import { formatDate, formatDateTime, humanize } from '../../utils/format'

type Rec = Record<string, any> // eslint-disable-line @typescript-eslint/no-explicit-any

export function TransfersPage() {
  const columns: GridColDef<Rec>[] = [
    { field: 'transfer_no', headerName: 'Transfer', width: 150, renderCell: ({ value }) => <CodeText>{value}</CodeText> },
    { field: 'asset', headerName: 'Asset', flex: 1, minWidth: 220, sortable: false, valueGetter: (_, r) => `${r.asset?.asset_no} · ${r.asset?.item?.name_en ?? ''}` },
    { field: 'transfer_type', headerName: 'Type', width: 100, renderCell: ({ value }) => <Chip label={humanize(value)} variant="outlined" color={value === 'loan' ? 'secondary' : 'default'} /> },
    { field: 'from_department', headerName: 'From', width: 180, sortable: false, valueGetter: (_, r) => r.from_department?.name_en ?? 'Stores' },
    { field: 'to_department', headerName: 'To', width: 180, sortable: false, valueGetter: (_, r) => r.to_department?.name_en ?? '—' },
    { field: 'requested_by', headerName: 'Requested by', width: 160, sortable: false, valueGetter: (_, r) => r.requested_by?.full_name },
    { field: 'status', headerName: 'Status', width: 120, renderCell: ({ value }) => <StatusChip value={value} /> },
  ]

  return (
    <ListPage<Rec>
      title="Transfers & loans"
      subtitle="Approval ≠ moved. After dispatch the asset is In Transit; its location and custodian change only when the receiver confirms condition and serial."
      endpoint="/asset-transfers"
      queryKey={['/asset-transfers']}
      columns={columns}
      sortFields={['transfer_no']}
      defaultSort={[{ field: 'transfer_no', sort: 'desc' }]}
      filters={[
        { name: 'status', label: 'Status', options: ['submitted', 'approved', 'dispatched', 'received', 'refused', 'returned', 'cancelled'] },
        { name: 'transfer_type', label: 'Type', options: ['transfer', 'loan'] },
        { name: 'overdue_loans', label: 'Loans', options: [{ value: 'true', label: 'Overdue loans' }] },
      ]}
      searchPlaceholder="Transfer no., reference"
      rowLink={(r) => `/assets/transfers/${r.public_id}`}
    />
  )
}

export function TransferDetailPage() {
  const { id } = useParams()
  const { can } = useAuth()
  const run = useRun()
  const navigate = useNavigate()
  const doc = useDoc<Rec>(['/asset-transfers', id], `/asset-transfers/${id}`)
  const [dialog, setDialog] = useState<string | null>(null)
  const [condition, setCondition] = useState('good')
  const [serialOk, setSerialOk] = useState(false)

  if (doc.isLoading) return <Skeleton height={300} />
  if (doc.isError || !doc.data) return <Alert severity="error">{errorMessage(doc.error)}</Alert>
  const t = doc.data
  const inv = [['/asset-transfers'], ['/asset-transfers', id], ['/assets']]
  const conditions = (
    <TextField select label="Condition" value={condition} onChange={(e) => setCondition(e.target.value)}>
      {['new', 'good', 'fair', 'poor', 'damaged'].map((c) => <MenuItem key={c} value={c}>{humanize(c)}</MenuItem>)}
    </TextField>
  )

  const specs: Record<string, ActionSpec> = {
    approve: { title: 'Approve transfer', description: 'Approval does not move the asset. You cannot approve a transfer you requested.', remarks: 'optional', confirmLabel: 'Approve', run: (r) => run('post', `/asset-transfers/${t.public_id}/approve`, { remarks: r || null }, inv) },
    reject: { title: 'Reject transfer', remarks: 'required', danger: true, confirmLabel: 'Reject', run: (r) => run('post', `/asset-transfers/${t.public_id}/reject`, { remarks: r }, inv) },
    dispatch: { title: 'Dispatch asset', description: 'The asset becomes In Transit until the receiver confirms.', body: conditions, confirmLabel: 'Dispatch', run: () => run('post', `/asset-transfers/${t.public_id}/dispatch`, { condition }, inv) },
    receive: {
      title: 'Confirm receipt',
      description: 'Check the asset number / serial against the label before confirming.',
      body: <Stack spacing={2}>{conditions}<FormControlLabel control={<Checkbox checked={serialOk} onChange={(e) => setSerialOk(e.target.checked)} />} label={`Asset ${t.asset?.asset_no} and serial checked`} /></Stack>,
      disabled: !serialOk,
      confirmLabel: 'Confirm receipt',
      run: () => run('post', `/asset-transfers/${t.public_id}/receive`, { condition, serial_verified: true }, inv),
    },
    refuse: { title: 'Refuse delivery', description: 'The asset goes back to its previous state and a discrepancy is opened.', remarks: 'required', danger: true, confirmLabel: 'Refuse', run: (r) => run('post', `/asset-transfers/${t.public_id}/refuse`, { reason: r }, inv) },
    return: { title: 'Loan returned', description: 'The asset comes back to the lending department.', remarks: 'optional', confirmLabel: 'Record return', run: (r) => run('post', `/asset-transfers/${t.public_id}/return`, { remarks: r || null }, inv) },
  }

  return (
    <>
      <PageHeader
        crumbs={[{ label: 'Transfers & loans', to: '/assets/transfers' }, { label: t.transfer_no }]}
        title={<Stack direction="row" spacing={1.5} sx={{ alignItems: 'center' }}><span>{t.transfer_no}</span><StatusChip value={t.status} />{t.transfer_type === 'loan' && <Chip label="Loan" color="secondary" />}</Stack>}
        subtitle={`${t.asset?.asset_no} · ${t.asset?.item?.name_en ?? ''}`}
        actions={
          <>
            {t.status === 'submitted' && can('transfer.approve') && <><Button variant="contained" color="success" onClick={() => setDialog('approve')}>Approve</Button><Button variant="outlined" color="error" onClick={() => setDialog('reject')}>Reject</Button></>}
            {t.status === 'approved' && can('transfer.dispatch') && <Button variant="contained" onClick={() => { setCondition('good'); setDialog('dispatch') }}>Dispatch</Button>}
            {t.status === 'dispatched' && can('transfer.receive') && <><Button variant="contained" color="success" onClick={() => { setSerialOk(false); setDialog('receive') }}>Confirm receipt</Button><Button variant="outlined" color="error" onClick={() => setDialog('refuse')}>Refuse</Button></>}
            {t.status === 'received' && t.transfer_type === 'loan' && can('loan.manage') && <Button variant="outlined" onClick={() => setDialog('return')}>Record loan return</Button>}
            <Button onClick={() => navigate(`/assets/${t.asset?.public_id}`)}>Open asset</Button>
          </>
        }
      />
      <Grid container spacing={2.5}>
        <Grid size={{ xs: 12, lg: 8 }}>
          <SectionCard title="Movement" icon={<SwapHorizOutlinedIcon />}>
            <InfoGrid columns={2} items={[
              { label: 'From department', value: t.from_department?.name_en ?? 'Stores' },
              { label: 'To department', value: t.to_department?.name_en },
              { label: 'From location', value: t.from_location?.name },
              { label: 'To location', value: t.to_location?.name },
              { label: 'New custodian', value: t.to_custodian?.full_name },
              { label: 'Requested by', value: t.requested_by?.full_name },
              { label: 'Dispatched', value: t.dispatched_at ? `${formatDateTime(t.dispatched_at)} · ${humanize(t.condition_on_dispatch ?? '')}` : '—' },
              { label: 'Received', value: t.received_at ? `${formatDateTime(t.received_at)} · ${humanize(t.condition_on_receipt ?? '')}` : '—' },
              { label: 'Expected back (loan)', value: formatDate(t.expected_return_on) },
              { label: 'Accessories', value: t.accessories },
              { label: 'Reason', value: t.reason, wide: true },
            ]} />
          </SectionCard>
        </Grid>
        <Grid size={{ xs: 12, lg: 4 }}>
          <SectionCard title="Approval" icon={<FactCheckOutlinedIcon />} tone="success"><ApprovalTimeline steps={t.approvals} /></SectionCard>
        </Grid>
      </Grid>
      <ActionDialog action={dialog ? specs[dialog] : null} onClose={() => setDialog(null)} />
    </>
  )
}
