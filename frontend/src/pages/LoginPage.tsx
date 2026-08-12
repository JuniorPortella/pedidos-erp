import { LockOutlined, Visibility, VisibilityOff } from '@mui/icons-material';
import {
  Alert,
  Box,
  Button,
  IconButton,
  InputAdornment,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import { useState, type FormEvent } from 'react';
import { Navigate, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { ApiError } from '../lib/api';

export function LoginPage() {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const { user, loading, login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  if (!loading && user) {
    return <Navigate to="/" replace />;
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setSubmitting(true);

    try {
      await login(username, password);

      const destination =
        (location.state as { from?: { pathname?: string } } | null)?.from
          ?.pathname ?? '/';

      navigate(destination, { replace: true });
    } catch (loginError) {
      setError(
        loginError instanceof ApiError
          ? loginError.message
          : 'Nao foi possivel entrar no sistema.',
      );
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Box
      sx={{
        minHeight: '100vh',
        display: 'grid',
        gridTemplateColumns: { xs: '1fr', md: 'minmax(360px, 43%) 1fr' },
        bgcolor: '#eef2f7',
      }}
    >
      <Box
        sx={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          px: { xs: 2, sm: 5 },
          py: 5,
        }}
      >
        <Box sx={{ width: '100%', maxWidth: 420 }}>
          <Box
            component="img"
            src="/pedidos-full-logo.png"
            alt="PedidosFull"
            sx={{ width: '100%', maxWidth: 310, height: 104, objectFit: 'contain' }}
          />

          <Paper sx={{ mt: 2, p: { xs: 3, sm: 4 } }}>
            <Stack component="form" spacing={2.5} onSubmit={handleSubmit}>
              <Box>
                <Typography component="h1" variant="h1">
                  Acessar sistema
                </Typography>
                <Typography color="text.secondary" sx={{ mt: 0.75 }}>
                  Informe suas credenciais para continuar.
                </Typography>
              </Box>

              {error && <Alert severity="error">{error}</Alert>}

              <TextField
                label="Usuario"
                value={username}
                onChange={(event) => setUsername(event.target.value)}
                autoComplete="username"
                autoFocus
                required
                fullWidth
                inputProps={{ maxLength: 60 }}
              />

              <TextField
                label="Senha"
                type={showPassword ? 'text' : 'password'}
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                autoComplete="current-password"
                required
                fullWidth
                InputProps={{
                  endAdornment: (
                    <InputAdornment position="end">
                      <IconButton
                        aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
                        onClick={() => setShowPassword((value) => !value)}
                        edge="end"
                      >
                        {showPassword ? <VisibilityOff /> : <Visibility />}
                      </IconButton>
                    </InputAdornment>
                  ),
                }}
              />

              <Button
                type="submit"
                variant="contained"
                size="large"
                disabled={submitting || !username.trim() || !password}
                startIcon={<LockOutlined />}
              >
                {submitting ? 'Entrando...' : 'Entrar'}
              </Button>
            </Stack>
          </Paper>
        </Box>
      </Box>

      <Box
        sx={{
          display: { xs: 'none', md: 'flex' },
          alignItems: 'flex-end',
          p: 7,
          color: 'white',
          bgcolor: '#0b1739',
          backgroundImage:
            'linear-gradient(145deg, rgba(7,95,228,0.2), transparent 50%), linear-gradient(20deg, rgba(0,169,143,0.16), transparent 45%)',
        }}
      >
        <Box sx={{ maxWidth: 560 }}>
          <Typography component="p" sx={{ fontSize: '2.3rem', fontWeight: 700 }}>
            Pedidos organizados, acesso controlado.
          </Typography>
          <Typography sx={{ mt: 1.5, color: 'rgba(255,255,255,0.72)', fontSize: '1.05rem' }}>
            Uma area de trabalho objetiva para acompanhar pedidos e administrar
            acessos com seguranca.
          </Typography>
        </Box>
      </Box>
    </Box>
  );
}
