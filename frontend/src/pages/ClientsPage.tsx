import {
  Add,
  Close,
  DeleteOutline,
  EditOutlined,
  SaveOutlined,
} from '@mui/icons-material';
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
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
import { PageHeader } from '../components/PageHeader';
import { SearchField } from '../components/SearchField';
import { ApiError, apiRequest, jsonBody } from '../lib/api';
import { matchesSearch } from '../lib/search';
import type { Client, ValidationFields } from '../types/api';

const ROWS_PER_PAGE = 10;

interface ClientForm {
  nome: string;
  telefone: string;
}

const emptyForm: ClientForm = {
  nome: '',
  telefone: '',
};

export function ClientsPage() {
  const [clients, setClients] = useState<Client[]>([]);
  const [loading, setLoading] = useState(true);
  const [formOpen, setFormOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<ClientForm>(emptyForm);
  const [fields, setFields] = useState<ValidationFields>({});
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState<Client | null>(null);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(0);

  const filteredClients = useMemo(
    () => clients.filter((client) =>
      matchesSearch(search, [client.nome, client.telefone])),
    [clients, search],
  );

  const visibleClients = useMemo(
    () => filteredClients.slice(
      page * ROWS_PER_PAGE,
      page * ROWS_PER_PAGE + ROWS_PER_PAGE,
    ),
    [filteredClients, page],
  );

  const loadClients = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await apiRequest<{ clients: Client[] }>('/clientes');
      setClients(response.clients);
    } catch (loadError) {
      setError(
        loadError instanceof ApiError
          ? loadError.message
          : 'Nao foi possivel carregar os clientes.',
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadClients();
  }, [loadClients]);

  useEffect(() => {
    const lastPage = Math.max(
      0,
      Math.ceil(filteredClients.length / ROWS_PER_PAGE) - 1,
    );

    setPage((currentPage) => Math.min(currentPage, lastPage));
  }, [filteredClients.length]);

  const updateSearch = (value: string) => {
    setSearch(value);
    setPage(0);
  };

  const openNew = () => {
    setEditingId(null);
    setForm(emptyForm);
    setFields({});
    setError(null);
    setMessage(null);
    setFormOpen(true);
  };

  const openEdit = (client: Client) => {
    setEditingId(client.id);
    setForm({ nome: client.nome, telefone: client.telefone });
    setFields({});
    setError(null);
    setMessage(null);
    setFormOpen(true);
  };

  const update = (field: keyof ClientForm, value: string) => {
    setForm((current) => ({ ...current, [field]: value }));
    setFields((current) => ({ ...current, [field]: '' }));
  };

  const save = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setSaving(true);
    setError(null);
    setMessage(null);
    setFields({});

    try {
      await apiRequest(editingId ? `/clientes/${editingId}` : '/clientes', {
        method: editingId ? 'PUT' : 'POST',
        body: jsonBody(form),
      });

      setMessage(
        editingId
          ? 'Cliente atualizado com sucesso.'
          : 'Cliente cadastrado com sucesso.',
      );
      setFormOpen(false);
      await loadClients();
    } catch (saveError) {
      if (saveError instanceof ApiError) {
        setError(saveError.message);
        setFields(saveError.fields);
      } else {
        setError('Nao foi possivel salvar o cliente.');
      }
    } finally {
      setSaving(false);
    }
  };

  const remove = async () => {
    if (!deleting) return;

    try {
      await apiRequest<void>(`/clientes/${deleting.id}`, {
        method: 'DELETE',
      });
      setDeleting(null);
      setMessage('Cliente excluido com sucesso.');
      await loadClients();
    } catch (removeError) {
      setDeleting(null);
      setError(
        removeError instanceof ApiError
          ? removeError.message
          : 'Nao foi possivel excluir o cliente.',
      );
    }
  };

  return (
    <Box sx={{ maxWidth: 1280, mx: 'auto' }}>
      <PageHeader
        title="Clientes"
        description="Cadastre os clientes utilizados nos pedidos."
        actions={(
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
              label="Buscar clientes"
              value={search}
              onChange={updateSearch}
            />
            <Button
              variant="contained"
              startIcon={<Add />}
              onClick={openNew}
              sx={{ whiteSpace: 'nowrap' }}
            >
              Novo cadastro
            </Button>
          </Box>
        )}
      />

      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      {message && <Alert severity="success" sx={{ mb: 2 }}>{message}</Alert>}

      {formOpen && (
        <Paper sx={{ p: { xs: 2, sm: 3 }, mb: 3 }}>
          <Stack component="form" spacing={2} onSubmit={save}>
            <Box sx={{ display: 'flex', alignItems: 'center' }}>
              <Typography variant="h2">
                {editingId ? 'Editar cliente' : 'Novo cliente'}
              </Typography>
              <Tooltip title="Fechar formulario">
                <IconButton
                  aria-label="Fechar formulario"
                  sx={{ ml: 'auto' }}
                  onClick={() => setFormOpen(false)}
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
                label="Nome"
                value={form.nome}
                onChange={(event) => update('nome', event.target.value)}
                error={Boolean(fields.nome)}
                helperText={fields.nome}
                inputProps={{ maxLength: 120 }}
                required
              />
              <TextField
                label="Telefone"
                type="tel"
                value={form.telefone}
                onChange={(event) => update('telefone', event.target.value)}
                error={Boolean(fields.telefone)}
                helperText={fields.telefone}
                inputProps={{ maxLength: 20 }}
                required
              />
            </Box>

            <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
              <Button onClick={() => setFormOpen(false)}>Cancelar</Button>
              <Button
                type="submit"
                variant="contained"
                startIcon={<SaveOutlined />}
                disabled={saving}
              >
                {saving ? 'Salvando...' : 'Salvar cliente'}
              </Button>
            </Box>
          </Stack>
        </Paper>
      )}

      {loading && (
        <Paper sx={{ py: 7, textAlign: 'center' }} role="status">
          <CircularProgress size={28} />
        </Paper>
      )}

      {!loading && clients.length === 0 && (
        <Paper sx={{ py: 7, px: 2, textAlign: 'center' }}>
          <Typography fontWeight={600}>Nenhum cliente cadastrado.</Typography>
        </Paper>
      )}

      {!loading && clients.length > 0 && filteredClients.length === 0 && (
        <Paper sx={{ py: 7, px: 2, textAlign: 'center' }}>
          <Typography fontWeight={600}>Nenhum cliente encontrado.</Typography>
          <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
            Tente buscar por outro nome ou telefone.
          </Typography>
        </Paper>
      )}

      {!loading && filteredClients.length > 0 && (
        <Paper>
          <TableContainer>
            <Table sx={{ minWidth: 600 }} aria-label="Lista de clientes">
              <TableHead>
                <TableRow>
                  <TableCell>Nome</TableCell>
                  <TableCell>Telefone</TableCell>
                  <TableCell align="center">Acoes</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {visibleClients.map((client) => (
                  <TableRow hover key={client.id}>
                    <TableCell sx={{ fontWeight: 600 }}>
                      {client.nome}
                    </TableCell>
                    <TableCell>{client.telefone}</TableCell>
                    <TableCell align="center">
                      <Tooltip title="Editar">
                        <IconButton
                          aria-label={`Editar ${client.nome}`}
                          onClick={() => openEdit(client)}
                        >
                          <EditOutlined fontSize="small" />
                        </IconButton>
                      </Tooltip>
                      <Tooltip title="Excluir">
                        <IconButton
                          color="error"
                          aria-label={`Excluir ${client.nome}`}
                          onClick={() => setDeleting(client)}
                        >
                          <DeleteOutline fontSize="small" />
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
            count={filteredClients.length}
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

      <Dialog open={Boolean(deleting)} onClose={() => setDeleting(null)}>
        <DialogTitle>Excluir cliente</DialogTitle>
        <DialogContent>
          Deseja excluir o cliente <strong>{deleting?.nome}</strong>?
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDeleting(null)}>Cancelar</Button>
          <Button
            color="error"
            variant="contained"
            onClick={() => void remove()}
          >
            Excluir
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}
