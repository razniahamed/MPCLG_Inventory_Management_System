import AddIcon from '@mui/icons-material/Add'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlined'
import { Box, Button, Grid, IconButton, Paper, Stack, TextField, Typography } from '@mui/material'
import { ItemPicker, UnitSelect, type ItemOption } from './Pickers'

export interface LineDraft {
  key: string
  item: ItemOption | null
  unit_id: number | ''
  qty: string
  unit_price?: string
  remarks?: string
}

export const newLine = (): LineDraft => ({ key: crypto.randomUUID(), item: null, unit_id: '', qty: '', unit_price: '', remarks: '' })

/** Item + unit + quantity (+ price) rows for documents. */
export function LineItemsEditor({
  lines,
  onChange,
  qtyLabel = 'Quantity',
  withPrice = false,
  withRemarks = false,
  itemFilters,
  defaultUnit = 'issue',
}: {
  lines: LineDraft[]
  onChange: (lines: LineDraft[]) => void
  qtyLabel?: string
  withPrice?: boolean
  withRemarks?: boolean
  itemFilters?: Record<string, string | boolean>
  defaultUnit?: 'issue' | 'purchase' | 'base'
}) {
  const update = (key: string, patch: Partial<LineDraft>) => onChange(lines.map((l) => (l.key === key ? { ...l, ...patch } : l)))

  return (
    <Stack spacing={1.5}>
      {lines.map((line, i) => (
        <Paper key={line.key} variant="outlined" sx={{ p: 1.5, borderRadius: 3 }}>
          <Grid container spacing={1.5} sx={{ alignItems: 'center' }}>
            <Grid size={{ xs: 12, md: withPrice ? 5 : 6 }}>
              <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                <Typography sx={{ fontWeight: 700, color: 'text.secondary', width: 22 }}>{i + 1}</Typography>
                <Box sx={{ flex: 1 }}>
                  <ItemPicker
                    value={line.item}
                    filters={itemFilters}
                    onChange={(item) =>
                      update(line.key, {
                        item,
                        unit_id: item ? (defaultUnit === 'purchase' ? item.purchase_unit_id : defaultUnit === 'base' ? item.base_unit_id : item.issue_unit_id) : '',
                      })
                    }
                  />
                </Box>
              </Stack>
            </Grid>
            <Grid size={{ xs: 6, md: withPrice ? 2 : 3 }}>
              <UnitSelect item={line.item} value={line.unit_id} onChange={(id) => update(line.key, { unit_id: id })} />
            </Grid>
            <Grid size={{ xs: 6, md: 2 }}>
              <TextField label={qtyLabel} type="number" value={line.qty} onChange={(e) => update(line.key, { qty: e.target.value })} slotProps={{ htmlInput: { min: 0, step: 'any' } }} />
            </Grid>
            {withPrice && (
              <Grid size={{ xs: 10, md: 2 }}>
                <TextField label="Unit price (Rs.)" type="number" value={line.unit_price} onChange={(e) => update(line.key, { unit_price: e.target.value })} />
              </Grid>
            )}
            <Grid size={{ xs: 2, md: 1 }} sx={{ textAlign: 'right' }}>
              <IconButton onClick={() => onChange(lines.filter((l) => l.key !== line.key))} disabled={lines.length === 1} aria-label="Remove line">
                <DeleteOutlineIcon />
              </IconButton>
            </Grid>
            {withRemarks && (
              <Grid size={12}>
                <TextField label="Remarks" value={line.remarks} onChange={(e) => update(line.key, { remarks: e.target.value })} />
              </Grid>
            )}
          </Grid>
        </Paper>
      ))}
      <Button startIcon={<AddIcon />} onClick={() => onChange([...lines, newLine()])} sx={{ alignSelf: 'flex-start' }}>
        Add line
      </Button>
    </Stack>
  )
}

export const linesValid = (lines: LineDraft[]) => lines.length > 0 && lines.every((l) => l.item && l.unit_id && Number(l.qty) > 0)
