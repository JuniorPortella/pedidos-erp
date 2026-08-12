import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError, apiRequest, jsonBody } from './api';

function jsonResponse(data: unknown, status = 200): Response {
  return new Response(JSON.stringify(data), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

describe('apiRequest', () => {
  beforeEach(() => {
    Object.defineProperty(document, 'cookie', {
      configurable: true,
      writable: true,
      value: '',
    });
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('envia cookies em todas as requisicoes', async () => {
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ orders: [] }));
    vi.stubGlobal('fetch', fetchMock);

    await apiRequest('/pedidos');

    expect(fetchMock).toHaveBeenCalledWith(
      'http://localhost:18080/pedidos',
      expect.objectContaining({ credentials: 'include' }),
    );
  });

  it('envia o token CSRF somente em operacoes de escrita', async () => {
    document.cookie = 'csrf_token=token-seguro';
    const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ order: {} }, 201));
    vi.stubGlobal('fetch', fetchMock);

    await apiRequest('/pedidos', {
      method: 'POST',
      body: jsonBody({ cliente_nome: 'Cliente' }),
    });

    const options = fetchMock.mock.calls[0][1] as RequestInit;
    const headers = options.headers as Headers;

    expect(headers.get('X-CSRF-Token')).toBe('token-seguro');
    expect(headers.get('Content-Type')).toBe('application/json');
  });

  it('faz refresh e repete a requisicao que recebeu 401', async () => {
    document.cookie = 'csrf_token=token-seguro';
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse({ error: 'Token invalido.' }, 401))
      .mockResolvedValueOnce(jsonResponse({ user: {} }))
      .mockResolvedValueOnce(jsonResponse({ orders: [] }));
    vi.stubGlobal('fetch', fetchMock);

    const result = await apiRequest<{ orders: [] }>('/pedidos');

    expect(result).toEqual({ orders: [] });
    expect(fetchMock.mock.calls.map(([url]) => url)).toEqual([
      'http://localhost:18080/pedidos',
      'http://localhost:18080/auth/refresh',
      'http://localhost:18080/pedidos',
    ]);
  });

  it('nao tenta refresh para credenciais de login invalidas', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValue(jsonResponse({ error: 'Usuario ou senha invalidos.' }, 401));
    vi.stubGlobal('fetch', fetchMock);

    await expect(
      apiRequest('/auth/login', {
        method: 'POST',
        body: jsonBody({ usuario: 'teste', senha: 'invalida' }),
      }),
    ).rejects.toMatchObject({
      status: 401,
      message: 'Usuario ou senha invalidos.',
    });

    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('preserva os erros de validacao retornados pela API', async () => {
    const fetchMock = vi.fn().mockResolvedValue(
      jsonResponse(
        {
          error: 'Dados invalidos.',
          fields: { cliente_nome: 'Informe o nome do cliente.' },
        },
        422,
      ),
    );
    vi.stubGlobal('fetch', fetchMock);

    try {
      await apiRequest('/pedidos', {
        method: 'POST',
        body: jsonBody({}),
        retryOnUnauthorized: false,
      });
      throw new Error('A requisicao deveria falhar.');
    } catch (error) {
      expect(error).toBeInstanceOf(ApiError);
      expect((error as ApiError).fields).toEqual({
        cliente_nome: 'Informe o nome do cliente.',
      });
    }
  });

  it('consolida refresh simultaneo em uma unica requisicao', async () => {
    document.cookie = 'csrf_token=token-seguro';
    let protectedCalls = 0;
    let refreshCalls = 0;

    const fetchMock = vi.fn(async (url: string) => {
      if (url.endsWith('/auth/refresh')) {
        refreshCalls += 1;
        await Promise.resolve();
        return jsonResponse({ user: {} });
      }

      protectedCalls += 1;
      return protectedCalls <= 2
        ? jsonResponse({ error: 'Token invalido.' }, 401)
        : jsonResponse({ orders: [] });
    });
    vi.stubGlobal('fetch', fetchMock);

    await Promise.all([
      apiRequest('/pedidos'),
      apiRequest('/pedidos'),
    ]);

    expect(refreshCalls).toBe(1);
  });
});
