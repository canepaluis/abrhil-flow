import { useState } from 'react'
import type { FormEvent } from 'react'
import { ApiError, request } from './api'
import { dateLabel } from './sprint'
import { absenceLabel } from './team'
import type { TeamMember, TimeOff } from './team'

const empty = { type: 'day_off' as TimeOff['type'], start_date: '', end_date: '' }
type Props = { user: TeamMember; onUpdated: (periods: TimeOff[]) => void }

export default function UserTimeOff({ user, onUpdated }: Props) {
  const [form, setForm] = useState(empty)
  const [editing, setEditing] = useState<number | null>(null)
  const [expanded, setExpanded] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError(''); setMessage('')
    try {
      const result = await request<{ data: TimeOff }>(`/users/${user.id}/time-off${editing ? `/${editing}` : ''}`, form, editing ? 'PUT' : 'POST')
      onUpdated([result.data, ...user.time_off.filter(item => item.id !== result.data.id)].sort((a, b) => b.start_date.localeCompare(a.start_date)))
      setForm(empty); setEditing(null); setExpanded(false)
      setMessage('Ausencia guardada. La capacidad y los avisos de los sprints se recalculan al consultarlos.')
    } catch (error) {
      setError(error instanceof ApiError ? [error.message, ...Object.values(error.errors).flat()].join(' ') : error instanceof Error ? error.message : 'No se pudo guardar.')
    } finally { setBusy(false) }
  }
  async function cancel(item: TimeOff) {
    setBusy(true); setError(''); setMessage('')
    try {
      const result = await request<{ data: TimeOff }>(`/users/${user.id}/time-off/${item.id}/status`, { is_cancelled: !item.is_cancelled })
      onUpdated(user.time_off.map(period => period.id === item.id ? result.data : period))
      setMessage(item.is_cancelled ? 'Ausencia reactivada; se descontará de la capacidad.' : 'Ausencia cancelada; ya no se descontará de la capacidad.')
    } catch (error) { setError(error instanceof Error ? error.message : 'No se pudo actualizar.') }
    finally { setBusy(false) }
  }

  return <section className="user-time-off" aria-label={`Ausencias de ${user.name}`}><div className="section-heading"><h4>Días libres y vacaciones</h4><button className="text-button" disabled={busy} onClick={() => { setExpanded(!expanded); setEditing(null); setForm(empty); setError(''); setMessage('') }}>{expanded ? 'Cerrar' : '+ Agregar'}</button></div>
    {error && <p className="notice error" role="alert">{error}</p>}{message && <p className="notice success" role="status">{message}</p>}
    {expanded && <form onSubmit={save}><fieldset disabled={busy}><label>Tipo de ausencia<select value={form.type} onChange={e => setForm(current => ({ ...current, type: e.target.value as TimeOff['type'] }))}><option value="day_off">Día libre</option><option value="vacation">Vacaciones</option></select></label><div className="form-grid"><label>Inicio de ausencia<input type="date" required value={form.start_date} onChange={e => { const value = e.target.value; setForm(current => ({ ...current, start_date: value })) }} /></label><label>Fin de ausencia<input type="date" required min={form.start_date || undefined} value={form.end_date} onChange={e => { const value = e.target.value; setForm(current => ({ ...current, end_date: value })) }} /></label></div><button type="submit" className="add-card">{busy ? 'Guardando…' : editing ? 'Guardar cambios' : 'Registrar ausencia'}</button><p className="helper">Días completos. Se incluyen inicio y fin.</p></fieldset></form>}
    {user.time_off.length === 0 && <p className="helper">Sin ausencias registradas.</p>}
    <div className="absence-list">{user.time_off.map(item => <div className={`absence-row ${item.is_cancelled ? 'absence-cancelled' : ''}`} key={item.id}><strong>{absenceLabel(item.type)}{item.is_cancelled ? ' · Cancelada' : ''}</strong><span>{dateLabel(item.start_date)} — {dateLabel(item.end_date)}</span><div><button className="text-button" disabled={busy} onClick={() => { setForm({ type: item.type, start_date: item.start_date, end_date: item.end_date }); setEditing(item.id); setExpanded(true); setError(''); setMessage('') }}>Editar</button><button className="text-button" disabled={busy || expanded} onClick={() => void cancel(item)}>{item.is_cancelled ? 'Reactivar' : 'Cancelar ausencia'}</button></div></div>)}</div>
  </section>
}
