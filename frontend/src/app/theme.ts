import { createTheme } from '@mui/material/styles';

export const theme = createTheme({
  palette: {
    mode: 'light',
    primary: {
      main: '#075fe4',
      dark: '#0845a3',
      light: '#e8f1ff',
    },
    secondary: {
      main: '#00a98f',
      dark: '#007d6b',
    },
    background: {
      default: '#f3f5f8',
      paper: '#ffffff',
    },
    text: {
      primary: '#172033',
      secondary: '#5d687b',
    },
    divider: '#dfe4ec',
  },
  shape: {
    borderRadius: 6,
  },
  typography: {
    fontFamily: 'Inter, Roboto, Arial, sans-serif',
    h1: { fontSize: '1.75rem', fontWeight: 700 },
    h2: { fontSize: '1.3rem', fontWeight: 700 },
    h3: { fontSize: '1rem', fontWeight: 700 },
    button: { textTransform: 'none', fontWeight: 600 },
  },
  components: {
    MuiButton: {
      defaultProps: { disableElevation: true },
    },
    MuiCard: {
      styleOverrides: {
        root: {
          border: '1px solid #dfe4ec',
          boxShadow: '0 1px 3px rgba(18, 32, 56, 0.06)',
        },
      },
    },
    MuiTextField: {
      defaultProps: {
        size: 'small',
      },
    },
  },
});
