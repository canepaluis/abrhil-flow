import { useState } from 'react'
import { ApiError, request } from './api'
import type { TeamMember } from './team'
import type { Workflow } from './workflow'
import { stages } from './workflow'
import './CardWorkflow.css'

type Props = { id: number; workflow: Workflow; onChanged: () => void }
const fields = [
  ['documenter_id', 'documenter', 'Documentación / diseño'], ['approval_owner_id', 'approval', 'Seguimiento con dirección'],
  ['developer_id', 'developer', 'Desarrollo'], ['tester_id', 'tester', 'Testing'],
] as const

export function WorkflowSummary({ cards, name, selected, onSelect }: { cards: { workflow: Workflow }[]; name: string; selected: string; onSelect: (value: string) => void }) {
  const corrections = cards.filter(card => card.workflow.requires_corrections).length
  return <section className="workflow-summary" aria-label="Etapas del sprint"><div className="section-heading"><h2>Etapas de {name}</h2><button className="text-button" onClick={() => onSelect('all')}>Ver todas ({cards.length})</button></div>
    <div className="workflow-totals">{stages.map(stage => <button className={`workflow-total stage-${stage.key}`} key={stage.key} aria-pressed={selected === stage.key} onClick={() => onSelect(selected === stage.key ? 'all' : stage.key)}><strong>{cards.filter(card => card.workflow.stage === stage.key).length}</strong><span>{stage.label}</span></button>)}<button className="workflow-total stage-corrections" aria-pressed={selected === 'corrections'} onClick={() => onSelect(selected === 'corrections' ? 'all' : 'corrections')}><strong>{corrections}</strong><span>Requieren correcciones</span></button></div>
    {corrections > 0 && <p className="workflow-alert" role="status">{corrections} tarjeta(s) necesitan correcciones solicitadas por dirección o testing. Selecciona el aviso para ver sus observaciones.</p>}
    {cards.some(card => card.workflow.stage === 'cancelled') && <button className="text-button" onClick={() => onSelect('cancelled')}>Canceladas: {cards.filter(card => card.workflow.stage === 'cancelled').length}</button>}
    <p className="helper">La asociación al sprint define el alcance. La aprobación de testing se muestra por separado y no confirma una liberación a producción.</p>
  </section>
}

export default function CardWorkflow({ id, workflow, onChanged }: Props) {
  const [open, setOpen] = useState(false)
  const [users, setUsers] = useState<TeamMember[]>([])
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [action, setAction] = useState('')
  const [note, setNote] = useState('')
  const [created, setCreated] = useState(workflow.created_date || '')
  const [owners, setOwners] = useState({
    documenter_id: String(workflow.owners.documenter?.id || ''), developer_id: String(workflow.owners.developer?.id || ''),
    tester_id: String(workflow.owners.tester?.id || ''), approval_owner_id: String(workflow.owners.approval?.id || ''),
  })
  async function toggle() {
    if (open) { setOpen(false); return }
    setOpen(true); setBusy(true); setError('')
    try { setUsers((await request<{ data: TeamMember[] }>('/users')).data) }
    catch (error) { setError(error instanceof Error ? error.message : 'No se pudo cargar el equipo.') }
    finally { setBusy(false) }
  }
  async function save() {
    setBusy(true); setError('')
    try {
      const body = Object.fromEntries(Object.entries(owners).map(([key, value]) => [key, value ? Number(value) : null]))
      await request(`/cards/${id}/workflow`, { ...body, ...(created ? { requested_at: created } : {}), ...(action ? { state: action, note: note || null } : {}) }, 'PUT')
      onChanged()
    } catch (error) {
      setError(error instanceof ApiError ? [error.message, ...Object.values(error.errors).flat()].join(' ') : error instanceof Error ? error.message : 'No se pudo guardar.')
    } finally { setBusy(false) }
  }
  const index = stages.findIndex(stage => stage.key === workflow.stage)
  const needsNote = workflow.next_actions.find(item => item.state === action)?.requires_note
  return <div className="card-workflow">
    <div className="workflow-card-heading"><span className={`workflow-badge stage-${workflow.stage}`}>{workflow.label}</span><span className="workflow-age">{workflow.age_days ?? '—'} días desde creación{workflow.is_demo ? ' · Ejemplo' : ''}</span></div>
    <div className="workflow-steps" aria-label="Avance por etapas">{stages.map((stage, position) => <span key={stage.key} className={position < index ? 'step-done' : position === index ? 'step-current' : ''}>{stage.label}</span>)}</div>
    <p className="helper">Atiende esta etapa: {workflow.active_owner || 'Sin asignar'}</p>
    {workflow.requires_corrections && <div className="workflow-alert"><strong>Requiere correcciones · {workflow.correction_origin === 'direction' ? 'Dirección' : 'Testing'}</strong><p>{workflow.correction_note}</p></div>}
    <button className="text-button workflow-manage" onClick={() => void toggle()}>{open ? 'Cerrar gestión' : 'Gestionar etapa y responsables'}</button>
    {open && <section className="workflow-editor" aria-label={`Gestionar ${workflow.reference}`}>
      {error && <p className="notice error" role="alert">{error}</p>}
      <fieldset disabled={busy || users.length === 0}><div className="form-grid">{fields.map(([field, owner, label]) => <label key={field}>{label}<select value={owners[field]} onChange={e => { const value = e.target.value; setOwners(current => ({ ...current, [field]: value })) }}><option value="">Sin asignar</option>{users.filter(user => owner !== 'developer' && owner !== 'tester' || user.roles.some(role => role.slug === owner)).map(user => <option key={user.id} value={user.id}>{user.name}</option>)}</select></label>)}</div>
        <label>Fecha original de creación<input type="date" value={created} onChange={e => setCreated(e.target.value)} /></label>
        <label>Acción de la etapa<select value={action} onChange={e => setAction(e.target.value)}><option value="">Solo guardar responsables y fecha</option>{workflow.next_actions.map(item => <option key={item.state} value={item.state}>{item.label}</option>)}</select></label>
        <label>Observaciones {needsNote ? '*' : '(opcional)'}<textarea disabled={!action} rows={3} maxLength={5000} value={note} onChange={e => setNote(e.target.value)} placeholder={needsNote ? 'Describe las correcciones o el motivo de cancelación' : 'Agrega contexto para el historial'} /></label>
        <button className="add-card" disabled={!!needsNote && !note.trim()} onClick={() => void save()}>{busy ? 'Guardando…' : 'Guardar cambios'}</button>
      </fieldset>
      <details className="workflow-history"><summary>Historial de etapas ({workflow.history.length})</summary>{workflow.history.map(event => <div key={event.id}><strong>{event.label}</strong><time>{new Date(event.created_at).toLocaleString('es-MX', { timeZone: 'America/Cancun' })}</time>{event.note && <p>{event.note}</p>}</div>)}{workflow.history.length === 0 && <p className="helper">Sin movimientos registrados. No se deduce el avance a partir de estados anteriores.</p>}</details>
    </section>}
  </div>
}
