import { Add, EditOutlined, Refresh } from '@mui/icons-material';
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  IconButton,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TablePagination,
  TableRow,
  Tooltip,
  Typography,
} from '@mui/material';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { PageHeader } from '../components/PageHeader';
import { SearchField } from '../components/SearchField';
import { ApiError, apiRequest } from '../lib/api';
import { matchesSearch } from '../lib/search';
import type { Order, OrderStatus } from '../types/api';

const ROWS_PER_PAGE = 10;

const statusLabels: Record<OrderStatus, string> = {
  PENDENTE: 'Pendente',
  EM_PROCESSAMENTO: 'Em processamento',
  CONCLUIDO: 'Concluido',
};

const statusColors: Record<OrderStatus, 'warning' | 'info' | 'success'> = {
  PENDENTE: 'warning',
  EM_PROCESSAMENTO: 'info',
  CONCLUIDO: 'success',
};

export function OrdersPage() {
  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(0);
  const navigate = useNavigate();

  const filteredOrders = useMemo(
    () =>
      orders.filter((order) =>
        matchesSearch(search, [
          order.id,
          order.cliente_nome,
          order.descricao,
          order.status,
          statusLabels[order.status],
        ]),
      ),
    [orders, search],
  );

  const visibleOrders = useMemo(
    () =>
      filteredOrders.slice(
        page * ROWS_PER_PAGE,
        page * ROWS_PER_PAGE + ROWS_PER_PAGE,
      ),
    [filteredOrders, page],
  );

  const loadOrders = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await apiRequest<{ orders: Order[] }>('/pedidos');
      setOrders(response.orders);
    } catch (loadError) {
      setError(
        loadError instanceof ApiError
          ? loadError.message
          : 'Nao foi possivel carregar os pedidos.',
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadOrders();
  }, [loadOrders]);

  useEffect(() => {
    const lastPage = Math.max(
      0,
      Math.ceil(filteredOrders.length / ROWS_PER_PAGE) - 1,
    );

    setPage((currentPage) => Math.min(currentPage, lastPage));
  }, [filteredOrders.length]);

  const updateSearch = (value: string) => {
    setSearch(value);
    setPage(0);
  };

  return (
    <Box sx={{ maxWidth: 1280, mx: 'auto' }}>
      <PageHeader
        title="Pedidos"
        description="Consulte os registros e abra um pedido para editar."
        actions={
          <Box
            sx={{
              display: 'flex',
              flexDirection: { xs: 'column', md: 'row' },
              alignItems: { xs: 'stretch', md: 'center' },
              gap: 1.5,
              width: { xs: '100%', md: 'auto' },
            }}
          >
            <SearchField
              label="Buscar pedidos"
              value={search}
              onChange={updateSearch}
            />
            <Button
              variant="contained"
              startIcon={<Add />}
              onClick={() => navigate('/pedidos/novo')}
              sx={{ whiteSpace: 'nowrap' }}
            >
              Novo pedido
            </Button>
          </Box>
        }
      />

      {error && (
        <Alert
          severity="error"
          sx={{ mb: 2 }}
          action={
            <Button color="inherit" startIcon={<Refresh />} onClick={() => void loadOrders()}>
              Tentar novamente
            </Button>
          }
        >
          {error}
        </Alert>
      )}

      {loading && (
        <Paper sx={{ py: 7, textAlign: 'center' }} role="status">
          <CircularProgress size={28} />
          <Typography color="text.secondary" sx={{ mt: 1 }}>
            Carregando pedidos...
          </Typography>
        </Paper>
      )}

      {!loading && !error && orders.length === 0 && (
        <Paper sx={{ py: 7, px: 2, textAlign: 'center' }}>
          <Typography fontWeight={600}>Nenhum pedido cadastrado.</Typography>
          <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
            Use o botao Novo pedido para incluir o primeiro registro.
          </Typography>
        </Paper>
      )}

      {!loading && !error && orders.length > 0 && filteredOrders.length === 0 && (
        <Paper sx={{ py: 7, px: 2, textAlign: 'center' }}>
          <Typography fontWeight={600}>Nenhum pedido encontrado.</Typography>
          <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
            Tente buscar por outro cliente, descricao ou status.
          </Typography>
        </Paper>
      )}

      {!loading && filteredOrders.length > 0 && (
        <Paper>
          <TableContainer>
            <Table sx={{ minWidth: 760 }} aria-label="Lista de pedidos">
              <TableHead>
                <TableRow>
                  <TableCell width={90}>Codigo</TableCell>
                  <TableCell>Cliente</TableCell>
                  <TableCell>Descricao</TableCell>
                  <TableCell width={180}>Status</TableCell>
                  <TableCell width={180}>Criado em</TableCell>
                  <TableCell width={72} align="center">Acoes</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {visibleOrders.map((order) => (
                <TableRow hover key={order.id}>
                  <TableCell>{order.id}</TableCell>
                  <TableCell sx={{ fontWeight: 600 }}>{order.cliente_nome}</TableCell>
                  <TableCell sx={{ maxWidth: 420 }}>
                    <Typography noWrap variant="body2">
                      {order.descricao}
                    </Typography>
                  </TableCell>
                  <TableCell>
                    <Chip
                      size="small"
                      label={statusLabels[order.status]}
                      color={statusColors[order.status]}
                      variant="outlined"
                    />
                  </TableCell>
                  <TableCell>
                    {new Intl.DateTimeFormat('pt-BR', {
                      dateStyle: 'short',
                      timeStyle: 'short',
                    }).format(new Date(order.created_at))}
                  </TableCell>
                  <TableCell align="center">
                    <Tooltip title="Editar pedido">
                      <IconButton
                        aria-label={`Editar pedido ${order.id}`}
                        onClick={() => navigate(`/pedidos/${order.id}`)}
                      >
                        <EditOutlined fontSize="small" />
                      </IconButton>
                    </Tooltip>
                  </TableCell>
                </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
          <TablePagination
            component="div"
            count={filteredOrders.length}
            page={page}
            rowsPerPage={ROWS_PER_PAGE}
            rowsPerPageOptions={[]}
            onPageChange={(_event, nextPage) => setPage(nextPage)}
            labelDisplayedRows={({ from, to, count }) =>
              `${from}-${to} de ${count}`
            }
            getItemAriaLabel={(type) =>
              type === 'next' ? 'Proxima pagina' : 'Pagina anterior'
            }
          />
        </Paper>
      )}
    </Box>
  );
}
