export type Option = { id: number; name: string; team_id?: number }
export type Catalogs = Record<'teams' | 'projects' | 'platforms' | 'statuses' | 'card_types' | 'modules' | 'users', Option[]>
export type Card = { id: number; title: string; priority: number | null; assigned_to: { id: number | null; name: string | null }; status: { name: string | null }; card_type: { name: string | null } }
export class ApiError extends Error {
  errors: Record<string, string[]>
  constructor(message: string, errors: Record<string, string[]> = {}) { super(message); this.errors = errors }
}
export async function request<T>(path: string, body?: Record<string, unknown>, method = body ? 'POST' : 'GET'): Promise<T> {
  const base = import.meta.env.VITE_API_URL?.replace(/\/$/, '')
  if (!base) throw new ApiError('Configura VITE_API_URL en .env y reinicia el frontend.')
  let response: Response
  try {
    response = await fetch(`${base}${path}`, { method, headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) }, ...(body ? { body: JSON.stringify(body) } : {}) })
  } catch { throw new ApiError('No se pudo conectar con Laravel. Comprueba que el servidor esté iniciado.') }
  const data = await response.json().catch(() => null)
  if (!response.ok) throw new ApiError(response.status === 422 ? 'Revisa los campos indicados.' : 'No se pudo completar la solicitud. Inténtalo nuevamente.', data?.errors)
  if (!data) throw new ApiError('El servidor devolvió una respuesta no válida.')
  return data as T
}
