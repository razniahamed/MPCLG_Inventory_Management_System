import dayjs from 'dayjs'
import timezone from 'dayjs/plugin/timezone'
import utc from 'dayjs/plugin/utc'
import relativeTime from 'dayjs/plugin/relativeTime'

dayjs.extend(utc)
dayjs.extend(timezone)
dayjs.extend(relativeTime)

/** The API stores UTC; the UI shows Sri Lanka time (Section 2). */
export const DISPLAY_TZ = 'Asia/Colombo'

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return '—'
  return dayjs(value).tz(DISPLAY_TZ).format('DD MMM YYYY, HH:mm')
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return '—'
  return dayjs(value).format('DD MMM YYYY')
}

export function fromNow(value: string | null | undefined): string {
  if (!value) return 'never'
  return dayjs(value).fromNow()
}

/** Value for <input type="datetime-local"> → ISO string (interpreted as Colombo time). */
export function localInputToIso(value: string): string | null {
  if (!value) return null
  return dayjs.tz(value, DISPLAY_TZ).toISOString()
}

export function humanize(value: string): string {
  return value.replace(/[._]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

export { dayjs }
