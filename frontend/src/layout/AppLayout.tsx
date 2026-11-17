import ChevronLeftIcon from '@mui/icons-material/ChevronLeft'
import DarkModeOutlinedIcon from '@mui/icons-material/DarkModeOutlined'
import ExpandMoreIcon from '@mui/icons-material/ExpandMore'
import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown'
import LightModeOutlinedIcon from '@mui/icons-material/LightModeOutlined'
import LockResetOutlinedIcon from '@mui/icons-material/LockResetOutlined'
import LogoutOutlinedIcon from '@mui/icons-material/LogoutOutlined'
import MenuIcon from '@mui/icons-material/Menu'
import NotificationsNoneOutlinedIcon from '@mui/icons-material/NotificationsNoneOutlined'
import PersonOutlinedIcon from '@mui/icons-material/PersonOutlined'
import {
  alpha,
  AppBar,
  Avatar,
  Badge,
  Box,
  Collapse,
  Divider,
  Drawer,
  IconButton,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Menu,
  MenuItem,
  Stack,
  Toolbar,
  Tooltip,
  Typography,
  useColorScheme,
  useMediaQuery,
  useTheme,
} from '@mui/material'
import { useQuery } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { Link as RouterLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { get } from '../api/client'
import { useAuth } from '../auth/AuthContext'
import { brand } from '../theme'
import { NAVIGATION, type NavItem } from './navigation'

const DRAWER_WIDTH = 272
const COLLAPSED_WIDTH = 84

export function Logo({ size = 40 }: { size?: number }) {
  return (
    <Box
      sx={{
        width: size,
        height: size,
        borderRadius: size / 4,
        display: 'grid',
        placeItems: 'center',
        background: 'linear-gradient(135deg, #ffffff 0%, #dbeafe 100%)',
        boxShadow: '0 4px 14px rgba(0,0,0,0.25)',
        flexShrink: 0,
      }}
    >
      <svg width={size * 0.62} height={size * 0.62} viewBox="0 0 24 24" fill="none">
        <path d="M3 8.5 12 4l9 4.5v7L12 20l-9-4.5v-7Z" stroke="#1446b8" strokeWidth="1.8" strokeLinejoin="round" />
        <path d="M3 8.5 12 13l9-4.5M12 13v7" stroke="#2f6fed" strokeWidth="1.8" strokeLinejoin="round" />
      </svg>
    </Box>
  )
}

function useActive() {
  const location = useLocation()
  return (to: string) => (to === '/' ? location.pathname === '/' : location.pathname === to || location.pathname.startsWith(to + '/'))
}

function NavLinkItem({ item, collapsed, onNavigate }: { item: NavItem; collapsed: boolean; onNavigate?: () => void }) {
  const isActive = useActive()
  const active = item.phase === undefined && isActive(item.to)
  // The longest matching prefix wins (e.g. /stock vs /stock/ledger).
  const button = (
    <ListItemButton
      component={item.phase === undefined ? RouterLink : 'div'}
      to={item.phase === undefined ? item.to : undefined}
      onClick={onNavigate}
      disabled={item.phase !== undefined}
      sx={{
        mx: 1.25,
        my: 0.25,
        borderRadius: 3,
        minHeight: 42,
        color: 'rgba(225,235,255,0.82)',
        position: 'relative',
        justifyContent: collapsed ? 'center' : 'flex-start',
        '& .MuiListItemIcon-root': { color: 'inherit', minWidth: collapsed ? 0 : 38 },
        '&:hover': { bgcolor: 'rgba(255,255,255,0.08)', color: '#fff' },
        ...(active && {
          bgcolor: 'rgba(255,255,255,0.16)',
          color: '#fff',
          boxShadow: 'inset 0 0 0 1px rgba(255,255,255,0.12)',
          '&::after': { content: '""', position: 'absolute', right: -10, top: 10, bottom: 10, width: 4, borderRadius: 4, bgcolor: brand.sky },
        }),
        '&.Mui-disabled': { opacity: 0.45 },
      }}
    >
      <ListItemIcon>{item.icon}</ListItemIcon>
      {!collapsed && <ListItemText primary={item.label} slotProps={{ primary: { sx: { fontSize: 14, fontWeight: active ? 700 : 600 } } }} />}
      {!collapsed && item.phase !== undefined && (
        <Box sx={{ fontSize: 10, fontWeight: 700, px: 0.75, py: 0.25, borderRadius: 1, border: '1px solid rgba(255,255,255,0.35)' }}>P{item.phase}</Box>
      )}
    </ListItemButton>
  )

  return collapsed ? (
    <Tooltip title={item.label} placement="right">
      <Box>{button}</Box>
    </Tooltip>
  ) : (
    button
  )
}

function NavList({ collapsed, onNavigate }: { collapsed: boolean; onNavigate?: () => void }) {
  const { canAny } = useAuth()
  const isActive = useActive()
  const groups = useMemo(
    () =>
      NAVIGATION.map((g) => ({ ...g, items: g.items.filter((i) => i.phase !== undefined || i.anyOf.length === 0 || canAny(...i.anyOf)) })).filter((g) => g.items.length > 0),
    [canAny],
  )
  const [open, setOpen] = useState<Record<string, boolean>>({})
  const isOpen = (label: string, items: NavItem[]) => open[label] ?? (items.some((i) => isActive(i.to)) || ['Requests', 'Stores & stock'].includes(label))

  return (
    <Box sx={{ overflowY: 'auto', flex: 1, py: 1, '&::-webkit-scrollbar': { width: 6 }, '&::-webkit-scrollbar-thumb': { bgcolor: 'rgba(255,255,255,0.2)', borderRadius: 3 } }}>
      {groups.map((group) => (
        <Box key={group.label || 'home'} sx={{ mb: 0.5 }}>
          {group.label && !collapsed && (
            <ListItemButton
              onClick={() => setOpen((o) => ({ ...o, [group.label]: !isOpen(group.label, group.items) }))}
              sx={{ mx: 1.25, borderRadius: 2, py: 0.5, color: 'rgba(190,210,255,0.65)', '&:hover': { color: '#fff', bgcolor: 'transparent' } }}
            >
              <Typography sx={{ flex: 1, fontSize: 11, fontWeight: 800, letterSpacing: '0.12em', textTransform: 'uppercase' }}>{group.label}</Typography>
              <ExpandMoreIcon sx={{ fontSize: 18, transition: 'transform .2s', transform: isOpen(group.label, group.items) ? 'rotate(0deg)' : 'rotate(-90deg)' }} />
            </ListItemButton>
          )}
          {group.label && collapsed && <Divider sx={{ borderColor: 'rgba(255,255,255,0.1)', my: 1, mx: 2 }} />}
          <Collapse in={collapsed || !group.label || isOpen(group.label, group.items)}>
            <List dense disablePadding>
              {group.items.map((item) => (
                <NavLinkItem key={item.label} item={item} collapsed={collapsed} onNavigate={onNavigate} />
              ))}
            </List>
          </Collapse>
        </Box>
      ))}
    </Box>
  )
}

function pageTitle(pathname: string): string {
  let best: { to: string; label: string } | null = null
  for (const g of NAVIGATION) {
    for (const i of g.items) {
      if (i.phase !== undefined) continue
      const match = i.to === '/' ? pathname === '/' : pathname === i.to || pathname.startsWith(i.to + '/')
      if (match && (!best || i.to.length > best.to.length)) best = i
    }
  }
  if (pathname.startsWith('/profile')) return 'My account'
  return best?.label ?? 'MPCLG Inventory'
}

export function AppLayout() {
  const theme = useTheme()
  const desktop = useMediaQuery(theme.breakpoints.up('lg'))
  const [mobileOpen, setMobileOpen] = useState(false)
  const [collapsed, setCollapsed] = useState(false)
  const [menuAnchor, setMenuAnchor] = useState<HTMLElement | null>(null)
  const { me, logout, can } = useAuth()
  const { mode, systemMode, setMode } = useColorScheme()
  const navigate = useNavigate()
  const location = useLocation()

  const tasks = useQuery({
    queryKey: ['dashboard'],
    queryFn: () => get<{ tasks: { count: number }[] }>('/dashboard'),
    enabled: can('dashboard.view'),
    refetchInterval: 120_000,
  })
  const pending = (tasks.data?.tasks ?? []).reduce((n, t) => n + t.count, 0)

  const effectiveMode = mode === 'system' ? systemMode : mode
  const user = me!.user
  const displayName = user.officer?.full_name ?? user.username
  const initials = displayName.split(/\s+/).map((p) => p[0]).slice(0, 2).join('').toUpperCase()
  const roleLine = user.roles?.map((r) => r.role?.name).filter(Boolean)[0] ?? 'No role'
  const width = desktop && collapsed ? COLLAPSED_WIDTH : DRAWER_WIDTH
  const thin = desktop && collapsed

  const drawer = (
    <Box sx={{ display: 'flex', flexDirection: 'column', height: '100%', background: brand.sidebar, color: '#fff' }}>
      <Stack direction="row" sx={{ alignItems: 'center', gap: 1.5, px: thin ? 0 : 2.5, height: 72, justifyContent: thin ? 'center' : 'flex-start' }}>
        <Logo />
        {!thin && (
          <Box sx={{ lineHeight: 1.1, flex: 1, minWidth: 0 }}>
            <Typography noWrap sx={{ fontWeight: 800, fontSize: 16, letterSpacing: '-0.01em' }}>MPCLG Inventory</Typography>
            <Typography noWrap sx={{ fontSize: 11, color: brand.ice, fontWeight: 600 }}>Stores & Asset Management</Typography>
          </Box>
        )}
        {desktop && !thin && (
          <IconButton size="small" onClick={() => setCollapsed(true)} sx={{ color: 'rgba(255,255,255,0.7)' }} aria-label="Collapse sidebar">
            <ChevronLeftIcon />
          </IconButton>
        )}
      </Stack>
      <Divider sx={{ borderColor: 'rgba(255,255,255,0.1)' }} />
      <NavList collapsed={thin} onNavigate={() => setMobileOpen(false)} />
      <Box sx={{ p: 1.5, borderTop: '1px solid rgba(255,255,255,0.1)' }}>
        {thin ? (
          <Tooltip title="Sign out" placement="right">
            <IconButton onClick={() => void logout()} sx={{ color: '#fff', mx: 'auto', display: 'flex' }}>
              <LogoutOutlinedIcon />
            </IconButton>
          </Tooltip>
        ) : (
          <Stack direction="row" sx={{ alignItems: 'center', gap: 1, p: 1.5, borderRadius: 3, bgcolor: 'rgba(255,255,255,0.08)', border: '1px solid rgba(255,255,255,0.1)' }}>
            <Box sx={{ flex: 1, minWidth: 0 }}>
              <Typography sx={{ fontSize: 13, fontWeight: 700 }}>Sign out</Typography>
              <Typography noWrap sx={{ fontSize: 11, color: 'rgba(220,230,255,0.7)' }}>
                {user.email}
              </Typography>
            </Box>
            <IconButton onClick={() => void logout()} sx={{ color: '#fff', bgcolor: 'rgba(255,255,255,0.1)', '&:hover': { bgcolor: 'rgba(255,255,255,0.2)' } }} aria-label="Sign out">
              <LogoutOutlinedIcon fontSize="small" />
            </IconButton>
          </Stack>
        )}
        {!thin && <Typography sx={{ textAlign: 'center', fontSize: 11, color: 'rgba(200,215,255,0.6)', mt: 1.25 }}>Powered by Ministry IT Division</Typography>}
      </Box>
    </Box>
  )

  return (
    <Box sx={{ display: 'flex', minHeight: '100vh' }}>
      <AppBar position="fixed" elevation={0} sx={{ width: { lg: `calc(100% - ${width}px)` }, ml: { lg: `${width}px` }, background: brand.topbar, transition: 'width .2s, margin .2s' }}>
        <Toolbar sx={{ gap: 1, minHeight: { xs: 64, sm: 72 } }}>
          {(!desktop || collapsed) && (
            <IconButton edge="start" color="inherit" onClick={() => (desktop ? setCollapsed(false) : setMobileOpen(true))} aria-label="Open navigation">
              <MenuIcon />
            </IconButton>
          )}
          <Typography sx={{ fontSize: 20, fontWeight: 800, flex: 1, letterSpacing: '-0.01em' }} noWrap>
            {pageTitle(location.pathname)}
          </Typography>
          <Tooltip title={effectiveMode === 'dark' ? 'Light mode' : 'Dark mode'}>
            <IconButton color="inherit" onClick={() => setMode(effectiveMode === 'dark' ? 'light' : 'dark')} sx={{ bgcolor: 'rgba(255,255,255,0.08)', '&:hover': { bgcolor: 'rgba(255,255,255,0.16)' } }}>
              {effectiveMode === 'dark' ? <LightModeOutlinedIcon /> : <DarkModeOutlinedIcon />}
            </IconButton>
          </Tooltip>
          {can('dashboard.view') && (
            <Tooltip title={pending ? `${pending} item(s) waiting for you` : 'Nothing waiting'}>
              <IconButton color="inherit" onClick={() => navigate('/')} sx={{ bgcolor: 'rgba(255,255,255,0.08)', '&:hover': { bgcolor: 'rgba(255,255,255,0.16)' } }}>
                <Badge color="error" variant={pending ? 'dot' : 'standard'} invisible={!pending}>
                  <NotificationsNoneOutlinedIcon />
                </Badge>
              </IconButton>
            </Tooltip>
          )}
          <Box
            onClick={(e) => setMenuAnchor(e.currentTarget)}
            sx={{
              display: 'flex',
              alignItems: 'center',
              gap: 1.25,
              cursor: 'pointer',
              borderRadius: 99,
              pl: 0.5,
              pr: { xs: 0.5, sm: 1.5 },
              py: 0.5,
              ml: 0.5,
              bgcolor: 'rgba(255,255,255,0.1)',
              border: '1px solid rgba(255,255,255,0.18)',
              '&:hover': { bgcolor: 'rgba(255,255,255,0.18)' },
            }}
          >
            <Avatar sx={{ width: 38, height: 38, fontSize: 14, fontWeight: 800, background: 'linear-gradient(135deg, #5ab0ff, #1446b8)', border: '2px solid rgba(255,255,255,0.6)' }}>{initials}</Avatar>
            <Box sx={{ display: { xs: 'none', sm: 'block' }, lineHeight: 1.15 }}>
              <Typography sx={{ fontSize: 14, fontWeight: 700 }}>{displayName}</Typography>
              <Typography sx={{ fontSize: 12, color: brand.ice }}>
                {roleLine}
                {user.officer ? ` · ${user.officer.officer_no}` : ''}
              </Typography>
            </Box>
            <KeyboardArrowDownIcon sx={{ display: { xs: 'none', sm: 'block' }, opacity: 0.8 }} />
          </Box>
          <Menu anchorEl={menuAnchor} open={menuAnchor !== null} onClose={() => setMenuAnchor(null)} slotProps={{ paper: { sx: { mt: 1, borderRadius: 3, minWidth: 200 } } }}>
            <MenuItem onClick={() => { setMenuAnchor(null); navigate('/profile') }}>
              <ListItemIcon><PersonOutlinedIcon fontSize="small" /></ListItemIcon>
              My account
            </MenuItem>
            <MenuItem onClick={() => { setMenuAnchor(null); navigate('/change-password') }}>
              <ListItemIcon><LockResetOutlinedIcon fontSize="small" /></ListItemIcon>
              Change password
            </MenuItem>
            <Divider />
            <MenuItem onClick={() => { setMenuAnchor(null); void logout() }}>
              <ListItemIcon><LogoutOutlinedIcon fontSize="small" /></ListItemIcon>
              Sign out
            </MenuItem>
          </Menu>
        </Toolbar>
      </AppBar>

      <Box component="nav" sx={{ width: { lg: width }, flexShrink: { lg: 0 }, transition: 'width .2s' }}>
        {desktop ? (
          <Drawer variant="permanent" open sx={{ '& .MuiDrawer-paper': { width, boxSizing: 'border-box', border: 0, transition: 'width .2s', overflowX: 'hidden' } }}>
            {drawer}
          </Drawer>
        ) : (
          <Drawer variant="temporary" open={mobileOpen} onClose={() => setMobileOpen(false)} ModalProps={{ keepMounted: true }} sx={{ '& .MuiDrawer-paper': { width: DRAWER_WIDTH, border: 0 } }}>
            {drawer}
          </Drawer>
        )}
      </Box>

      <Box sx={{ flexGrow: 1, minWidth: 0, width: { xs: '100%', lg: `calc(100% - ${width}px)` }, display: 'flex', flexDirection: 'column', minHeight: '100vh' }}>
        <Box component="main" sx={{ flex: 1, p: { xs: 2, sm: 3, md: 4 }, pt: { xs: 10, sm: 12 }, maxWidth: 1600, width: '100%', mx: 'auto' }}>
          <Outlet />
        </Box>
        <Box
          component="footer"
          sx={{
            background: brand.topbar,
            color: 'rgba(230,238,255,0.9)',
            px: { xs: 2, sm: 4 },
            py: 1.5,
            display: 'flex',
            justifyContent: 'space-between',
            gap: 2,
            fontSize: 13,
            fontWeight: 600,
            borderTop: `1px solid ${alpha('#ffffff', 0.08)}`,
          }}
        >
          <span>© {new Date().getFullYear()} Ministry of Provincial Councils and Local Government. All rights reserved.</span>
          <span style={{ whiteSpace: 'nowrap' }}>Version 1.0</span>
        </Box>
      </Box>
    </Box>
  )
}
