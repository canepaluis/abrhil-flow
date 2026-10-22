import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { ApiError, request } from './api'
import type { Card, Catalogs } from './api'
import './App.css'
import SprintPlanner from './SprintPlanner'
import SprintOverview from './SprintOverview'
import TeamMembers from './TeamMembers'
import CardWorkflow from './CardWorkflow'

const initial = { title: '', description: '', team_id: '', project_id: '', platform_id: '', status_id: '', card_type_id: '', assigned_to: '', module_id: '', priority: '', jira_link: '' }
const selects = [
  ['team_id', 'Equipo', 'teams', true], ['project_id', 'Proyecto', 'projects', true],
  ['platform_id', 'Plataforma', 'platforms', true], ['card_type_id', 'Tipo de card', 'card_types', true],
  ['assigned_to', 'Responsable', 'users', false],
  ['module_id', 'Módulo', 'modules', false],
] as const

function App() {
  const [page, setPage] = useState<'home' | 'cards' | 'sprints' | 'history' | 'users'>('home')
  const [cards, setCards] = useState<Card[]>([])
  const [catalogs, setCatalogs] = useState<Catalogs | null>(null)
  const [form, setForm] = useState(initial)
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
      const [list, options] = await Promise.all([
        request<{ data: Card[] }>('/cards'), request<{ data: Catalogs }>('/card-catalogs'),
      ])
      setCards(list.data); setCatalogs(options.data)
    } catch (error) { setLoadError(error instanceof Error ? error.message : 'No se pudieron cargar los datos.') }
    finally { setLoading(false) }
  }
  useEffect(() => { void load() }, [])

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSaving(true); setSaveError(''); setErrors({}); setSuccess('')
    const body: Record<string, unknown> = { ...form }
    for (const key of ['team_id', 'project_id', 'platform_id', 'status_id', 'card_type_id', 'assigned_to', 'module_id', 'priority'] as const) {
      body[key] = form[key] === '' ? null : Number(form[key])
    }
    body.jira_link = form.jira_link || null
    body.status_id = catalogs?.statuses.find(status => status.name.toLocaleLowerCase() === 'documentación')?.id
    try {
      const result = await request<{ data: Card }>('/cards', body)
      setCards(current => [result.data, ...current]); setForm(initial)
      setSuccess(`Card #${result.data.id} creada correctamente.`)
    } catch (error) {
      setSaveError(error instanceof Error ? error.message : 'No se pudo crear la card.')
      if (error instanceof ApiError) setErrors(error.errors)
    } finally { setSaving(false) }
  }
  function fieldError(name: string) {
    return errors[name] ? <small className="field-error" id={`${name}-error`}>{errors[name].join(' ')}</small> : null
  }
  const missingCatalogs = catalogs && (selects.some(([, , catalog, required]) => required && catalogs[catalog].length === 0) || !catalogs.statuses.some(status => status.name.toLocaleLowerCase() === 'documentación'))
  const visibleCards = cards.filter(card => card.title.toLocaleLowerCase().includes(query.toLocaleLowerCase()))

  return (
    <div className="shell">
      <header className="topbar"><a className="brand" href="/"><span className="brand-icon">a</span>Abrhil<span>Flow</span></a><span className="workspace">ESPACIO DE TRABAJO</span></header>
      <main>
        <nav className="module-nav" aria-label="Módulos">
          <button aria-current={page === 'home' ? 'page' : undefined} onClick={() => setPage('home')}>Inicio</button>
          <button aria-current={page === 'cards' ? 'page' : undefined} onClick={() => setPage('cards')}>Tarjetas</button>
          <button aria-current={page === 'sprints' ? 'page' : undefined} onClick={() => setPage('sprints')}>Planeación de sprints</button>
          <button aria-current={page === 'history' ? 'page' : undefined} onClick={() => setPage('history')}>Historial</button>
          <button aria-current={page === 'users' ? 'page' : undefined} onClick={() => setPage('users')}>Equipo</button>
        </nav>
        {page === 'users' ? <TeamMembers /> : page === 'home' || page === 'history' ? <SprintOverview key={page} history={page === 'history'} onPlan={() => setPage('sprints')} /> : page === 'sprints' ? <SprintPlanner /> : <>
        <div className="page-heading"><div><p className="eyebrow">TU EQUIPO, EN MOVIMIENTO</p><h1>Un lugar para cada tarea.</h1><p>Organiza las cards de tu equipo y convierte los pendientes en avances.</p></div><span className="count">{loading ? '…' : cards.length} cards</span></div>
        {loadError && <div className="notice error" role="alert">{loadError} <button onClick={() => void load()}>Reintentar</button></div>}
        {success && <div className="notice success" role="status">{success}</div>}
        <div className="layout">
          <section className="list-panel" aria-labelledby="cards-heading">
            <div className="section-heading"><h2 id="cards-heading">Cards del equipo</h2><button className="text-button" disabled={loading || saving} onClick={() => void load()}>Actualizar</button></div>
            <label className="search"><span className="sr-only">Buscar por título</span><input type="search" placeholder="Buscar una card por título…" value={query} onChange={e => setQuery(e.target.value)} /></label>
            {loading ? <p className="empty" role="status">Cargando cards…</p> : loadError ? <p className="empty">La lista no pudo actualizarse.</p> : visibleCards.length === 0 ? <div className="empty"><span className="empty-icon" aria-hidden="true">▤</span><h3>{query ? 'Sin coincidencias' : 'Todo empieza con una card'}</h3><p>{query ? 'Prueba con otro título.' : 'Agrega tu primera tarea con el formulario.'}</p></div> : <div className="cards">{visibleCards.map(card => <article className="card" key={card.id}>
              <div className="card-top"><span className="card-id">{card.workflow.reference}</span></div>
              <h3>{card.title}</h3><CardWorkflow id={card.id} workflow={card.workflow} onChanged={update => setCards(current => current.map(item => item.id === update.id ? update.card : item))} /><span className="type">{card.card_type.name || 'Sin tipo'}</span>
              <div className="card-footer"><span className="assignee"><span className="avatar">{card.assigned_to.name?.charAt(0).toUpperCase() || '—'}</span>{card.assigned_to.name || 'Sin asignar'}</span><span>Prioridad {card.priority ?? '—'}</span></div>
            </article>)}</div>}
          </section>
          <section className="form-panel" aria-labelledby="new-heading"><p className="eyebrow">DA EL SIGUIENTE PASO</p><h2 id="new-heading">Nueva card</h2><p className="form-intro">Las tarjetas nuevas comienzan en Por documentar. Después podrás asignar los responsables por etapa.</p>
            {missingCatalogs && <p className="notice error" role="alert">Faltan catálogos obligatorios. Carga equipos, proyectos, plataformas, tipos y estados en el backend y pulsa Actualizar.</p>}
            {saveError && <p className="notice error" role="alert">{saveError}</p>}
            <form onSubmit={save}><fieldset disabled={saving || loading || !catalogs || !!loadError || !!missingCatalogs}>
              <label>Título *<input name="title" required maxLength={255} placeholder="¿Qué hay que hacer?" value={form.title} onChange={e => setForm({ ...form, title: e.target.value })} aria-invalid={!!errors.title} aria-describedby={errors.title ? 'title-error' : undefined} />{fieldError('title')}</label>
              <label>Descripción *<textarea name="description" required maxLength={255} rows={3} placeholder="Agrega contexto y el resultado esperado" value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} aria-invalid={!!errors.description} aria-describedby={errors.description ? 'description-error' : undefined} />{fieldError('description')}</label>
              <div className="form-grid">{selects.map(([name, label, catalog, required]) => <label key={name}>{label}{required ? ' *' : ''}<select name={name} required={required} value={form[name]} aria-invalid={!!errors[name]} aria-describedby={errors[name] ? `${name}-error` : undefined} onChange={e => setForm({ ...form, [name]: e.target.value, ...(name === 'team_id' ? { project_id: '' } : {}) })}>
                <option value="">{required ? 'Selecciona…' : 'Sin asignar'}</option>{catalogs?.[catalog].filter(option => catalog !== 'projects' || !form.team_id || option.team_id === Number(form.team_id)).map(option => <option key={option.id} value={option.id}>{option.name}</option>)}
              </select>{fieldError(name)}</label>)}
              <label>Prioridad<input name="priority" type="number" min={0} max={2147483647} placeholder="Opcional" value={form.priority} onChange={e => setForm({ ...form, priority: e.target.value })} aria-invalid={!!errors.priority} aria-describedby={errors.priority ? 'priority-error' : undefined} />{fieldError('priority')}</label></div>
              <label>Enlace de Jira<input name="jira_link" type="url" maxLength={255} placeholder="https://tu-equipo.atlassian.net/…" value={form.jira_link} onChange={e => setForm({ ...form, jira_link: e.target.value })} aria-invalid={!!errors.jira_link} aria-describedby={errors.jira_link ? 'jira_link-error' : undefined} />{fieldError('jira_link')}</label>
              <button className="primary" type="submit">{saving ? 'Creando card…' : '+ Crear card'}</button><p className="hint">* Campos obligatorios</p>
            </fieldset></form>
          </section>
        </div>
        </>}
      </main><footer>Abrhil Flow · Un paso a la vez, en equipo.</footer>
    </div>
  )
}
export default App
