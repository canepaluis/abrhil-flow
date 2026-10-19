export type TimeOff = { id: number; type: 'day_off' | 'vacation'; start_date: string; end_date: string; is_cancelled: boolean }
export type TeamMember = { id: number; name: string; roles: { id: number; slug: string; name: string }[]; responsibilities: string[]; time_off: TimeOff[] }
export const absenceLabel = (type: TimeOff['type']) => type === 'vacation' ? 'Vacaciones' : 'Día libre'
export function absentDates(user: TeamMember, dates: string[]): string[] {
  return dates.filter(date => user.time_off.some(absence => !absence.is_cancelled && absence.start_date <= date && absence.end_date >= date))
}
