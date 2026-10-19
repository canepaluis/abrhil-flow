import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { ApiError, request } from './api'
import type { Card } from './api'
import { dateLabel, emptySprint, hours, workingDates } from './sprint'
import type { DraftCard, Sprint, SprintDraft } from './sprint'
import './SprintPlanner.css'

const weekdays = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']

export default function SprintPlanner() {
  const [sprints, setSprints] = useState<Sprint[]>([])
  const [cards, setCards] = useState<Card[]>([])
  const [draft, setDraft] = useState<SprintDraft>(emptySprint)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [loadError, setLoadError] = useState('')
  const [saveError, setSaveError] = useState('')
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [success, setSuccess] = useState('')
  const [query, setQuery] = useState('')

  async function load() {
    setLoading(true); setLoadError('')
    try {
      const [plans, backlog] = await Promise.all([
        request<{ data: Sprint[] }>('/sprints'), request<{ data: Card[] }>('/cards'),
      ])
      setSprints(plans.data); setCards(backlog.data)
    } catch (error) { setLoadError(error instanceof Error ? error.message : 'No se pudo cargar la planeación.') }
    finally { setLoading(false) }
  }
  useEffect(() => { void load() }, [])

  function newPlan() {
    setEditingId(null); setDraft(emptySprint); setErrors({}); setSaveError(''); setSuccess('')
  }
  function edit(sprint: Sprint) {
    setEditingId(sprint.id); setSelectedId(sprint.id); setErrors({}); setSaveError(''); setSuccess('')
    setDraft({
      name: sprint.name, goal: sprint.goal || '', start_date: sprint.start_date, end_date: sprint.end_date,
      members_count: String(sprint.members_count), daily_hours: String(sprint.daily_minutes / 60), working_days: sprint.working_days,
      cards: sprint.cards.map(card => ({ id: card.id, title: card.title, start_date: card.start_date, end_date: card.end_date, hours: String(card.estimated_minutes / 60), is_completed: card.is_completed })),
    })
  }
  function updateCard(id: number, value: Partial<DraftCard>) {
    setDraft(current => ({ ...current, cards: current.cards.map(card => card.id === id ? { ...card, ...value } : card) }))
  }
  function changeBoundary(key: 'start_date' | 'end_date', value: string) {
    setDraft(current => ({ ...current, [key]: value, cards: current.cards.map(card => ({ ...card, [key]: card[key] === current[key] ? value : card[key] })) }))
  }
  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setSaving(true); setSaveError(''); setErrors({}); setSuccess('')
    try {
      const result = await request<{ data: Sprint }>(editingId ? `/sprints/${editingId}` : '/sprints', {
        name: draft.name, goal: draft.goal || null, start_date: draft.start_date, end_date: draft.end_date,
        members_count: Number(draft.members_count), daily_minutes: Math.round(Number(draft.daily_hours) * 60), working_days: draft.working_days,
        cards: draft.cards.map(card => ({ id: card.id, start_date: card.start_date, end_date: card.end_date, estimated_minutes: Math.round(Number(card.hours) * 60), is_completed: card.is_completed })),
      }, editingId ? 'PUT' : 'POST')
      setSprints(current => [result.data, ...current.filter(sprint => sprint.id !== result.data.id)].sort((a, b) => b.start_date.localeCompare(a.start_date) || b.id - a.id))
      setSelectedId(result.data.id); setEditingId(null); setDraft(emptySprint)
      setSuccess(editingId ? 'Planeación actualizada.' : 'Sprint creado. Ya puedes consultar su planeación.')
    } catch (error) {
      setSaveError(error instanceof Error ? error.message : 'No se pudo guardar el sprint.')
      if (error instanceof ApiError) setErrors(error.errors)
    } finally { setSaving(false) }
  }
  function fieldError(name: string) {
    return errors[name] ? <small className="field-error" id={`sprint-${name}-error`}>{errors[name].join(' ')}</small> : null
  }
  const dates = workingDates(draft.start_date, draft.end_date, draft.working_days)
  const capacity = dates.length * Number(draft.members_count || 0) * Math.round(Number(draft.daily_hours || 0) * 60)
  const effort = draft.cards.reduce((sum, card) => sum + Math.round(Number(card.hours || 0) * 60), 0)
  const selected = sprints.find(sprint => sprint.id === selectedId) || sprints[0]
  const available = cards.filter(card => !draft.cards.some(item => item.id === card.id) && card.title.toLocaleLowerCase().includes(query.toLocaleLowerCase()))

  return <>
    <div className="page-heading"><div><p className="eyebrow">DEL PENDIENTE AL PLAN</p><h1>Planeación de sprints.</h1><p>Crea un nuevo plan o registra un sprint pasado con sus fechas y tarjetas resueltas.</p></div><span className="count">{sprints.length} sprints</span></div>
    {loadError && <div className="notice error" role="alert">{loadError} <button onClick={() => void load()}>Reintentar</button></div>}
    {success && <p className="notice success" role="status">{success}</p>}
    <div className="sprint-layout">
      <section className="form-panel sprint-editor" aria-labelledby="sprint-editor-title">
        <div className="section-heading"><h2 id="sprint-editor-title">{editingId ? 'Editar sprint' : 'Nuevo sprint'}</h2><button className="text-button" disabled={saving} onClick={newPlan}>{editingId ? 'Cancelar edición' : 'Limpiar'}</button></div>
        <p className="form-intro">Las horas representan esfuerzo de trabajo, no horas transcurridas. Las fechas incluyen el día de inicio y de fin.</p>
        {saveError && <div className="notice error" role="alert">{saveError}<ul>{Object.entries(errors).map(([key, messages]) => <li key={key}>{messages.join(' ')}</li>)}</ul></div>}
        <form onSubmit={save}><fieldset disabled={saving || loading || !!loadError}>
          <label>Nombre del sprint *<input required maxLength={255} value={draft.name} onChange={e => { const value = e.target.value; setDraft(current => ({ ...current, name: value })) }} placeholder="Ej. Sprint 01 · Portal de clientes" aria-invalid={!!errors.name} />{fieldError('name')}</label>
          <label>Objetivo<textarea rows={2} maxLength={5000} value={draft.goal} onChange={e => { const value = e.target.value; setDraft(current => ({ ...current, goal: value })) }} placeholder="¿Qué resultado busca el equipo?" />{fieldError('goal')}</label>
          <div className="form-grid">
            <label>Fecha de inicio *<input type="date" required value={draft.start_date} onChange={e => changeBoundary('start_date', e.target.value)} aria-invalid={!!errors.start_date} />{fieldError('start_date')}</label>
            <label>Fecha de fin *<input type="date" required min={draft.start_date || undefined} value={draft.end_date} onChange={e => changeBoundary('end_date', e.target.value)} aria-invalid={!!errors.end_date} />{fieldError('end_date')}</label>
            <label>Integrantes *<input type="number" required min={1} max={1000} step={1} value={draft.members_count} onChange={e => { const value = e.target.value; setDraft(current => ({ ...current, members_count: value })) }} />{fieldError('members_count')}</label>
            <label>Horas diarias por integrante *<input type="number" required min={0.25} max={24} step="any" value={draft.daily_hours} onChange={e => { const value = e.target.value; setDraft(current => ({ ...current, daily_hours: value })) }} />{fieldError('daily_minutes')}</label>
          </div>
          <p className="input-caption" id="workdays-label">Días laborables *</p><div className="weekdays" role="group" aria-labelledby="workdays-label">{weekdays.map((name, index) => <label key={name}><input type="checkbox" checked={draft.working_days.includes(index + 1)} onChange={e => { const checked = e.target.checked; setDraft(current => ({ ...current, working_days: checked ? [...current.working_days, index + 1] : current.working_days.filter(day => day !== index + 1) })) }} />{name}</label>)}</div>{fieldError('working_days')}
          <div className="metrics" aria-live="polite"><div><span>Días laborables</span><strong>{dates.length}</strong></div><div><span>Capacidad</span><strong>{hours(capacity)}</strong></div><div><span>Planeado</span><strong>{hours(effort)}</strong></div></div>
          {effort > capacity && <p className="notice warning" role="status">Sobrecarga de {hours(effort - capacity)}. Puedes guardar el plan y ajustar su alcance después.</p>}
          <h3 className="subheading">Tarjetas del sprint <span>({draft.cards.length})</span></h3>
          {draft.cards.length === 0 && <p className="helper">Selecciona tarjetas del backlog de abajo. También puedes guardar un sprint vacío.</p>}
          <div className="planned-cards">{draft.cards.map((card, index) => <div className="planned-card" key={card.id}>
            <div className="section-heading"><h4>AF-{card.id} · {card.title}</h4><button type="button" className="text-button" aria-label={`Quitar ${card.title}`} onClick={() => setDraft(current => ({ ...current, cards: current.cards.filter(item => item.id !== card.id) }))}>Quitar</button></div>
            {fieldError(`cards.${index}.id`)}
            <label className="completion-checkbox"><input type="checkbox" checked={card.is_completed} onChange={e => updateCard(card.id, { is_completed: e.target.checked })} />Resuelta en este sprint</label>
            <div className="plan-fields"><label>Inicio *<input required type="date" min={draft.start_date || undefined} max={draft.end_date || undefined} value={card.start_date} onChange={e => updateCard(card.id, { start_date: e.target.value })} />{fieldError(`cards.${index}.start_date`)}</label>
              <label>Fin *<input required type="date" min={card.start_date || draft.start_date || undefined} max={draft.end_date || undefined} value={card.end_date} onChange={e => updateCard(card.id, { end_date: e.target.value })} />{fieldError(`cards.${index}.end_date`)}</label>
              <label>Esfuerzo (horas) *<input required type="number" min={0.25} max={16666.5} step="any" value={card.hours} onChange={e => updateCard(card.id, { hours: e.target.value })} placeholder="Ej. 6" />{fieldError(`cards.${index}.estimated_minutes`)}</label></div>
          </div>)}</div>
          <h3 className="subheading">Agregar desde el backlog</h3>
          <label><span className="sr-only">Buscar tarjetas para el sprint</span><input type="search" placeholder="Buscar tarjeta por título…" value={query} onChange={e => setQuery(e.target.value)} /></label>
          <div className="backlog">{available.map(card => <div className="backlog-row" key={card.id}><div><strong>AF-{card.id} · {card.title}</strong><span>{card.assigned_to.name || 'Sin asignar'} · {card.status.name || 'Sin estado'}</span></div><button type="button" className="add-card" aria-label={`Agregar ${card.title}`} onClick={() => setDraft(current => ({ ...current, cards: [...current.cards, { id: card.id, title: card.title, start_date: current.start_date, end_date: current.end_date, hours: '', is_completed: false }] }))}>+ Agregar</button></div>)}{!loading && available.length === 0 && <p className="helper">No hay tarjetas disponibles para esta búsqueda. Puedes crear nuevas en Tarjetas.</p>}</div>
          <button className="primary" type="submit">{saving ? 'Guardando…' : editingId ? 'Guardar cambios del sprint' : 'Crear sprint'}</button>
          <p className="hint">* Obligatorio · Las estimaciones se guardan por sprint.</p>
        </fieldset></form>
      </section>
      <section className="sprint-saved" aria-labelledby="saved-sprints-title">
        <div className="section-heading"><h2 id="saved-sprints-title">Sprints planeados</h2><button className="text-button" disabled={loading || saving} onClick={() => void load()}>Actualizar</button></div>
        {loading ? <p className="empty" role="status">Cargando sprints…</p> : !loadError && sprints.length === 0 ? <div className="empty"><h3>Tu próximo sprint empieza aquí</h3><p>Crea un plan y distribuye el esfuerzo del equipo.</p></div> : <>
          <div className="sprint-picker">{sprints.map(sprint => <button key={sprint.id} aria-pressed={selected?.id === sprint.id} onClick={() => setSelectedId(sprint.id)}><strong>{sprint.name}</strong><span>{dateLabel(sprint.start_date)} — {dateLabel(sprint.end_date)}</span></button>)}</div>
          {selected && <article className="form-panel sprint-detail"><div className="section-heading"><h2>{selected.name}</h2><button className="text-button" disabled={saving} onClick={() => edit(selected)}>Editar plan</button></div><p className="helper">{dateLabel(selected.start_date)} — {dateLabel(selected.end_date)}</p>{selected.goal && <p className="sprint-goal">{selected.goal}</p>}
            <div className="metrics"><div><span>Capacidad</span><strong>{hours(selected.capacity_minutes)}</strong></div><div><span>Planeado</span><strong>{hours(selected.estimated_minutes)}</strong></div></div>
            <p className={selected.remaining_minutes < 0 ? 'notice warning' : 'notice success'}>{selected.remaining_minutes < 0 ? `Sobrecarga: ${hours(-selected.remaining_minutes)}` : `Disponible: ${hours(selected.remaining_minutes)}`}</p>
            <p className="helper">{selected.working_dates.length} días laborables · {selected.members_count} integrantes · {hours(selected.daily_minutes)} por día y persona. No se descuentan festivos ni ausencias.</p>
            <h3 className="subheading">Plan de trabajo</h3>
            <div className="schedule">{selected.cards.map(card => {
              const total = selected.working_dates.length
              const before = selected.working_dates.filter(date => date < card.start_date).length
              const duration = selected.working_dates.filter(date => date >= card.start_date && date <= card.end_date).length
              return <div className="schedule-row" key={card.id}><strong>AF-{card.id} · {card.title}{card.archived ? ' (archivada)' : ''}</strong><span>{card.assigned_to || 'Sin asignar'} · {hours(card.estimated_minutes)}</span><div className="timeline-track" aria-hidden="true"><div className="timeline-bar" style={{ marginLeft: `${total ? before / total * 100 : 0}%`, width: `${total ? duration / total * 100 : 0}%` }} /></div><small>{dateLabel(card.start_date)} — {dateLabel(card.end_date)}</small></div>
            })}{selected.cards.length === 0 && <p className="helper">Este sprint todavía no tiene tarjetas. Usa Editar plan para agregarlas.</p>}</div>
            <p className="hint">Las barras indican fechas planeadas, no avance. La capacidad es global; no distribuye automáticamente las tareas por persona.</p>
          </article>}
        </>}
      </section>
    </div>
  </>
}
