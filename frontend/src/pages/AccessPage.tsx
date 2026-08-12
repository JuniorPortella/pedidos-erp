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
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  IconButton,
  MenuItem,
  Paper,
  Stack,
  Switch,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material';
import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { PageHeader } from '../components/PageHeader';
import { ApiError, apiRequest, jsonBody } from '../lib/api';
import type { User, UserProfile, ValidationFields } from '../types/api';

interface UserForm {
  nome: string;
  email: string;
  usuario: string;
  senha: string;
  perfil: UserProfile;
  ativo: boolean;
}

const emptyForm: UserForm = {
  nome: '',
  email: '',
  usuario: '',
  senha: '',
  perfil: 'OPERADOR',
  ativo: true,
};

export function AccessPage() {
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [formOpen, setFormOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<UserForm>(emptyForm);
  const [fields, setFields] = useState<ValidationFields>({});
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState<User | null>(null);

  const loadUsers = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await apiRequest<{ users: User[] }>('/usuarios');
      setUsers(response.users);
    } catch (loadError) {
      setError(loadError instanceof ApiError ? loadError.message : 'Nao foi possivel carregar os acessos.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadUsers();
  }, [loadUsers]);

  const openNew = () => {
    setEditingId(null);
    setForm(emptyForm);
    setFields({});
    setError(null);
    setMessage(null);
    setFormOpen(true);
  };

  const openEdit = (user: User) => {
    setEditingId(user.id);
    setForm({
      nome: user.nome,
      email: user.email,
      usuario: user.usuario,
      senha: '',
      perfil: user.perfil,
      ativo: user.ativo,
    });
    setFields({});
    setError(null);
    setMessage(null);
    setFormOpen(true);
  };

  const update = (field: keyof UserForm, value: string | boolean) => {
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
      const payload = editingId
        ? form
        : {
            nome: form.nome,
            email: form.email,
            usuario: form.usuario,
            senha: form.senha,
            perfil: form.perfil,
          };

      await apiRequest(editingId ? `/usuarios/${editingId}` : '/auth/register', {
        method: editingId ? 'PUT' : 'POST',
        body: jsonBody(payload),
      });

      setMessage(editingId ? 'Acesso atualizado com sucesso.' : 'Acesso cadastrado com sucesso.');
      setFormOpen(false);
      await loadUsers();
    } catch (saveError) {
      if (saveError instanceof ApiError) {
        setError(saveError.message);
        setFields(saveError.fields);
      } else {
        setError('Nao foi possivel salvar o acesso.');
      }
    } finally {
      setSaving(false);
    }
  };

  const remove = async () => {
    if (!deleting) return;

    try {
      await apiRequest<void>(`/usuarios/${deleting.id}`, { method: 'DELETE' });
      setDeleting(null);
      setMessage('Acesso excluido com sucesso.');
      await loadUsers();
    } catch (removeError) {
      setDeleting(null);
      setError(removeError instanceof ApiError ? removeError.message : 'Nao foi possivel excluir o acesso.');
    }
  };

  return (
    <Box sx={{ maxWidth: 1280, mx: 'auto' }}>
      <PageHeader
        title="Acessos"
        description="Cadastre administradores e operadores autorizados a usar o sistema."
        actions={
          <Button variant="contained" startIcon={<Add />} onClick={openNew}>
            Novo cadastro
          </Button>
        }
      />

      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
      {message && <Alert severity="success" sx={{ mb: 2 }}>{message}</Alert>}

      {formOpen && (
        <Paper sx={{ p: { xs: 2, sm: 3 }, mb: 3 }}>
          <Stack component="form" spacing={2} onSubmit={save}>
            <Box sx={{ display: 'flex', alignItems: 'center' }}>
              <Typography variant="h2">{editingId ? 'Editar acesso' : 'Novo acesso'}</Typography>
              <Tooltip title="Fechar formulario">
                <IconButton sx={{ ml: 'auto' }} onClick={() => setFormOpen(false)}>
                  <Close />
                </IconButton>
              </Tooltip>
            </Box>
            <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', md: '1fr 1fr' }, gap: 2 }}>
              <TextField label="Nome" value={form.nome} onChange={(event) => update('nome', event.target.value)} error={Boolean(fields.nome)} helperText={fields.nome} required />
              <TextField label="E-mail" type="email" value={form.email} onChange={(event) => update('email', event.target.value)} error={Boolean(fields.email)} helperText={fields.email} required />
              <TextField label="Usuario" value={form.usuario} onChange={(event) => update('usuario', event.target.value)} error={Boolean(fields.usuario)} helperText={fields.usuario} required />
              <TextField label={editingId ? 'Nova senha' : 'Senha'} type="password" value={form.senha} onChange={(event) => update('senha', event.target.value)} error={Boolean(fields.senha)} helperText={fields.senha ?? (editingId ? 'Deixe vazio para manter a senha atual.' : 'Minimo de 8 caracteres com maiuscula, minuscula, numero e especial.')} required={!editingId} />
              <TextField select label="Perfil" value={form.perfil} onChange={(event) => update('perfil', event.target.value)} error={Boolean(fields.perfil)} helperText={fields.perfil} required>
                <MenuItem value="OPERADOR">Operador</MenuItem>
                <MenuItem value="ADMIN">Administrador</MenuItem>
              </TextField>
              {editingId && (
                <FormControlLabel control={<Switch checked={form.ativo} onChange={(event) => update('ativo', event.target.checked)} />} label="Usuario ativo" />
              )}
            </Box>
            <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
              <Button onClick={() => setFormOpen(false)}>Cancelar</Button>
              <Button type="submit" variant="contained" startIcon={<SaveOutlined />} disabled={saving}>
                {saving ? 'Salvando...' : 'Salvar acesso'}
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

      {!loading && users.length === 0 && (
        <Paper sx={{ py: 7, px: 2, textAlign: 'center' }}>
          <Typography fontWeight={600}>Nenhum acesso cadastrado.</Typography>
        </Paper>
      )}

      {!loading && users.length > 0 && (
        <TableContainer component={Paper}>
          <Table sx={{ minWidth: 800 }} aria-label="Lista de acessos">
          <TableHead><TableRow><TableCell>Nome</TableCell><TableCell>E-mail</TableCell><TableCell>Usuario</TableCell><TableCell>Perfil</TableCell><TableCell>Status</TableCell><TableCell align="center">Acoes</TableCell></TableRow></TableHead>
          <TableBody>
            {users.map((user) => (
              <TableRow hover key={user.id}>
                <TableCell sx={{ fontWeight: 600 }}>{user.nome}</TableCell>
                <TableCell>{user.email}</TableCell>
                <TableCell>{user.usuario}</TableCell>
                <TableCell><Chip size="small" label={user.perfil === 'ADMIN' ? 'Administrador' : 'Operador'} color={user.perfil === 'ADMIN' ? 'primary' : 'default'} variant="outlined" /></TableCell>
                <TableCell><Chip size="small" label={user.ativo ? 'Ativo' : 'Inativo'} color={user.ativo ? 'success' : 'default'} /></TableCell>
                <TableCell align="center">
                  <Tooltip title="Editar"><IconButton aria-label={`Editar ${user.nome}`} onClick={() => openEdit(user)}><EditOutlined fontSize="small" /></IconButton></Tooltip>
                  <Tooltip title="Excluir"><IconButton color="error" aria-label={`Excluir ${user.nome}`} onClick={() => setDeleting(user)}><DeleteOutline fontSize="small" /></IconButton></Tooltip>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
        </TableContainer>
      )}

      <Dialog open={Boolean(deleting)} onClose={() => setDeleting(null)}>
        <DialogTitle>Excluir acesso</DialogTitle>
        <DialogContent>
          Deseja excluir o acesso de <strong>{deleting?.nome}</strong>? As sessoes desse usuario serao revogadas.
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDeleting(null)}>Cancelar</Button>
          <Button color="error" variant="contained" onClick={() => void remove()}>Excluir</Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}
