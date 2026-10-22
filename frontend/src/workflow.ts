export const stages = [
  { key: 'documentation', label: 'Documentación' }, { key: 'approval', label: 'Dirección' },
  { key: 'development', label: 'Desarrollo' }, { key: 'testing', label: 'Testing' },
  { key: 'approved', label: 'Testing aprobado' },
]
export type WorkflowUpdate = { id: number; workflow: Workflow; card: import('./api').Card; sprints: import('./sprint').Sprint[] }
export type Workflow = {
  state: string; label: string; stage: string; requires_corrections: boolean
  correction_origin: 'direction' | 'testing' | null; correction_note: string | null
  created_date: string | null; age_days: number | null; is_demo: boolean; reference: string
  next_actions: { state: string; label: string; requires_note: boolean }[]
  owners: Record<'documenter' | 'developer' | 'tester' | 'approval', { id: number; name: string } | null>
  active_owner: string | null; active_owner_id: number | null
  history: { id: number; from_state: string | null; to_state: string; label: string; note: string | null; created_at: string }[]
}
