import { alpha, createTheme } from '@mui/material/styles'

/**
 * Ministry blue: deep navy → royal blue gradients for the chrome (sidebar,
 * top bar, footer, hero cards), soft rounded white cards on a cool grey canvas.
 */
export const brand = {
  navy: '#061a4d',
  deep: '#0a2a7a',
  royal: '#1446b8',
  bright: '#2f6fed',
  sky: '#5ab0ff',
  ice: '#9fd3ff',
  sidebar: 'linear-gradient(180deg, #051640 0%, #0a2a7a 48%, #1446b8 100%)',
  topbar: 'linear-gradient(90deg, #051640 0%, #0a2a7a 55%, #1a56d6 100%)',
  hero: 'linear-gradient(135deg, #0b1a3d 0%, #10285f 55%, #173c8f 100%)',
  login: 'radial-gradient(1200px 600px at 10% 10%, #0b2a8a 0%, transparent 60%), radial-gradient(900px 600px at 90% 90%, #1446b8 0%, transparent 55%), linear-gradient(135deg, #020a2e 0%, #061a4d 50%, #0a2a7a 100%)',
}

export const theme = createTheme({
  cssVariables: { colorSchemeSelector: 'class' },
  colorSchemes: {
    light: {
      palette: {
        primary: { main: '#1446b8', light: '#2f6fed', dark: '#0a2a7a' },
        secondary: { main: '#0ea5e9' },
        success: { main: '#16a34a' },
        warning: { main: '#f59e0b' },
        error: { main: '#dc2626' },
        info: { main: '#0284c7' },
        background: { default: '#eef2f8', paper: '#ffffff' },
        text: { primary: '#0f1b3d', secondary: '#5b6785' },
        divider: '#e3e8f2',
      },
    },
    dark: {
      palette: {
        primary: { main: '#5ab0ff', light: '#9fd3ff', dark: '#2f6fed' },
        secondary: { main: '#38bdf8' },
        background: { default: '#0a1022', paper: '#111a33' },
        text: { primary: '#e6ecff', secondary: '#9aa8cc' },
        divider: '#1f2b4d',
      },
    },
  },
  shape: { borderRadius: 12 },
  typography: {
    fontFamily: '"Inter", "Segoe UI", system-ui, sans-serif',
    h4: { fontWeight: 800, fontSize: '1.65rem', letterSpacing: '-0.01em' },
    h5: { fontWeight: 700 },
    h6: { fontWeight: 700, fontSize: '1.05rem' },
    button: { textTransform: 'none', fontWeight: 600 },
    overline: { fontWeight: 700, letterSpacing: '0.12em' },
  },
  components: {
    MuiButton: {
      defaultProps: { disableElevation: true },
      styleOverrides: {
        root: {
          borderRadius: 10,
          paddingInline: 16,
          variants: [
            {
              props: { variant: 'contained', color: 'primary' },
              style: { backgroundImage: 'linear-gradient(135deg, #1446b8 0%, #2f6fed 100%)' },
            },
          ],
        },
      },
    },
    MuiPaper: { defaultProps: { elevation: 0 }, styleOverrides: { root: { backgroundImage: 'none' } } },
    MuiCard: {
      defaultProps: { variant: 'elevation', elevation: 0 },
      styleOverrides: {
        root: ({ theme }) => ({
          borderRadius: 20,
          border: `1px solid ${theme.palette.divider}`,
          boxShadow: '0 6px 24px rgba(10, 30, 90, 0.06)',
        }),
      },
    },
    MuiTextField: { defaultProps: { size: 'small', fullWidth: true } },
    MuiOutlinedInput: { styleOverrides: { root: { borderRadius: 10 } } },
    MuiFormControl: { defaultProps: { size: 'small' } },
    MuiChip: { defaultProps: { size: 'small' }, styleOverrides: { root: { borderRadius: 8, fontWeight: 600 } } },
    MuiDialog: { styleOverrides: { paper: { borderRadius: 20 } } },
    MuiTab: { styleOverrides: { root: { textTransform: 'none', fontWeight: 600, minHeight: 44 } } },
    MuiTableCell: {
      styleOverrides: {
        head: ({ theme }) => ({ fontWeight: 700, color: theme.palette.text.secondary, fontSize: 12, textTransform: 'uppercase', letterSpacing: '0.04em' }),
      },
    },
    MuiAlert: { styleOverrides: { root: { borderRadius: 12 } } },
    MuiAccordion: { styleOverrides: { root: { borderRadius: 16 } } },
    MuiLinearProgress: { styleOverrides: { root: { borderRadius: 8 }, bar: { borderRadius: 8 } } },
    MuiListItemButton: {
      styleOverrides: { root: ({ theme }) => ({ '&.Mui-selected': { backgroundColor: alpha(theme.palette.primary.main, 0.1) } }) },
    },
  },
})
