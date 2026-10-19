<?php

namespace App\Services;

use App\Models\Sprint;

class SprintAvailability
{
    public function calculate(Sprint $sprint): array
    {
        $sprint->loadMissing(['participants.timeOffs', 'cards.assignedTo.timeOffs']);
        $dates = $sprint->workingDates();
        $members = $sprint->participants;
        $warnings = [];
        if ($members->isEmpty()) {
            $warnings[] = 'Selecciona los participantes reales de este sprint para descontar sus ausencias. La capacidad actual es provisional.';
        }
        $absentByUser = [];
        $memberDetails = [];
        foreach ($members as $member) {
            $absent = $this->absentDates($member->timeOffs, $dates);
            $absentByUser[$member->id] = $absent;
            $estimated = (int) $sprint->cards->where('assigned_to', $member->id)->sum('pivot.estimated_minutes');
            $available = (count($dates) - count($absent)) * $sprint->daily_minutes;
            $memberDetails[] = [
                'id' => $member->id, 'name' => $member->name, 'absent_dates' => $absent,
                'available_minutes' => $available, 'estimated_minutes' => $estimated,
            ];
            if ($estimated > $available) {
                $warnings[] = $member->name.' tiene más esfuerzo asignado que horas disponibles en el sprint.';
            }
        }
        $cardDetails = [];
        foreach ($sprint->cards as $card) {
            $cardDates = array_values(array_filter($dates, fn ($date) => $date >= substr($card->pivot->start_date, 0, 10) && $date <= substr($card->pivot->end_date, 0, 10)));
            $absent = [];
            $available = null;
            $cardWarnings = [];
            if ($card->assignedTo) {
                $absent = $this->absentDates($card->assignedTo->timeOffs, $cardDates);
                $available = (count($cardDates) - count($absent)) * $sprint->daily_minutes;
                if (!$members->contains('id', $card->assigned_to)) {
                    $cardWarnings[] = 'El responsable no está incluido entre los participantes del sprint.';
                }
                if ($absent) {
                    $cardWarnings[] = 'La planeación coincide con '.count($absent).' día(s) de ausencia del responsable.';
                }
                if ($card->pivot->estimated_minutes > $available) {
                    $cardWarnings[] = 'El esfuerzo supera las horas disponibles del responsable en estas fechas.';
                }
            } else {
                $cardWarnings[] = 'La tarjeta no tiene responsable; no se puede verificar su disponibilidad.';
            }
            $cardDetails[$card->id] = ['absence_dates' => $absent, 'available_minutes' => $available, 'planning_warnings' => $cardWarnings];
        }
        $gross = count($dates) * $sprint->daily_minutes * ($members->isEmpty() ? $sprint->members_count : $members->count());
        $lost = array_sum(array_map(fn ($absent) => count($absent) * $sprint->daily_minutes, $absentByUser));
        return [
            'gross_capacity_minutes' => $gross, 'absence_minutes' => $lost, 'capacity_minutes' => $gross - $lost,
            'requires_participants' => $members->isEmpty(), 'participants' => $memberDetails,
            'planning_warnings' => $warnings, 'card_availability' => $cardDetails,
        ];
    }

    private function absentDates($absences, array $dates): array
    {
        // Count each working day only once, even when absence records overlap.
        return array_values(array_filter($dates, fn ($date) => $absences->contains(fn ($absence) => !$absence->is_cancelled
            && $absence->start_date->toDateString() <= $date && $absence->end_date->toDateString() >= $date)));
    }
}
