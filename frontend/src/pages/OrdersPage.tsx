import {
  Add,
  Close,
  DownloadOutlined,
  EditOutlined,
  Refresh,
  SaveOutlined,
} from '@mui/icons-material';
import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  IconButton,
  InputAdornment,
  MenuItem,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TablePagination,
  TableRow,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material';
import {
  useCallback,
  useEffect,
  useMemo,
  useState,
  type FormEvent,
} from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { PageHeader } from '../components/PageHeader';
import { SearchField } from '../components/SearchField';
import { useAuth } from '../contexts/AuthContext';
import { ApiError, apiRequest, jsonBody } from '../lib/api';
import { formatCurrency, normalizeMoneyInput } from '../lib/money';
import { downloadSalesReport } from '../lib/salesReport';
import { matchesSearch } from '../lib/search';
import type {
  Client,
  Order,
  OrderStatus,
  ValidationFields,
} from '../types/api';

const ROWS_PER_PAGE = 10;

interface OrderForm {
  cliente_id: string;
  descricao: string;
  valor_total: string;
  status: OrderStatus;
}

interface OrdersLocationState {
  openNewOrder?: boolean;
}

const emptyForm: OrderForm = {
  cliente_id: '',
  descricao: '',
  valor_total: '',
  status: 'PENDENTE',
};

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
  const [clients, setClients] = useState<Client[]>([]);
  const [clientsLoading, setClientsLoading] = useState(false);
  const [loading, setLoading] = useState(true);
  const [formOpen, setFormOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<OrderForm>(emptyForm);
  const [fields, setFields] = useState<ValidationFields>({});
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [generatingReport, setGeneratingReport] = useState(false);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(0);
  const location = useLocation();
  const navigate = useNavigate();
  const { user } = useAuth();

  const clientsById = useMemo(
    () => new Map(clients.map((client) => [client.id, client])),
    [clients],
  );

  const filteredOrders = useMemo(
    () =>
      orders.filter((order) =>
        matchesSearch(search, [
          order.id,
          clientsById.get(order.cliente_id)?.nome ?? '',
          order.descricao,
          formatCurrency(order.valor_total),
          order.status,
          statusLabels[order.status],
        ]),
      ),
    [clientsById, orders, search],
  );

  const visibleOrders = useMemo(
    () =>
      filteredOrders.slice(
        page * ROWS_PER_PAGE,
        page * ROWS_PER_PAGE + ROWS_PER_PAGE,
      ),
    [filteredOrders, page],
  );

  const currentClientName = editingId === null
    ? null
    : clientsById.get(
      orders.find((order) => order.id === editingId)?.cliente_id ?? 0,
    )?.nome ?? null;

  const selectedClientIsMissing = form.cliente_id !== ''
    && !clients.some((client) => String(client.id) === form.cliente_id);

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

  const loadClients = useCallback(async () => {
    setClientsLoading(true);

    try {
      const response = await apiRequest<{ clients: Client[] }>('/clientes');
      setClients(response.clients ?? []);
    } catch (loadError) {
      setError(
        loadError instanceof ApiError
          ? loadError.message
          : 'Nao foi possivel carregar os clientes.',
      );
    } finally {
      setClientsLoading(false);
    }
  }, []);

  const openNew = useCallback(() => {
    setEditingId(null);
    setForm(emptyForm);
    setFields({});
    setError(null);
    setMessage(null);
    setFormOpen(true);
  }, []);

  useEffect(() => {
    void loadOrders();
    void loadClients();
  }, [loadClients, loadOrders]);

  useEffect(() => {
    const state = location.state as OrdersLocationState | null;

    if (state?.openNewOrder) {
      openNew();
      navigate('/pedidos', { replace: true, state: null });
    }
  }, [location.state, navigate, openNew]);

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

  const downloadReport = async () => {
    setError(null);
    setGeneratingReport(true);

    try {
      await downloadSalesReport(filteredOrders, clients);
    } catch {
      setError('Nao foi possivel gerar o PDF.');
    } finally {
      setGeneratingReport(false);
    }
  };

  const openEdit = (order: Order) => {
    setEditingId(order.id);
    setForm({
      cliente_id: String(order.cliente_id),
      descricao: order.descricao,
      valor_total: order.valor_total.replace('.', ','),
      status: order.status,
    });
    setFields({});
    setError(null);
    setMessage(null);
    setFormOpen(true);
  };

  const closeForm = () => {
    if (!saving) {
      setFormOpen(false);
      setFields({});
    }
  };

  const update = (field: keyof OrderForm, value: string) => {
    setForm((current) => ({ ...current, [field]: value }));
    setFields((current) => ({ ...current, [field]: '' }));
  };

  const save = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setMessage(null);
    setFields({});

    const localErrors: ValidationFields = {};

    if (!form.cliente_id) {
      localErrors.cliente_id = 'Selecione um cliente.';
    }

    if (!form.descricao.trim()) {
      localErrors.descricao = 'Informe a descricao do pedido.';
    }

    const totalAmount = normalizeMoneyInput(form.valor_total);

    if (totalAmount === null) {
      localErrors.valor_total = 'Informe um valor maior que zero.';
    }

    if (Object.keys(localErrors).length > 0) {
      setFields(localErrors);
      return;
    }

    setSaving(true);

    try {
      await apiRequest(
        editingId === null ? '/pedidos' : `/pedidos/${editingId}`,
        {
          method: editingId === null ? 'POST' : 'PUT',
          body: jsonBody({
            cliente_id: Number(form.cliente_id),
            descricao: form.descricao,
            valor_total: totalAmount,
            status: form.status,
          }),
        },
      );

      setMessage(
        editingId === null
          ? 'Pedido criado com sucesso.'
          : 'Pedido atualizado com sucesso.',
      );
      setFormOpen(false);
      setEditingId(null);
      setForm(emptyForm);
      await loadOrders();
    } catch (saveError) {
      if (saveError instanceof ApiError) {
        setError(saveError.message);
        setFields(saveError.fields);
      } else {
        setError('Nao foi possivel salvar o pedido.');
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <Box sx={{ maxWidth: 1280, mx: 'auto' }}>
      <PageHeader
        title="Pedidos"
        description="Cadastre pedidos e consulte os registros existentes."
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
            {user?.perfil === 'ADMIN' && (
              <Button
                variant="outlined"
                startIcon={<DownloadOutlined />}
                onClick={() => void downloadReport()}
                disabled={
                  loading
                  || clientsLoading
                  || generatingReport
                  || filteredOrders.length === 0
                }
                sx={{ whiteSpace: 'nowrap' }}
              >
                {generatingReport ? 'Gerando...' : 'Baixar PDF'}
              </Button>
            )}
            <Button
              variant="contained"
              startIcon={<Add />}
              onClick={openNew}
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
            !formOpen ? (
              <Button
                color="inherit"
                startIcon={<Refresh />}
                onClick={() => void loadOrders()}
              >
                Tentar novamente
              </Button>
            ) : undefined
          }
        >
          {error}
        </Alert>
      )}
      {message && <Alert severity="success" sx={{ mb: 2 }}>{message}</Alert>}

      {formOpen && (
        <Paper sx={{ p: { xs: 2, sm: 3 }, mb: 3 }}>
          <Stack component="form" spacing={2} onSubmit={save} noValidate>
            <Box sx={{ display: 'flex', alignItems: 'center' }}>
              <Typography variant="h2">
                {editingId === null ? 'Novo pedido' : `Editar pedido #${editingId}`}
              </Typography>
              <Tooltip title="Fechar formulario">
                <IconButton
                  aria-label="Fechar formulario"
                  sx={{ ml: 'auto' }}
                  onClick={closeForm}
                  disabled={saving}
                >
                  <Close />
                </IconButton>
              </Tooltip>
            </Box>

            <Box
              sx={{
                display: 'grid',
                gridTemplateColumns: { xs: '1fr', md: '1fr 1fr' },
                gap: 2,
              }}
            >
              <TextField
                select
                label="Cliente"
                value={form.cliente_id}
                onChange={(event) => update('cliente_id', event.target.value)}
                error={Boolean(fields.cliente_id)}
                helperText={
                  fields.cliente_id
                  ?? (clientsLoading ? 'Carregando clientes...' : undefined)
                }
                disabled={clientsLoading}
                required
                autoFocus
              >
                {selectedClientIsMissing && (
                  <MenuItem value={form.cliente_id}>
                    {currentClientName ?? 'Cliente atual'}
                  </MenuItem>
                )}
                {clients.length === 0 && (
                  <MenuItem value="" disabled>
                    Nenhum cliente disponivel
                  </MenuItem>
                )}
                {clients.map((client) => (
                  <MenuItem key={client.id} value={String(client.id)}>
                    {client.nome} - {client.telefone}
                  </MenuItem>
                ))}
              </TextField>

              <TextField
                label="Valor total"
                value={form.valor_total}
                onChange={(event) => update('valor_total', event.target.value)}
                error={Boolean(fields.valor_total)}
                helperText={fields.valor_total}
                inputProps={{ inputMode: 'decimal', maxLength: 14 }}
                InputProps={{
                  startAdornment: (
                    <InputAdornment position="start">R$</InputAdornment>
                  ),
                }}
                required
              />

              <TextField
                select
                label="Status"
                value={form.status}
                onChange={(event) => update('status', event.target.value)}
                error={Boolean(fields.status)}
                helperText={fields.status}
                required
              >
                <MenuItem value="PENDENTE">Pendente</MenuItem>
                <MenuItem value="EM_PROCESSAMENTO">Em processamento</MenuItem>
                <MenuItem value="CONCLUIDO">Concluido</MenuItem>
              </TextField>

              <TextField
                label="Descricao"
                value={form.descricao}
                onChange={(event) => update('descricao', event.target.value)}
                error={Boolean(fields.descricao)}
                helperText={fields.descricao}
                inputProps={{ maxLength: 5000 }}
                required
                multiline
                minRows={4}
                sx={{ gridColumn: '1 / -1' }}
              />
            </Box>

            <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
              <Button onClick={closeForm} disabled={saving}>
                Cancelar
              </Button>
              <Button
                type="submit"
                variant="contained"
                startIcon={<SaveOutlined />}
                disabled={saving}
              >
                {saving ? 'Salvando...' : 'Salvar pedido'}
              </Button>
            </Box>
          </Stack>
        </Paper>
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
                  <TableCell width={150} align="right">Valor</TableCell>
                  <TableCell width={180}>Status</TableCell>
                  <TableCell width={180}>Criado em</TableCell>
                  <TableCell width={72} align="center">Acoes</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {visibleOrders.map((order) => (
                  <TableRow hover key={order.id}>
                    <TableCell>{order.id}</TableCell>
                    <TableCell sx={{ fontWeight: 600 }}>
                      {clientsById.get(order.cliente_id)?.nome
                        ?? `Cliente #${order.cliente_id}`}
                    </TableCell>
                    <TableCell sx={{ maxWidth: 420 }}>
                      <Typography noWrap variant="body2">
                        {order.descricao}
                      </Typography>
                    </TableCell>
                    <TableCell align="right" sx={{ whiteSpace: 'nowrap' }}>
                      {formatCurrency(order.valor_total)}
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
                          onClick={() => openEdit(order)}
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
