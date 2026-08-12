export type UserProfile = 'ADMIN' | 'OPERADOR';

export interface AuthUser {
  id: number;
  nome: string;
  email: string;
  usuario: string;
  perfil: UserProfile;
}

export interface User extends AuthUser {
  ativo: boolean;
  criado_em: string;
  atualizado_em: string;
}

export type OrderStatus =
  | 'PENDENTE'
  | 'EM_PROCESSAMENTO'
  | 'CONCLUIDO';

export interface Order {
  id: number;
  cliente_nome: string;
  descricao: string;
  status: OrderStatus;
  criado_por: number;
  created_at: string;
  updated_at: string;
}

export interface ValidationFields {
  [field: string]: string;
}

export interface ErrorResponse {
  error?: string;
  fields?: ValidationFields;
}
