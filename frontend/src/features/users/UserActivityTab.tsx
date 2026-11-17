import type { User } from '../../api/types'
import { AuditTable } from '../audit/AuditTable'

/** Actions this user performed (audit trail filtered by actor). */
export function UserActivityTab({ user }: { user: User }) {
  return <AuditTable filters={{ user: user.id }} />
}
