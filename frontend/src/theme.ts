import { createTheme } from '@mui/material/styles'

// Government-office look: deep navy primary, maroon accent, quiet surfaces.
export const theme = createTheme({
  cssVariables: { colorSchemeSelector: 'class' },
  colorSchemes: {
    light: {
      palette: {
        primary: { main: '#1d4e89' },
        secondary: { main: '#8d1b3d' },
        background: { default: '#f4f6f9', paper: '#ffffff' },
      },
    },
    dark: {
      palette: {
        primary: { main: '#7fb0ec' },
        secondary: { main: '#e48aa6' },
        background: { default: '#0f141b', paper: '#161d27' },
      },
    },
  },
  shape: { borderRadius: 8 },
  typography: {
    fontFamily: '"Inter", "Segoe UI", system-ui, sans-serif',
    h4: { fontWeight: 700, fontSize: '1.6rem' },
    h5: { fontWeight: 600 },
    h6: { fontWeight: 600 },
    button: { textTransform: 'none', fontWeight: 600 },
  },
  components: {
    MuiButton: { defaultProps: { disableElevation: true } },
    MuiPaper: { defaultProps: { elevation: 0 }, styleOverrides: { root: { backgroundImage: 'none' } } },
    MuiCard: { defaultProps: { variant: 'outlined' } },
    MuiTextField: { defaultProps: { size: 'small', fullWidth: true } },
    MuiFormControl: { defaultProps: { size: 'small' } },
    MuiChip: { defaultProps: { size: 'small' } },
  },
})
