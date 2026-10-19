import type { Sprint } from './sprint'
import { dateLabel, hours } from './sprint'

export default function SprintAvailability({ sprint }: { sprint: Sprint }) {
  return <section className="sprint-availability" aria-label="Disponibilidad del equipo">
    {sprint.planning_warnings.map(warning => <p className="notice warning" key={warning}>{warning}</p>)}
    <p className="helper">Desarrolladores: {sprint.developer_count} · Capacidad base: {hours(sprint.gross_capacity_minutes)} · Ausencias de desarrollo: −{hours(sprint.absence_minutes)} · Capacidad neta de desarrollo: {hours(sprint.capacity_minutes)}</p>
    <p className="helper">Esfuerzo de desarrollo: {hours(sprint.development_estimated_minutes)} · Otros roles: {hours(sprint.other_estimated_minutes)} · Sin clasificar: {hours(sprint.unclassified_estimated_minutes)} · Total: {hours(sprint.estimated_minutes)}.</p>
    {sprint.participants.length > 0 && <details><summary>Disponibilidad de {sprint.participants.length} participantes</summary>{sprint.participants.map(member => <div className="availability-member" key={member.id}><strong>{member.name} · {member.counts_toward_capacity ? 'Cuenta en desarrollo' : 'Fuera de la capacidad de desarrollo'}</strong><p className="helper">{hours(member.available_minutes)} disponibles · {hours(member.estimated_minutes)} asignadas{member.absent_dates.length ? ` · Ausencias: ${member.absent_dates.map(dateLabel).join(', ')}` : ' · Sin ausencias en días laborables'}</p></div>)}</details>}
  </section>
}
