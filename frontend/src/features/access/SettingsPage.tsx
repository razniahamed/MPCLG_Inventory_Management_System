import { Alert, Box, Button, Card, CardContent, Chip, MenuItem, Skeleton, Stack, Switch, TextField, Typography } from '@mui/material'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { get, send } from '../../api/client'
import type { Setting } from '../../api/types'
import { useNotify } from '../../components/Notify'
import { PageHeader } from '../../components/PageHeader'
import { ReasonDialog } from '../../components/ReasonDialog'
import { formatDateTime, humanize } from '../../utils/format'

const SELECT_OPTIONS: Record<string, { value: string; label: string }[]> = {
  'a4.rule_mode': [
    { value: 'per_request', label: 'Readings with every request' },
    { value: 'monthly', label: 'Monthly readings' },
  ],
}

export function SettingsPage() {
  const notify = useNotify()
  const queryClient = useQueryClient()
  const settings = useQuery({ queryKey: ['settings'], queryFn: () => get<Setting[]>('/settings') })
  const [values, setValues] = useState<Record<string, unknown>>({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (settings.data) setValues(Object.fromEntries(settings.data.map((s) => [s.key, s.value])))
  }, [settings.data])

  if (settings.isLoading) return <Skeleton height={400} />

  const changed = (settings.data ?? []).filter((s) => JSON.stringify(values[s.key]) !== JSON.stringify(s.value))
  const groups = [...new Set((settings.data ?? []).map((s) => s.group))]

  return (
    <>
      <PageHeader
        title="System settings"
        subtitle="Security policy and ministry-level switches. Changes are audited."
        actions={
          <>
            <Button disabled={changed.length === 0} onClick={() => setValues(Object.fromEntries((settings.data ?? []).map((s) => [s.key, s.value])))}>
              Undo
            </Button>
            <Button variant="contained" disabled={changed.length === 0} onClick={() => setSaving(true)}>
              Save {changed.length > 0 && `(${changed.length})`}
            </Button>
          </>
        }
      />
      <Stack spacing={2}>
        {groups.map((g) => (
          <Card key={g}>
            <CardContent>
              <Typography variant="h6" sx={{ fontSize: 16, mb: 1 }}>
                {humanize(g)}
              </Typography>
              {(settings.data ?? [])
                .filter((s) => s.group === g)
                .map((s) => {
                  const v = values[s.key]
                  const isChanged = changed.includes(s)
                  let input
                  if (s.type === 'boolean') {
                    input = <Switch checked={Boolean(v)} onChange={(e) => setValues((x) => ({ ...x, [s.key]: e.target.checked }))} />
                  } else if (SELECT_OPTIONS[s.key]) {
                    input = (
                      <TextField select value={v ?? ''} onChange={(e) => setValues((x) => ({ ...x, [s.key]: e.target.value }))} sx={{ width: { xs: '100%', sm: 260 } }}>
                        {SELECT_OPTIONS[s.key].map((o) => (
                          <MenuItem key={o.value} value={o.value}>
                            {o.label}
                          </MenuItem>
                        ))}
                      </TextField>
                    )
                  } else {
                    input = (
                      <TextField
                        type={s.type === 'integer' ? 'number' : 'text'}
                        value={v ?? ''}
                        onChange={(e) => setValues((x) => ({ ...x, [s.key]: s.type === 'integer' ? Number(e.target.value) : e.target.value }))}
                        sx={{ width: s.type === 'integer' ? 140 : 420 }}
                      />
                    )
                  }
                  return (
                    <Stack key={s.key} direction={{ xs: 'column', md: 'row' }} spacing={2} sx={{ py: 1.5, borderTop: 1, borderColor: 'divider', alignItems: { md: 'center' } }}>
                      <Box sx={{ flex: 1 }}>
                        <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                          <Typography sx={{ fontSize: 14, fontWeight: 600, fontFamily: 'monospace' }}>
                            {s.key}
                          </Typography>
                          {isChanged && <Chip label="changed" color="warning" />}
                        </Stack>
                        <Typography sx={{ fontSize: 13 }} color="text.secondary">
                          {s.description} Default: {String(s.default)}. {s.updated_at && `Last changed ${formatDateTime(s.updated_at)}.`}
                        </Typography>
                      </Box>
                      {input}
                    </Stack>
                  )
                })}
            </CardContent>
          </Card>
        ))}
      </Stack>
      <ReasonDialog
        open={saving}
        title="Save settings"
        confirmLabel="Save"
        onClose={() => setSaving(false)}
        onConfirm={async (reason) => {
          await send('patch', '/settings', { settings: changed.map((s) => ({ key: s.key, value: values[s.key] })), reason })
          await queryClient.invalidateQueries({ queryKey: ['settings'] })
          notify.success('Settings saved.')
        }}
      >
        <Alert severity="info" sx={{ mb: 1 }}>
          Changing: {changed.map((s) => s.key).join(', ')}
        </Alert>
      </ReasonDialog>
    </>
  )
}
