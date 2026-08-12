import {
  Add,
  ArrowForward,
  Inventory2Outlined,
  PeopleAltOutlined,
  SecurityOutlined,
} from '@mui/icons-material';
import {
  Box,
  Button,
  Card,
  CardActionArea,
  CardContent,
  Chip,
  Grid,
  Stack,
  Typography,
} from '@mui/material';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '../components/PageHeader';
import { useAuth } from '../contexts/AuthContext';

export function HomePage() {
  const { user } = useAuth();
  const navigate = useNavigate();

  return (
    <Box sx={{ maxWidth: 1180, mx: 'auto' }}>
      <PageHeader
        title={`Ola, ${user?.nome.split(' ')[0] ?? ''}`}
        description="Acesse rapidamente as operacoes disponiveis para seu perfil."
        actions={
          <Button
            variant="contained"
            startIcon={<Add />}
            onClick={() => navigate('/pedidos', {
              state: { openNewOrder: true },
            })}
          >
            Novo pedido
          </Button>
        }
      />

      <Grid container spacing={2.5}>
        <Grid size={{ xs: 12, md: 7 }}>
          <Card sx={{ height: '100%' }}>
            <CardActionArea
              onClick={() => navigate('/pedidos')}
              sx={{ height: '100%', p: 1 }}
            >
              <CardContent>
                <Inventory2Outlined color="primary" sx={{ fontSize: 38 }} />
                <Typography variant="h2" sx={{ mt: 2 }}>
                  Gerenciar pedidos
                </Typography>
                <Typography color="text.secondary" sx={{ mt: 1, maxWidth: 600 }}>
                  Consulte cliente, descricao, status e data de criacao. Abra um
                  registro para atualizar seus dados.
                </Typography>
                <Stack
                  direction="row"
                  alignItems="center"
                  spacing={0.75}
                  sx={{ mt: 2, color: 'primary.main' }}
                >
                  <Typography variant="body2" fontWeight={700}>
                    Abrir listagem
                  </Typography>
                  <ArrowForward fontSize="small" />
                </Stack>
              </CardContent>
            </CardActionArea>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 5 }}>
          <Stack spacing={2.5} sx={{ height: '100%' }}>
            <Card>
              <CardContent>
                <Stack direction="row" alignItems="center" spacing={1.5}>
                  <SecurityOutlined color="secondary" />
                  <Box>
                    <Typography variant="h3">Sessao protegida</Typography>
                    <Typography variant="body2" color="text.secondary">
                      JWT em cookies HttpOnly e protecao CSRF.
                    </Typography>
                  </Box>
                </Stack>
              </CardContent>
            </Card>

            {user?.perfil === 'ADMIN' && (
              <Card>
                <CardActionArea onClick={() => navigate('/acessos')}>
                  <CardContent>
                    <Stack direction="row" alignItems="center" spacing={1.5}>
                      <PeopleAltOutlined color="primary" />
                      <Box sx={{ flexGrow: 1 }}>
                        <Typography variant="h3">Administrar acessos</Typography>
                        <Typography variant="body2" color="text.secondary">
                          Cadastre, altere ou desative usuarios.
                        </Typography>
                      </Box>
                      <Chip size="small" label="ADMIN" color="primary" />
                    </Stack>
                  </CardContent>
                </CardActionArea>
              </Card>
            )}
          </Stack>
        </Grid>
      </Grid>
    </Box>
  );
}
