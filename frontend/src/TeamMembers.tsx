import { useEffect, useState } from 'react'
import { request } from './api'
import './TeamMembers.css'
import type { TeamMember } from './team'
import UserTimeOff from './UserTimeOff'

export default function TeamMembers() {
  const [users, setUsers] = useState<TeamMember[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [query, setQuery] = useState('')
  const [role, setRole] = useState('')

  async function load() {
    setLoading(true); setError('')
    try {
      const result = await request<{ data: TeamMember[] }>('/users')
      setUsers(result.data)
    } catch (error) { setError(error instanceof Error ? error.message : 'No se pudo cargar el equipo.') }
    finally { setLoading(false) }
  }
  useEffect(() => { void load() }, [])
  const roles = [...new Map(users.flatMap(user => user.roles).map(item => [item.slug, item])).values()]
  const visible = users.filter(user => (!role || user.roles.some(item => item.slug === role)) && `${user.name} ${user.responsibilities.join(' ')}`.toLocaleLowerCase().includes(query.toLocaleLowerCase()))

  return <>
    <div className="page-heading"><div><p className="eyebrow">LAS PERSONAS DETRÁS DE CADA ENTREGA</p><h1>Nuestro equipo.</h1><p>Conoce quién participa y qué responsabilidades asume en el trabajo.</p></div><span className="count">{users.length} integrantes</span></div>
    <div className="section-heading"><h2>Usuarios y roles</h2><button className="text-button" disabled={loading} onClick={() => void load()}>Actualizar</button></div>
    <div className="team-filters"><label>Buscar persona o responsabilidad<input type="search" placeholder="Ej. Fernando, planeación o testing…" value={query} onChange={e => setQuery(e.target.value)} /></label><label>Rol<select value={role} onChange={e => setRole(e.target.value)}><option value="">Todos los roles</option>{roles.map(item => <option key={item.slug} value={item.slug}>{item.name}</option>)}</select></label></div>
    {error && <p className="notice error" role="alert">{error}</p>}
    {loading ? <p className="empty" role="status">Cargando equipo…</p> : !error && <div className="team-grid">{visible.map(user => <article className="card team-member" key={user.id}><div className="team-person"><span className="team-avatar" aria-hidden="true">{user.name.split(' ').slice(0, 2).map(part => part[0]).join('')}</span><h3>{user.name}</h3></div><div className="team-roles" aria-label="Roles">{user.roles.map(item => <span className="badge" key={item.slug}>{item.name}</span>)}{user.roles.length === 0 && <span className="helper">Sin roles definidos</span>}</div><h4>Responsabilidades</h4>{user.responsibilities.length ? <ul>{user.responsibilities.map(item => <li key={item}>{item}</li>)}</ul> : <p className="helper">Pendientes de definir.</p>}<UserTimeOff user={user} onUpdated={periods => setUsers(current => current.map(item => item.id === user.id ? { ...item, time_off: periods } : item))} /></article>)}</div>}
    {!loading && !error && visible.length === 0 && <p className="empty">No hay integrantes con estos filtros.</p>}
    <p className="helper team-note">Una persona puede tener varios roles. Las responsabilidades describen su trabajo; los permisos de acceso se definirán al incorporar el inicio de sesión.</p>
  </>
}
