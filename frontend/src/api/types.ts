// Shapes returned by the Laravel API (Section 4 envelopes and Phase 1 resources).

export interface Envelope<T> {
  success: true
  message: string
  data: T
  meta: { request_id: string; warnings?: string[] }
}

export interface PageMeta {
  page: number
  per_page: number
  total: number
  last_page: number
  request_id: string
}

export interface Paged<T> {
  success: true
  message: string
  data: T[]
  meta: PageMeta
}

export type UserStatus = 'pending' | 'active' | 'suspended' | 'deactivated'
export type ScopeType = 'global' | 'division' | 'department' | 'store'

export interface UserRoleAssignment {
  id: number
  role?: { id: number; code: string; name: string }
  scope_type: ScopeType
  scope_id: number | null
  valid_from: string | null
  valid_to: string | null
  is_current: boolean
  granted_at: string | null
  revoked_at: string | null
  revoke_reason: string | null
}

export interface User {
  id: string // public_id (ULID)
  username: string
  email: string
  status: UserStatus
  is_locked: boolean
  locked_until: string | null
  must_change_password: boolean
  password_changed_at: string | null
  mfa_enabled: boolean
  last_login_at: string | null
  last_login_ip: string | null
  officer_id: number | null
  officer?: {
    id: number
    officer_no: string
    full_name: string
    designation: string | null
    department: string | null
  } | null
  roles?: UserRoleAssignment[]
  deactivated_at: string | null
  deactivation_reason: string | null
  created_at: string | null
  updated_at: string | null
  temporary_password?: string
}

export interface PermissionScope {
  scope_type: ScopeType
  scope_id: number | null
  source: 'role' | 'override' | 'delegation'
}

export interface Me {
  user: User
  permissions: string[]
  permission_scopes: Record<string, PermissionScope[]>
  modules: { code: string; name: string; is_enabled: boolean }[]
}

export interface Permission {
  id: number
  code: string
  name: string
  description: string | null
  module?: string
  resource: string
  action: string
  is_sensitive: boolean
  supports_scope: boolean
  is_enabled: boolean
}

export interface Module {
  id: number
  code: string
  name: string
  is_enabled: boolean
  sort_order: number
  permissions?: Permission[]
}

export interface Role {
  id: number
  code: string
  name: string
  description: string | null
  is_system: boolean
  is_active: boolean
  permissions?: string[]
  active_assignments_count?: number
  created_at: string | null
}

export interface UserOverride {
  id: number
  permission?: string
  effect: 'allow' | 'deny'
  scope_type: ScopeType
  scope_id: number | null
  valid_from: string | null
  valid_to: string | null
  is_current: boolean
  reason: string
  granted_at: string | null
  revoked_at: string | null
}

export interface EffectivePermissionRow {
  code: string
  module: string
  allowed: boolean
  reason: string
  sources: {
    source: 'role' | 'override' | 'delegation'
    label: string
    effect: 'allow' | 'deny'
    scope_type: ScopeType
    scope_id: number | null
    valid_to: string | null
  }[]
}

export interface Delegation {
  id: number
  delegator?: { public_id: string; username: string }
  delegate?: { public_id: string; username: string }
  permission?: string | null
  covers: 'single_permission' | 'all_approval_permissions'
  scope_type: ScopeType
  scope_id: number | null
  valid_from: string
  valid_to: string
  written_reference: string
  status: 'active' | 'expired' | 'revoked'
  revoked_at: string | null
  created_at: string | null
}

export interface AuditLog {
  id: number
  event_at: string
  user?: { public_id: string; username: string } | null
  action: string
  auditable_type: string
  auditable_id: number
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  reason: string | null
  ip_address: string | null
  request_id: string | null
}

export interface Setting {
  key: string
  group: string
  type: 'string' | 'integer' | 'boolean' | 'json'
  value: unknown
  default: unknown
  description: string
  updated_at: string | null
}

export interface OfficerAssignment {
  id: number
  officer_id: number
  designation_id: number
  designation?: { id: number; code: string; name: string } | null
  department_id: number
  department?: { id: number; code: string; name_en: string } | null
  from_on: string
  to_on: string | null
  is_current: boolean
  authority_ref: string | null
}

export interface Officer {
  id: number
  officer_no: string
  full_name: string
  name_with_initials: string | null
  designation_id: number
  designation?: { id: number; code: string; name: string } | null
  department_id: number
  department?: { id: number; code: string; name_en: string } | null
  public_service_id: number | null
  service_grade_id: number | null
  work_phone: string | null
  work_email: string | null
  employment_status: 'active' | 'transferred' | 'retired' | 'resigned' | 'suspended'
  joined_on: string | null
  left_on: string | null
  is_active: boolean
  has_user_account?: boolean
  assignments?: OfficerAssignment[]
}

/** Generic master record (divisions, sites, stores …). */
export type MasterRecord = { id: number; is_active?: boolean } & Record<string, unknown>
