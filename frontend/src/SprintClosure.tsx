import { useState } from 'react'
import { ApiError, request } from './api'
import { dateLabel } from './sprint'
import type { Sprint } from './sprint'

const fields = [['review_date', 'Fecha del review'], ['dev_review_date', 'Fecha de revisión en desarrollo'], ['dev_review_jira_url', 'Tarjeta de Jira de revisión en desarrollo (opcional)'], ['production_release_date', 'Fecha de liberación a producción']] as const

export default function SprintClosure({ sprint, today, onChanged }: { sprint: Sprint; today: string; onChanged: (sprint: Sprint) => void }) {
  const closure = sprint.closure
  const [open, setOpen] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [draft, setDraft] = useState({ review_date: closure.review_date || '', dev_review_date: closure.dev_review_date || '', dev_review_jira_url: closure.dev_review_jira_url || '', production_release_date: closure.production_release_date || '' })
  const finalized = closure.status === 'finalized'
  async function save() {
    setBusy(true); setError(''); setMessage('')
    try {
      const body = Object.fromEntries(fields.filter(([key]) => draft[key] !== (closure[key] || '')).map(([key]) => [key, draft[key] || null]))
      if (!Object.keys(body).length) return
      const result = await request<{ data: Sprint }>(`/sprints/${sprint.id}/closure`, body, 'PUT')
      onChanged(result.data)
      setMessage(result.data.closure.status === 'finalized' ? 'Sprint finalizado: liberación a producción registrada.' : 'Avance guardado. La liberación a producción sigue pendiente.')
      setOpen(false)
    } catch (error) {
      setError(error instanceof ApiError ? [error.message, ...Object.values(error.errors).flat()].join(' ') : error instanceof Error ? error.message : 'No se pudo guardar el cierre.')
    } finally { setBusy(false) }
  }
  return <section className={`form-panel sprint-closure ${finalized ? 'closure-finalized' : ''}`} aria-label="Cierre del sprint">
    <div className="section-heading"><div><p className="eyebrow">CIERRE DEL SPRINT</p><h2>{finalized ? 'Sprint finalizado' : closure.status === 'pending_release' ? 'Pendiente de liberación a producción' : 'Cierre pendiente'}</h2></div>{!finalized && closure.ready && <button className="add-card" onClick={() => setOpen(value => !value)}>{open ? 'Ocultar formulario' : 'Registrar cierre del sprint'}</button>}</div>
    <p className="helper">{finalized ? 'La liberación a producción confirma la finalización del sprint.' : closure.ready ? 'Todas las tarjetas están aprobadas por testing. Puedes registrar el cierre sin esperar a la fecha final planeada.' : 'Todas las tarjetas deben estar aprobadas por testing para habilitar el cierre. Un sprint vacío o con tarjetas canceladas no cumple esa condición.'}</p>
    <dl className="closure-dates"><div><dt>Review del sprint</dt><dd>{closure.review_date ? dateLabel(closure.review_date) : 'Pendiente'}</dd></div><div><dt>Revisión en desarrollo</dt><dd>{closure.dev_review_date ? dateLabel(closure.dev_review_date) : 'Pendiente'}</dd>{closure.dev_review_jira_url && <dd><a href={closure.dev_review_jira_url} target="_blank" rel="noreferrer">Tarjeta de Jira ↗</a></dd>}</div><div><dt>Liberación a producción</dt><dd>{closure.production_release_date ? dateLabel(closure.production_release_date) : 'Pendiente · necesaria para finalizar'}</dd></div></dl>
    {message && <p className="notice success" role="status">{message}</p>}{error && <p className="notice error" role="alert">{error}</p>}
    {open && !finalized && closure.ready && <form onSubmit={event => { event.preventDefault(); void save() }}><fieldset disabled={busy}><div className="form-grid">{fields.map(([key, label]) => <label key={key}>{label}<input type={key === 'dev_review_jira_url' ? 'url' : 'date'} maxLength={2048} max={key === 'dev_review_jira_url' ? undefined : today} min={key === 'review_date' ? sprint.start_date : key === 'dev_review_date' ? draft.review_date || sprint.start_date : key === 'production_release_date' ? draft.dev_review_date || draft.review_date || sprint.start_date : undefined} value={draft[key]} onChange={event => setDraft(value => ({ ...value, [key]: event.target.value }))} /></label>)}</div><p className="helper">Guarda las fechas conforme se realicen. Registrar la liberación a producción finaliza el sprint y conserva su planeación.</p><button className="add-card">{busy ? 'Guardando…' : draft.production_release_date ? 'Guardar y finalizar sprint' : 'Guardar avance del cierre'}</button></fieldset></form>}
  </section>
}
