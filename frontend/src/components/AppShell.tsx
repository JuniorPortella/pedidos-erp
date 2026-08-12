import {
  ChevronLeft,
  DashboardOutlined,
  Inventory2Outlined,
  Logout,
  Menu,
  PeopleAltOutlined,
} from '@mui/icons-material';
import {
  AppBar,
  Avatar,
  Box,
  Button,
  Divider,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Drawer,
  IconButton,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Toolbar,
  Tooltip,
  Typography,
  useMediaQuery,
  useTheme,
} from '@mui/material';
import { useEffect, useState } from 'react';
import { Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

const expandedWidth = 248;
const collapsedWidth = 72;

interface NavigationItem {
  label: string;
  path: string;
  icon: React.ReactNode;
  adminOnly?: boolean;
}

const navigationItems: NavigationItem[] = [
  { label: 'Inicio', path: '/', icon: <DashboardOutlined /> },
  { label: 'Pedidos', path: '/pedidos', icon: <Inventory2Outlined /> },
  {
    label: 'Acessos',
    path: '/acessos',
    icon: <PeopleAltOutlined />,
    adminOnly: true,
  },
];

function isNavigationItemSelected(
  itemPath: string,
  currentPath: string,
): boolean {
  if (itemPath === '/') {
    return currentPath === '/';
  }

  if (itemPath === '/pedidos') {
    return currentPath === '/pedidos'
      || currentPath === '/pedidos/novo'
      || /^\/pedidos\/[1-9][0-9]*$/.test(currentPath);
  }

  return currentPath === itemPath || currentPath.startsWith(`${itemPath}/`);
}

export function AppShell() {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('md'));
  const [open, setOpen] = useState(!isMobile);
  const [logoutConfirmationOpen, setLogoutConfirmationOpen] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);
  const { user, logout } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();

  useEffect(() => {
    setOpen(!isMobile);
  }, [isMobile]);

  const drawerWidth = open ? expandedWidth : collapsedWidth;
  const items = navigationItems.filter(
    (item) => !item.adminOnly || user?.perfil === 'ADMIN',
  );

  const goTo = (path: string) => {
    navigate(path);

    if (isMobile) {
      setOpen(false);
    }
  };

  const handleLogout = async () => {
    setLoggingOut(true);

    try {
      await logout();
      setLogoutConfirmationOpen(false);
      navigate('/login', { replace: true });
    } finally {
      setLoggingOut(false);
    }
  };

  const drawer = (
    <Box
      sx={{
        height: '100%',
        display: 'flex',
        flexDirection: 'column',
        bgcolor: '#0b1739',
        color: 'white',
      }}
    >
      <Toolbar sx={{ minHeight: 64, px: open ? 2 : 1 }}>
        {open && (
          <Box
            component="img"
            src="/pedidos-full-logo.png"
            alt="PedidosFull"
            sx={{ width: 166, height: 48, objectFit: 'contain' }}
          />
        )}
        <Tooltip title={open ? 'Recolher menu' : 'Expandir menu'}>
          <IconButton
            color="inherit"
            onClick={() => setOpen((value) => !value)}
            sx={{ ml: open ? 'auto' : 0.5 }}
            aria-label={open ? 'Recolher menu' : 'Expandir menu'}
          >
            {open ? <ChevronLeft /> : <Menu />}
          </IconButton>
        </Tooltip>
      </Toolbar>

      <Divider sx={{ borderColor: 'rgba(255,255,255,0.12)' }} />

      <List sx={{ px: 1, py: 2 }}>
        {items.map((item) => {
          const selected = isNavigationItemSelected(
            item.path,
            location.pathname,
          );

          return (
            <Tooltip
              key={item.path}
              title={open ? '' : item.label}
              placement="right"
            >
              <ListItemButton
                selected={selected}
                onClick={() => goTo(item.path)}
                sx={{
                  minHeight: 46,
                  mb: 0.5,
                  px: 1.5,
                  color: 'rgba(255,255,255,0.76)',
                  '& .MuiListItemIcon-root': { color: 'inherit' },
                  '&.Mui-selected': {
                    color: 'white',
                    bgcolor: '#075fe4',
                  },
                  '&.Mui-selected:hover': { bgcolor: '#0754c8' },
                  '&:hover': { bgcolor: 'rgba(255,255,255,0.08)' },
                }}
              >
                <ListItemIcon sx={{ minWidth: open ? 42 : 32 }}>
                  {item.icon}
                </ListItemIcon>
                {open && <ListItemText primary={item.label} />}
              </ListItemButton>
            </Tooltip>
          );
        })}
      </List>

      <Box sx={{ mt: 'auto', p: 1 }}>
        <Divider sx={{ mb: 1, borderColor: 'rgba(255,255,255,0.12)' }} />
        {open && (
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.25, p: 1 }}>
            <Avatar sx={{ width: 34, height: 34, bgcolor: '#00a98f' }}>
              {user?.nome.charAt(0).toUpperCase()}
            </Avatar>
            <Box sx={{ minWidth: 0 }}>
              <Typography noWrap variant="body2" fontWeight={700}>
                {user?.nome}
              </Typography>
              <Typography
                noWrap
                variant="caption"
                sx={{ color: 'rgba(255,255,255,0.62)' }}
              >
                {user?.perfil === 'ADMIN' ? 'Administrador' : 'Operador'}
              </Typography>
            </Box>
          </Box>
        )}
        <Tooltip title={open ? '' : 'Sair'} placement="right">
          <ListItemButton
            onClick={() => setLogoutConfirmationOpen(true)}
            disabled={loggingOut}
            sx={{ color: '#ffb4b4', px: 1.5 }}
          >
            <ListItemIcon sx={{ minWidth: open ? 42 : 32, color: 'inherit' }}>
              <Logout />
            </ListItemIcon>
            {open && <ListItemText primary={loggingOut ? 'Saindo...' : 'Sair'} />}
          </ListItemButton>
        </Tooltip>
      </Box>
    </Box>
  );

  return (
    <Box sx={{ display: 'flex', minHeight: '100vh' }}>
      <AppBar
        position="fixed"
        elevation={0}
        sx={{
          width: isMobile ? '100%' : `calc(100% - ${drawerWidth}px)`,
          ml: isMobile ? 0 : `${drawerWidth}px`,
          bgcolor: 'background.paper',
          color: 'text.primary',
          borderBottom: '1px solid',
          borderColor: 'divider',
          transition: theme.transitions.create(['width', 'margin']),
        }}
      >
        <Toolbar sx={{ minHeight: 64 }}>
          {isMobile && (
            <IconButton
              edge="start"
              onClick={() => setOpen(true)}
              aria-label="Abrir menu"
              sx={{ mr: 1 }}
            >
              <Menu />
            </IconButton>
          )}
          <Typography variant="body2" color="text.secondary">
            Gerenciamento de pedidos
          </Typography>
          <Typography sx={{ ml: 'auto' }} variant="body2" fontWeight={600}>
            {user?.usuario}
          </Typography>
        </Toolbar>
      </AppBar>

      <Drawer
        variant={isMobile ? 'temporary' : 'permanent'}
        open={open}
        onClose={() => setOpen(false)}
        ModalProps={{ keepMounted: true }}
        sx={{
          width: isMobile ? expandedWidth : drawerWidth,
          flexShrink: 0,
          '& .MuiDrawer-paper': {
            width: isMobile ? expandedWidth : drawerWidth,
            overflowX: 'hidden',
            border: 0,
            transition: theme.transitions.create('width'),
          },
        }}
      >
        {drawer}
      </Drawer>

      <Box
        component="main"
        sx={{
          flexGrow: 1,
          minWidth: 0,
          mt: '64px',
          p: { xs: 2, sm: 3 },
        }}
      >
        <Outlet />
      </Box>

      <Dialog
        open={logoutConfirmationOpen}
        onClose={() => {
          if (!loggingOut) {
            setLogoutConfirmationOpen(false);
          }
        }}
        aria-labelledby="logout-dialog-title"
        aria-describedby="logout-dialog-description"
      >
        <DialogTitle id="logout-dialog-title">Sair do sistema</DialogTitle>
        <DialogContent id="logout-dialog-description">
          Deseja realmente encerrar sua sessao?
        </DialogContent>
        <DialogActions>
          <Button
            onClick={() => setLogoutConfirmationOpen(false)}
            disabled={loggingOut}
          >
            Cancelar
          </Button>
          <Button
            color="error"
            variant="contained"
            onClick={() => void handleLogout()}
            disabled={loggingOut}
            startIcon={<Logout />}
          >
            {loggingOut ? 'Saindo...' : 'Sair'}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}
