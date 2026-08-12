import { ArrowBack, SaveOutlined } from '@mui/icons-material';
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  MenuItem,
  Paper,
  Stack,
  TextField,
} from '@mui/material';
import { useEffect, useState, type FormEvent } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { PageHeader } from '../components/PageHeader';
import { ApiError, apiRequest, jsonBody } from '../lib/api';
import type { Order, OrderStatus, ValidationFields } from '../types/api';

interface OrderForm {
  cliente_nome: string;
  descricao: string;
  status: OrderStatus;
}

const emptyForm: OrderForm = {
  cliente_nome: '',
  descricao: '',
  status: 'PENDENTE',
};

export function OrderFormPage() {
  const { id } = useParams();
  const editing = Boolean(id);
  const [form, setForm] = useState<OrderForm>(emptyForm);
  const [loading, setLoading] = useState(editing);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [fields, setFields] = useState<ValidationFields>({});
  const navigate = useNavigate();

  useEffect(() => {
    if (!id) {
      return;
    }

    const load = async () => {
      setLoading(true);

      try {
        const response = await apiRequest<{ order: Order }>(`/pedidos/${id}`);
        setForm({
          cliente_nome: response.order.cliente_nome,
          descricao: response.order.descricao,
          status: response.order.status,
        });
      } catch (loadError) {
        setError(
          loadError instanceof ApiError
            ? loadError.message
            : 'Nao foi possivel carregar o pedido.',
        );
      } finally {
        setLoading(false);
      }
    };

    void load();
  }, [id]);

  const update = (field: keyof OrderForm, value: string) => {
    setForm((current) => ({ ...current, [field]: value }));
    setFields((current) => ({ ...current, [field]: '' }));
  };

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setSuccess(null);
    setFields({});

    const localErrors: ValidationFields = {};

    if (!form.cliente_nome.trim()) {
      localErrors.cliente_nome = 'Informe o nome do cliente.';
    }

    if (!form.descricao.trim()) {
      localErrors.descricao = 'Informe a descricao do pedido.';
    }

    if (Object.keys(localErrors).length > 0) {
      setFields(localErrors);
      return;
    }

    setSaving(true);

    try {
      await apiRequest(editing ? `/pedidos/${id}` : '/pedidos', {
        method: editing ? 'PUT' : 'POST',
        body: jsonBody(form),
      });

      setSuccess(editing ? 'Pedido atualizado com sucesso.' : 'Pedido criado com sucesso.');

      window.setTimeout(() => navigate('/pedidos'), 700);
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
    <Box sx={{ maxWidth: 900, mx: 'auto' }}>
      <PageHeader
        title={editing ? `Editar pedido #${id}` : 'Novo pedido'}
        description="Preencha os dados obrigatorios para salvar o registro."
        actions={
          <Button startIcon={<ArrowBack />} onClick={() => navigate('/pedidos')}>
            Voltar
          </Button>
        }
      />

      {loading ? (
        <Box sx={{ py: 8, textAlign: 'center' }}>
          <CircularProgress />
        </Box>
      ) : (
        <Paper sx={{ p: { xs: 2, sm: 3 } }}>
          <Stack component="form" spacing={2.5} onSubmit={handleSubmit}>
            {error && <Alert severity="error">{error}</Alert>}
            {success && <Alert severity="success">{success}</Alert>}

            <TextField
              label="Cliente"
              value={form.cliente_nome}
              onChange={(event) => update('cliente_nome', event.target.value)}
              error={Boolean(fields.cliente_nome)}
              helperText={fields.cliente_nome}
              inputProps={{ maxLength: 120 }}
              required
              fullWidth
              autoFocus
            />

            <TextField
              label="Descricao"
              value={form.descricao}
              onChange={(event) => update('descricao', event.target.value)}
              error={Boolean(fields.descricao)}
              helperText={fields.descricao}
              inputProps={{ maxLength: 5000 }}
              required
              fullWidth
              multiline
              minRows={5}
            />

            <TextField
              select
              label="Status"
              value={form.status}
              onChange={(event) => update('status', event.target.value)}
              error={Boolean(fields.status)}
              helperText={fields.status}
              required
              fullWidth
            >
              <MenuItem value="PENDENTE">Pendente</MenuItem>
              <MenuItem value="EM_PROCESSAMENTO">Em processamento</MenuItem>
              <MenuItem value="CONCLUIDO">Concluido</MenuItem>
            </TextField>

            <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1.5 }}>
              <Button onClick={() => navigate('/pedidos')} disabled={saving}>
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
    </Box>
  );
}
