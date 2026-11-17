import { Alert, Button, Dialog, DialogActions, DialogContent, DialogTitle, Stack, Table, TableBody, TableCell, TableHead, TableRow, TextField } from '@mui/material'
import type { GridColDef } from '@mui/x-data-grid'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { get } from '../../api/client'
import { ListPage } from '../../components/ListPage'
import { errorMessage } from '../../components/Notify'
import { StatusChip } from '../../components/StatusChip'
import { CodeText, Qty } from '../../components/ui'
import { useRun } from '../../hooks/useApi'
import { formatDate } from '../../utils/format'
import { ActionDialog } from '../../components/ActionDialog'

type Rec = Record<string, any> // eslint-disable-line @typescript-eslint/no-explicit-any

function NewReturn({ grnId, onClose }: { grnId: string; onClose: () => void }) {
  const run = useRun()
  const [grn, setGrn] = useState<Rec | null>(null)
  const [qty, setQty] = useState<Record<number, string>>({})
  const [reason, setReason] = useState('')
  const [ref, setRef] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<unknown>(null)

  useEffect(() => {
    get<Rec>(`/grns/${grnId}`).then(setGrn).catch(setError)
  }, [grnId])

  const save = async () => {
    setBusy(true)
    setError(null)
    try {
      await run('post', '/supplier-returns', {
        grn_id: grn!.id,
        reason,
        replacement_or_claim_ref: ref || null,
        lines: grn!.lines.filter((l: Rec) => Number(qty[l.id]) > 0).map((l: Rec) => ({ grn_line_id: l.id, qty: qty[l.id] })),
      }, [['/supplier-returns']])
      onClose()
    } catch (e) {
      setError(e)
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onClose={() => !busy && onClose()} maxWidth="md" fullWidth>
      <DialogTitle sx={{ fontWeight: 700 }}>Return goods to supplier {grn ? `— ${grn.grn_no}` : ''}</DialogTitle>
      <DialogContent dividers>
        {!grn ? (
          <Alert severity="info">Loading GRN…</Alert>
        ) : (
          <Stack spacing={2}>
            <Table size="small">
              <TableHead><TableRow><TableCell>Item</TableCell><TableCell align="right">Accepted</TableCell><TableCell width={160}>Return qty</TableCell></TableRow></TableHead>
              <TableBody>
                {grn.lines.filter((l: Rec) => Number(l.accepted_qty) > 0).map((l: Rec) => (
                  <TableRow key={l.id}>
                    <TableCell><CodeText>{l.item?.item_code}</CodeText> {l.item?.name_en}</TableCell>
                    <TableCell align="right"><Qty value={l.accepted_qty} unit={l.unit?.code} /></TableCell>
                    <TableCell><TextField type="number" value={qty[l.id] ?? ''} onChange={(e) => setQty({ ...qty, [l.id]: e.target.value })} /></TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
            <TextField label="Reason" value={reason} onChange={(e) => setReason(e.target.value)} required />
            <TextField label="Replacement / claim reference" value={ref} onChange={(e) => setRef(e.target.value)} />
          </Stack>
        )}
        {error !== null && <Alert severity="error" sx={{ mt: 2 }}>{errorMessage(error)}</Alert>}
      </DialogContent>
      <DialogActions sx={{ px: 3, py: 2 }}>
        <Button onClick={onClose}>Cancel</Button>
        <Button variant="contained" disabled={busy || !grn || reason.length < 3 || !Object.values(qty).some((q) => Number(q) > 0)} onClick={save}>Save return</Button>
      </DialogActions>
    </Dialog>
  )
}

export function SupplierReturnsPage() {
  const [params, setParams] = useSearchParams()
  const grn = params.get('grn')
  const run = useRun()
  const [posting, setPosting] = useState<Rec | null>(null)

  const columns: GridColDef<Rec>[] = [
    { field: 'return_no', headerName: 'Return no.', width: 160, renderCell: ({ value }) => <CodeText>{value}</CodeText> },
    { field: 'return_on', headerName: 'Date', width: 120, valueFormatter: (v) => formatDate(v) },
    { field: 'supplier', headerName: 'Supplier', flex: 1, minWidth: 180, sortable: false, valueGetter: (_, r) => r.supplier?.name },
    { field: 'grn', headerName: 'From GRN', width: 160, sortable: false, valueGetter: (_, r) => r.grn?.grn_no },
    { field: 'reason', headerName: 'Reason', flex: 1, minWidth: 180 },
    { field: 'status', headerName: 'Status', width: 120, renderCell: ({ value }) => <StatusChip value={value} /> },
    {
      field: 'actions', headerName: '', width: 110, sortable: false,
      renderCell: ({ row }) => row.status === 'draft' && <Button size="small" variant="outlined" onClick={(e) => { e.stopPropagation(); setPosting(row) }}>Post</Button>,
    },
  ]

  return (
    <>
      <ListPage<Rec>
        title="Supplier returns"
        subtitle="Goods sent back to a supplier from a posted GRN. Posting takes them out of stock."
        endpoint="/supplier-returns"
        queryKey={['/supplier-returns']}
        columns={columns}
        sortFields={['return_no', 'return_on']}
        defaultSort={[{ field: 'return_no', sort: 'desc' }]}
        filters={[{ name: 'status', label: 'Status', options: ['draft', 'posted'] }]}
      />
      {grn && <NewReturn grnId={grn} onClose={() => setParams({})} />}
      <ActionDialog
        action={posting && { title: `Post ${posting.return_no}`, description: 'Removes the returned quantities from stock.', confirmLabel: 'Post', run: () => run('post', `/supplier-returns/${posting.public_id}/post`, {}, [['/supplier-returns']], { idempotent: true }) }}
        onClose={() => setPosting(null)}
      />
    </>
  )
}
