export type PlannedCard = {
  workflow: import('./workflow').Workflow; development_owner_id: number | null
  id: number; title: string; assigned_to: string | null; status: string | null
  archived: boolean; start_date: string; end_date: string; estimated_minutes: number
  is_completed: boolean; description: string; priority: number | null; jira_link: string | null; card_type: string | null
  assigned_to_id: number | null; absence_dates: string[]; available_minutes: number | null; planning_warnings: string[]
  capacity_scope: 'development' | 'other' | 'unclassified'
}
export type Sprint = {
  closure: { ready: boolean; status: 'in_progress' | 'pending_release' | 'finalized'; review_date: string | null; dev_review_date: string | null; dev_review_jira_url: string | null; production_release_date: string | null }
  id: number; name: string; goal: string | null; start_date: string; end_date: string
  members_count: number; daily_minutes: number; working_days: number[]; working_dates: string[]
  capacity_minutes: number; estimated_minutes: number; remaining_minutes: number; cards: PlannedCard[]
  completed_cards: number; completed_minutes: number
  developer_count: number; development_estimated_minutes: number; other_estimated_minutes: number; unclassified_estimated_minutes: number
  gross_capacity_minutes: number; absence_minutes: number; requires_participants: boolean; planning_warnings: string[]
  participants: { id: number; name: string; absent_dates: string[]; available_minutes: number; estimated_minutes: number; counts_toward_capacity: boolean }[]
}
export type DraftCard = { id: number; title: string; start_date: string; end_date: string; hours: string; is_completed: boolean; assigned_to_id: number | null; development_owner_id: number | null }
export type SprintDraft = {
  name: string; goal: string; start_date: string; end_date: string
  participant_ids: number[]; daily_hours: string; working_days: number[]; cards: DraftCard[]
}
export const emptySprint: SprintDraft = {
  name: '', goal: '', start_date: '', end_date: '', participant_ids: [], daily_hours: '8', working_days: [1, 2, 3, 4, 5], cards: [],
}
// UTC avoids daylight-saving changes and date-only strings shifting to the previous day.
export function workingDates(start: string, end: string, days: number[]): string[] {
  const first = new Date(`${start}T00:00:00Z`)
  const last = new Date(`${end}T00:00:00Z`)
  if (!Number.isFinite(first.getTime()) || !Number.isFinite(last.getTime()) || first > last) return []
  const dates: string[] = []
  for (let i = 0; first <= last && i < 367; i++, first.setUTCDate(first.getUTCDate() + 1)) {
    if (days.includes(first.getUTCDay() || 7)) dates.push(first.toISOString().slice(0, 10))
  }
  return dates
}
export const hours = (minutes: number) => `${new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 }).format(minutes / 60)} h`
export const dateLabel = (date: string) => new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T00:00:00Z`))
