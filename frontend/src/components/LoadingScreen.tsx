import { Box, CircularProgress, Typography } from '@mui/material';

interface LoadingScreenProps {
  label?: string;
}

export function LoadingScreen({
  label = 'Carregando...',
}: LoadingScreenProps) {
  return (
    <Box
      role="status"
      sx={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        flexDirection: 'column',
        gap: 2,
      }}
    >
      <CircularProgress size={32} />
      <Typography color="text.secondary">{label}</Typography>
    </Box>
  );
}
