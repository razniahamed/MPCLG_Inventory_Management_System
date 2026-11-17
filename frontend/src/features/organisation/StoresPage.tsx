import AddIcon from '@mui/icons-material/Add'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlined'
import PeopleOutlineIcon from '@mui/icons-material/PeopleOutlined'
import { Alert, Button, Dialog, DialogActions, DialogContent, DialogTitle, Grid, IconButton, MenuItem, Skeleton, Stack, TextField, Tooltip, Typography } from '@mui/material'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { get, send } from '../../api/client'
import type { MasterRecord, Officer } from '../../api/types'
import { useAuth } from '../../auth/AuthContext'
import { errorMessage, useNotify } from '../../components/Notify'
import { useLookup } from '../../hooks/useLookup'
import { storesConfig } from './configs'
import { MasterDataPage } from './MasterDataPage'

interface StoreOfficerRow {
  officer_id: number | ''
  role: string
  valid_from: string
  valid_to: string
}

function StoreOfficersDialog({ store, onClose }: { store: MasterRecord; onClose: () => void }) {
  const { can } = useAuth()
  const notify = useNotify()
  const queryClient = useQueryClient()
  const manage = can('store.manage')
  const officers = useLookup<Officer>('/officers', { sort: 'full_name' })
  const current = useQuery({
    queryKey: ['store-officers', store.id],
    queryFn: () => get<{ officer_id: number; role: string; valid_from: string | null; valid_to: string | null }[]>(`/stores/${store.id}/officers`),
  })
  const [rows, setRows] = useState<StoreOfficerRow[]>([])
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<unknown>(null)

  useEffect(() => {
    if (current.data) {
      setRows(current.data.map((r) => ({ officer_id: r.officer_id, role: r.role, valid_from: r.valid_from?.slice(0, 10) ?? '', valid_to: r.valid_to?.slice(0, 10) ?? '' })))
    }
  }, [current.data])

  const update = (i: number, patch: Partial<StoreOfficerRow>) => setRows((r) => r.map((x, j) => (j === i ? { ...x, ...patch } : x)))

  const save = async () => {
    setBusy(true)
    setError(null)
    try {
      await send('put', `/stores/${store.id}/officers`, {
        officers: rows.filter((r) => r.officer_id !== '').map((r) => ({ officer_id: r.officer_id, role: r.role || 'storekeeper', valid_from: r.valid_from || null, valid_to: r.valid_to || null })),
      })
      await queryClient.invalidateQueries({ queryKey: ['store-officers', store.id] })
      notify.success('Store officers saved.')
      onClose()
    } catch (e) {
      setError(e)
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open onClose={() => !busy && onClose()} maxWidth="md" fullWidth>
      <DialogTitle>Officers working in {String(store.name)}</DialogTitle>
      <DialogContent dividers>
        <Typography color="text.secondary" sx={{ fontSize: 14, mb: 2 }}>
          This records who works in the store. What they may do is controlled by their roles — give a Storekeeper the storekeeper role limited to this store on their user page.
        </Typography>
        {current.isLoading ? (
          <Skeleton height={120} />
        ) : (
          <Stack spacing={1.5}>
            {rows.map((row, i) => (
              <Grid key={i} container spacing={1.5} sx={{ alignItems: 'center' }}>
                <Grid size={{ xs: 12, md: 4 }}>
                  <TextField select label="Officer" value={row.officer_id} onChange={(e) => update(i, { officer_id: Number(e.target.value) })} disabled={!manage}>
                    {(officers.data ?? []).map((o) => (
                      <MenuItem key={o.id} value={o.id}>
                        {o.full_name} ({o.officer_no})
                      </MenuItem>
                    ))}
                  </TextField>
                </Grid>
                <Grid size={{ xs: 12, md: 2.5 }}>
                  <TextField label="Role here" value={row.role} onChange={(e) => update(i, { role: e.target.value })} disabled={!manage} />
                </Grid>
                <Grid size={{ xs: 6, md: 2.5 }}>
                  <TextField type="date" label="From" value={row.valid_from} onChange={(e) => update(i, { valid_from: e.target.value })} disabled={!manage} slotProps={{ inputLabel: { shrink: true } }} />
                </Grid>
                <Grid size={{ xs: 5, md: 2.5 }}>
                  <TextField type="date" label="Until" value={row.valid_to} onChange={(e) => update(i, { valid_to: e.target.value })} disabled={!manage} slotProps={{ inputLabel: { shrink: true } }} />
                </Grid>
                <Grid size={{ xs: 1, md: 0.5 }}>
                  {manage && (
                    <IconButton onClick={() => setRows((r) => r.filter((_, j) => j !== i))} aria-label="Remove">
                      <DeleteOutlineIcon />
                    </IconButton>
                  )}
                </Grid>
              </Grid>
            ))}
            {rows.length === 0 && <Typography color="text.secondary">No officers recorded for this store.</Typography>}
            {manage && (
              <Button startIcon={<AddIcon />} onClick={() => setRows((r) => [...r, { officer_id: '', role: 'storekeeper', valid_from: '', valid_to: '' }])} sx={{ alignSelf: 'flex-start' }}>
                Add officer
              </Button>
            )}
            {error !== null && <Alert severity="error">{errorMessage(error)}</Alert>}
          </Stack>
        )}
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} disabled={busy}>
          {manage ? 'Cancel' : 'Close'}
        </Button>
        {manage && (
          <Button variant="contained" onClick={save} disabled={busy}>
            {busy ? 'Saving…' : 'Save'}
          </Button>
        )}
      </DialogActions>
    </Dialog>
  )
}

export function StoresPage() {
  const [store, setStore] = useState<MasterRecord | null>(null)

  return (
    <>
      <MasterDataPage
        config={{
          ...storesConfig,
          rowActions: (row) => (
            <Tooltip title="Store officers">
              <IconButton size="small" onClick={() => setStore(row)}>
                <PeopleOutlineIcon fontSize="small" />
              </IconButton>
            </Tooltip>
          ),
        }}
      />
      {store && <StoreOfficersDialog store={store} onClose={() => setStore(null)} />}
    </>
  )
}
