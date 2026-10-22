import { useEffect, useState } from 'react'
import { request } from './api'
import { dateLabel, hours } from './sprint'
import type { Sprint } from './sprint'
import './SprintPlanner.css'
import './SprintOverview.css'
import SprintAvailability from './SprintAvailability'
import CardWorkflow, { WorkflowSummary } from './CardWorkflow'
import { stages } from './workflow'
import SprintClosure from './SprintClosure'

type CurrentSprint = { data: Sprint | null; mode: 'automatic' | 'manual'; today: string; timezone: string }
type Props = { history: boolean; onPlan: () => void }

export default function SprintOverview({ history, onPlan }: Props) {
  const [current, setCurrent] = useState<CurrentSprint | null>(null)
  const [sprints, setSprints] = useState<Sprint[]>([])
  const [past, setPast] = useState<Sprint[]>([])
  const [selection, setSelection] = useState('')
  const [pastId, setPastId] = useState('')
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [loadError, setLoadError] = useState('')
  const [actionError, setActionError] = useState('')
  const [message, setMessage] = useState('')
  const [filter, setFilter] = useState('all')
  const [query, setQuery] = useState('')
  const [stageFilter, setStageFilter] = useState('all')

  async function load() {
    setLoading(true); setLoadError('')
    try {
      const [active, all, archived] = await Promise.all([
        request<CurrentSprint>('/sprints/current'), request<{ data: Sprint[] }>('/sprints'), request<{ data: Sprint[] }>('/sprints/history'),
      ])
      setCurrent(active); setSprints(all.data); setPast(archived.data)
      setSelection(active.mode === 'manual' && active.data ? String(active.data.id) : '')
    } catch (error) { setLoadError(error instanceof Error ? error.message : 'No se pudieron cargar los sprints.') }
    finally { setLoading(false) }
  }
  useEffect(() => { void load() }, [])

  async function configure() {
    setBusy(true); setActionError(''); setMessage('')
    try {
      const result = await request<CurrentSprint>('/sprints/current', { sprint_id: selection ? Number(selection) : null })
      setCurrent(result)
      setMessage(result.mode === 'manual' ? 'Sprint actual configurado para el sistema.' : 'Selección automática activada.')
    } catch (error) { setActionError(error instanceof Error ? error.message : 'No se pudo cambiar el sprint actual.') }
    finally { setBusy(false) }
  }
  function updateCard(update: import('./workflow').WorkflowUpdate) {
    const updated = new Map(update.sprints.map(sprint => [sprint.id, sprint]))
    setCurrent(value => value?.data && updated.has(value.data.id) ? { ...value, data: updated.get(value.data.id)! } : value)
    setPast(value => value.map(item => updated.get(item.id) || item))
    setSprints(value => value.map(item => updated.get(item.id) || item))
  }

  function updateClosure(sprint: Sprint) {
    setCurrent(value => value?.data?.id === sprint.id ? { ...value, data: sprint } : value)
    setSprints(value => value.map(item => item.id === sprint.id ? sprint : item))
    setPast(value => value.some(item => item.id === sprint.id) ? value.map(item => item.id === sprint.id ? sprint : item) : sprint.closure.status === 'finalized' ? [sprint, ...value] : value)
  }

  const sprint = history ? past.find(item => String(item.id) === pastId) || past[0] : current?.data
  const today = current?.today || ''
  const pending = sprint?.cards.filter(card => !card.is_completed && card.workflow.stage !== 'cancelled') || []
  const todayCards = sprint?.working_dates.includes(today) ? pending.filter(card => card.start_date <= today && card.end_date >= today) : []
  const overdue = pending.filter(card => card.end_date < today)
  const visible = sprint?.cards.filter(card => (stageFilter === 'all' || (stageFilter === 'corrections' ? card.workflow.requires_corrections : card.workflow.stage === stageFilter)) && (filter === 'all' || (filter === 'done' ? card.is_completed : !card.is_completed)) && `${card.title} ${card.workflow.reference} ${card.workflow.active_owner || ''} ${card.assigned_to || ''}`.toLocaleLowerCase().includes(query.toLocaleLowerCase())) || []
  const progress = sprint?.cards.length ? Math.round(sprint.completed_cards / sprint.cards.length * 100) : 0

  return <>
    <div className="page-heading"><div><p className="eyebrow">{history ? 'EL TRABAJO QUE YA RECORRIMOS' : 'EL EQUIPO, EN EL MISMO RUMBO'}</p><h1>{history ? 'Historial de sprints.' : 'Tu sprint, de un vistazo.'}</h1><p>{history ? 'Consulta periodos anteriores y las tarjetas resueltas en cada sprint.' : 'Consulta el objetivo, la planeación y las tareas del sprint actual.'}</p></div><button className="add-card" onClick={onPlan}>{history ? '+ Agregar sprint pasado' : 'Planear un sprint'}</button></div>
    <div className="overview-toolbar"><button className="text-button" disabled={loading || busy} onClick={() => void load()}>Actualizar</button>{current && <span>{dateLabel(current.today)} · {current.timezone}</span>}</div>
    {loadError && <p className="notice error" role="alert">{loadError}</p>}
    {actionError && <p className="notice error" role="alert">{actionError}</p>}
    {message && <p className="notice success" role="status">{message}</p>}
    {loading ? <p className="empty" role="status">Cargando sprints…</p> : !loadError && <>
      {sprint && <SprintClosure key={sprint.id} sprint={sprint} today={today} onChanged={updateClosure} />}
      {sprint && <WorkflowSummary name={sprint.name} cards={sprint.cards} selected={stageFilter} onSelect={value => { setStageFilter(value); setFilter('all'); document.getElementById('overview-tasks-title')?.scrollIntoView({ behavior: 'smooth', block: 'start' }) }} />}
      {sprint?.cards.some(card => card.workflow.is_demo) && <p className="notice">Este sprint incluye tarjetas de ejemplo identificadas como DEMO-FLOW. Son escenarios ficticios para evaluar el seguimiento.</p>}
      {history ? <section className="overview-config form-panel"><label>Sprint pasado<select value={sprint ? String(sprint.id) : ''} disabled={busy || past.length === 0} onChange={e => { setPastId(e.target.value); setFilter('all'); setMessage('') }}><option value="" disabled>Selecciona un sprint</option>{past.map(item => <option key={item.id} value={item.id}>{item.name} · {dateLabel(item.start_date)} — {dateLabel(item.end_date)} · {item.completed_cards}/{item.cards.length} resueltas · {item.closure.status === 'finalized' ? 'Finalizado' : 'Cierre pendiente'}</option>)}</select></label><p className="helper">Aquí aparecen los sprints de periodos anteriores y los finalizados anticipadamente. Para cargar uno anterior, usa Agregar sprint pasado y captura sus fechas reales y tarjetas resueltas.</p></section> : <section className="overview-config form-panel" aria-label="Configuración del sprint actual"><label>Sprint actual<select value={selection} disabled={busy} onChange={e => setSelection(e.target.value)}><option value="">Automático según el último sprint creado</option>{sprints.map(item => <option key={item.id} value={item.id}>{item.name} · {dateLabel(item.start_date)} — {dateLabel(item.end_date)}</option>)}</select></label><button className="add-card" disabled={busy} onClick={() => void configure()}>{busy ? 'Guardando…' : 'Aplicar selección'}</button><p className="helper">Automático: se muestra el último sprint creado dentro de sus fechas o mientras tenga pendiente el cierre tras la aprobación de testing. La selección manual prevalece hasta que vuelvas a automático y se comparte entre usuarios.</p></section>}
      {!sprint ? <section className="empty"><h2>{history ? 'Todavía no hay sprints pasados' : 'No hay un sprint actual'}</h2><p>{history ? 'Puedes registrar un periodo anterior desde Planeación de sprints.' : 'El último sprint creado no incluye hoy, o aún no has creado ninguno. Selecciona uno manualmente o crea un nuevo plan.'}</p></section> : <>
        <section className="form-panel overview-summary"><div className="section-heading"><div><p className="eyebrow">{history ? 'SPRINT PASADO' : current?.mode === 'manual' ? 'SPRINT ACTUAL · MANUAL' : 'SPRINT ACTUAL · AUTOMÁTICO'}</p><h2>{sprint.name}</h2></div><span className="count">{dateLabel(sprint.start_date)} — {dateLabel(sprint.end_date)}</span></div>{sprint.goal && <p className="sprint-goal">{sprint.goal}</p>}
          {!history && (today < sprint.start_date || today > sprint.end_date) && <p className="notice warning">{current?.mode === 'manual' ? 'Este sprint está fijado manualmente, aunque sus fechas no incluyen el día de hoy.' : 'El periodo planeado terminó. El sprint sigue visible porque su cierre está pendiente.'}</p>}
          <div className="metrics"><div><span>Resueltas / total</span><strong>{sprint.completed_cards} / {sprint.cards.length}</strong></div><div><span>Planeado desarrollo</span><strong>{hours(sprint.development_estimated_minutes)}</strong></div><div><span>Capacidad desarrollo</span><strong>{hours(sprint.capacity_minutes)}</strong></div><div><span>Esfuerzo de resueltas</span><strong>{hours(sprint.completed_minutes)}</strong></div>{!history && <><div><span>Pendientes para hoy</span><strong>{todayCards.length}</strong></div><div><span>Pendientes vencidas</span><strong>{overdue.length}</strong></div></>}</div>
          <SprintAvailability sprint={sprint} />
          <label className="progress-label">Tarjetas resueltas: {progress}%<progress max={100} value={progress} /></label>
          <p className="helper">La tarjeta queda resuelta automáticamente al aprobar testing. En sprints pasados se conserva el avance registrado al cierre; los registros históricos importados se mantienen. El esfuerzo de resueltas corresponde a la estimación, no al tiempo real trabajado.</p>
          {sprint.remaining_minutes < 0 && <p className="notice warning">El esfuerzo de desarrollo supera la capacidad por {hours(-sprint.remaining_minutes)}.</p>}
        </section>
        <section aria-labelledby="overview-tasks-title"><div className="section-heading"><h2 id="overview-tasks-title">{history ? 'Tarjetas del sprint pasado' : 'Planeación de tareas'}</h2></div><div className="overview-filters"><label>Etapa<select value={stageFilter} onChange={e => setStageFilter(e.target.value)}><option value="all">Todas las etapas</option>{stages.map(stage => <option key={stage.key} value={stage.key}>{stage.label}</option>)}<option value="corrections">Requieren correcciones</option><option value="cancelled">Canceladas</option></select></label><label>Buscar tarjeta o responsable<input type="search" value={query} onChange={e => setQuery(e.target.value)} placeholder="Buscar…" /></label><label>Resolución<select value={filter} onChange={e => setFilter(e.target.value)}><option value="all">Todas</option><option value="pending">Pendientes</option><option value="done">Resueltas</option></select></label></div>
          <div className="overview-cards">{visible.map(card => <article key={card.id} className={`card overview-task ${card.is_completed ? 'task-done' : ''}`}><div className="card-top"><span className="card-id">{card.workflow.reference} · {card.card_type || 'Sin tipo'}</span><span className="badge">{card.is_completed ? 'Resuelta en este sprint' : 'Pendiente'}</span></div><h3>{card.title}{card.archived ? ' (archivada)' : ''}</h3><p className="task-description">{card.description}</p><CardWorkflow id={card.id} workflow={card.workflow} onChanged={updateCard} /><dl><div><dt>Responsable</dt><dd>{card.workflow.active_owner || card.assigned_to || 'Sin asignar'}</dd></div><div><dt>Fechas planeadas</dt><dd>{dateLabel(card.start_date)} — {dateLabel(card.end_date)}</dd></div><div><dt>Esfuerzo</dt><dd>{hours(card.estimated_minutes)}</dd></div><div><dt>Prioridad</dt><dd>{card.priority ?? 'Sin definir'}</dd></div><div><dt>Estado actual de la tarjeta</dt><dd>{card.workflow.label || 'Sin estado'}</dd></div></dl>
            {!history && !card.is_completed && card.workflow.stage !== 'cancelled' && card.end_date < today && <p className="task-overdue">Pendiente fuera de su fecha planeada</p>}
            {card.planning_warnings.map(warning => <p className="notice warning" key={warning}>{warning}</p>)}<div className="task-actions">{card.jira_link && /^https?:\/\//i.test(card.jira_link) && <a href={card.jira_link} target="_blank" rel="noreferrer">Ver en Jira ↗</a>}</div>
          </article>)}</div>{visible.length === 0 && <p className="empty">{sprint.cards.length === 0 ? 'Este sprint aún no tiene tarjetas. Agrégalas en Planeación de sprints.' : 'No hay tarjetas con estos filtros.'}</p>}
        </section>
      </>}
    </>}
  </>
}
