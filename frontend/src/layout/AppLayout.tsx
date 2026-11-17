import DarkModeOutlinedIcon from '@mui/icons-material/DarkModeOutlined'
import LightModeOutlinedIcon from '@mui/icons-material/LightModeOutlined'
import LockResetOutlinedIcon from '@mui/icons-material/LockResetOutlined'
import LogoutOutlinedIcon from '@mui/icons-material/LogoutOutlined'
import MenuIcon from '@mui/icons-material/Menu'
import PersonOutlineIcon from '@mui/icons-material/PersonOutlined'
import {
  AppBar,
  Avatar,
  Box,
  Chip,
  Divider,
  Drawer,
  IconButton,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  ListSubheader,
  Menu,
  MenuItem,
  Toolbar,
  Tooltip,
  Typography,
  useColorScheme,
  useMediaQuery,
  useTheme,
} from '@mui/material'
import { useState } from 'react'
import { Link as RouterLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/AuthContext'
import { NAVIGATION } from './navigation'

const DRAWER_WIDTH = 264

function Brand() {
  return (
    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, px: 2.5, height: 64 }}>
      <Box
        sx={{
          width: 36,
          height: 36,
          borderRadius: 1.5,
          bgcolor: 'primary.main',
          color: 'primary.contrastText',
          display: 'grid',
          placeItems: 'center',
          fontWeight: 800,
          fontSize: 14,
        }}
      >
        MI
      </Box>
      <Box sx={{ lineHeight: 1.1 }}>
        <Typography sx={{ fontSize: 15, fontWeight: 700 }}>
          MPCLG Inventory
        </Typography>
        <Typography sx={{ fontSize: 11 }} color="text.secondary">
          Stores & Asset Management
        </Typography>
      </Box>
    </Box>
  )
}

function NavList({ onNavigate }: { onNavigate?: () => void }) {
  const { canAny } = useAuth()
  const location = useLocation()

  const isActive = (to: string) => (to === '/' ? location.pathname === '/' : location.pathname.startsWith(to))

  return (
    <Box sx={{ overflowY: 'auto', flex: 1, pb: 2 }}>
      {NAVIGATION.map((group) => {
        const items = group.items.filter((i) => i.phase !== undefined || i.anyOf.length === 0 || canAny(...i.anyOf))
        if (items.length === 0) return null
        return (
          <List
            key={group.label || 'main'}
            dense
            subheader={
              group.label ? (
                <ListSubheader sx={{ bgcolor: 'transparent', lineHeight: '32px', fontSize: 11, textTransform: 'uppercase', letterSpacing: 0.6 }}>
                  {group.label}
                </ListSubheader>
              ) : undefined
            }
          >
            {items.map((item) =>
              item.phase !== undefined ? (
                <ListItemButton key={item.label} disabled sx={{ mx: 1, borderRadius: 1.5 }}>
                  <ListItemIcon sx={{ minWidth: 36 }}>{item.icon}</ListItemIcon>
                  <ListItemText primary={item.label} />
                  <Chip label={`P${item.phase}`} variant="outlined" sx={{ height: 18, fontSize: 10 }} />
                </ListItemButton>
              ) : (
                <ListItemButton
                  key={item.label}
                  component={RouterLink}
                  to={item.to}
                  selected={isActive(item.to)}
                  onClick={onNavigate}
                  sx={{
                    mx: 1,
                    borderRadius: 1.5,
                    '&.Mui-selected': { bgcolor: 'primary.main', color: 'primary.contrastText', '& .MuiListItemIcon-root': { color: 'inherit' } },
                    '&.Mui-selected:hover': { bgcolor: 'primary.dark' },
                  }}
                >
                  <ListItemIcon sx={{ minWidth: 36 }}>{item.icon}</ListItemIcon>
                  <ListItemText primary={item.label} />
                </ListItemButton>
              ),
            )}
          </List>
        )
      })}
    </Box>
  )
}

export function AppLayout() {
  const theme = useTheme()
  const desktop = useMediaQuery(theme.breakpoints.up('lg'))
  const [mobileOpen, setMobileOpen] = useState(false)
  const [menuAnchor, setMenuAnchor] = useState<HTMLElement | null>(null)
  const { me, logout } = useAuth()
  const { mode, systemMode, setMode } = useColorScheme()
  const navigate = useNavigate()

  const effectiveMode = mode === 'system' ? systemMode : mode
  const user = me!.user
  const initials = (user.officer?.full_name ?? user.username).split(/\s+/).map((p) => p[0]).slice(0, 2).join('').toUpperCase()

  const drawer = (
    <Box sx={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
      <Brand />
      <Divider />
      <NavList onNavigate={() => setMobileOpen(false)} />
    </Box>
  )

  return (
    <Box sx={{ display: 'flex', minHeight: '100vh' }}>
      <AppBar
        position="fixed"
        color="inherit"
        sx={{ width: { lg: `calc(100% - ${DRAWER_WIDTH}px)` }, ml: { lg: `${DRAWER_WIDTH}px` }, borderBottom: 1, borderColor: 'divider' }}
        elevation={0}
      >
        <Toolbar sx={{ gap: 1 }}>
          {!desktop && (
            <IconButton edge="start" onClick={() => setMobileOpen(true)} aria-label="Open navigation">
              <MenuIcon />
            </IconButton>
          )}
          <Box sx={{ flex: 1 }} />
          <Tooltip title={effectiveMode === 'dark' ? 'Light mode' : 'Dark mode'}>
            <IconButton onClick={() => setMode(effectiveMode === 'dark' ? 'light' : 'dark')}>
              {effectiveMode === 'dark' ? <LightModeOutlinedIcon /> : <DarkModeOutlinedIcon />}
            </IconButton>
          </Tooltip>
          <Box
            onClick={(e) => setMenuAnchor(e.currentTarget)}
            sx={{ display: 'flex', alignItems: 'center', gap: 1, cursor: 'pointer', borderRadius: 2, px: 1, py: 0.5, '&:hover': { bgcolor: 'action.hover' } }}
          >
            <Avatar sx={{ width: 32, height: 32, fontSize: 13, bgcolor: 'secondary.main' }}>{initials}</Avatar>
            <Box sx={{ display: { xs: 'none', sm: 'block' }, lineHeight: 1.2 }}>
              <Typography sx={{ fontSize: 14, fontWeight: 600 }}>
                {user.officer?.full_name ?? user.username}
              </Typography>
              <Typography sx={{ fontSize: 12 }} color="text.secondary">
                {user.roles?.map((r) => r.role?.name).filter(Boolean).join(', ') || 'No role'}
              </Typography>
            </Box>
          </Box>
          <Menu anchorEl={menuAnchor} open={menuAnchor !== null} onClose={() => setMenuAnchor(null)}>
            <MenuItem onClick={() => { setMenuAnchor(null); navigate('/profile') }}>
              <ListItemIcon><PersonOutlineIcon fontSize="small" /></ListItemIcon>
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

      <Box component="nav" sx={{ width: { lg: DRAWER_WIDTH }, flexShrink: { lg: 0 } }}>
        {desktop ? (
          <Drawer variant="permanent" open sx={{ '& .MuiDrawer-paper': { width: DRAWER_WIDTH, boxSizing: 'border-box' } }}>
            {drawer}
          </Drawer>
        ) : (
          <Drawer variant="temporary" open={mobileOpen} onClose={() => setMobileOpen(false)} ModalProps={{ keepMounted: true }} sx={{ '& .MuiDrawer-paper': { width: DRAWER_WIDTH } }}>
            {drawer}
          </Drawer>
        )}
      </Box>

      <Box component="main" sx={{ flexGrow: 1, minWidth: 0, width: { xs: '100%', lg: `calc(100% - ${DRAWER_WIDTH}px)` }, p: { xs: 2, sm: 3 }, pt: { xs: 10, sm: 11 } }}>
        <Outlet />
      </Box>
    </Box>
  )
}
