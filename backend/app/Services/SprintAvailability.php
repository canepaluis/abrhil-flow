<?php

namespace App\Services;

use App\Models\Sprint;

class SprintAvailability
{
    public function calculate(Sprint $sprint): array
    {
        $sprint->loadMissing(['participants.timeOffs', 'participants.roles', 'cards.assignedTo.timeOffs', 'cards.assignedTo.roles']);
        $dates = $sprint->workingDates();
        $members = $sprint->participants;
        $warnings = [];
        if ($members->isEmpty()) {
            $warnings[] = 'Selecciona los participantes reales del sprint para calcular la capacidad de desarrollo.';
        }
        $absentByUser = [];
        $memberDetails = [];
        foreach ($members as $member) {
            $isDeveloper = $member->roles->contains('slug', 'developer');
            $absent = $this->absentDates($member->timeOffs, $dates);
            if ($isDeveloper) {
                $absentByUser[$member->id] = $absent;
            }
            $estimated = (int) $sprint->cards->where('assigned_to', $member->id)->sum('pivot.estimated_minutes');
            $available = (count($dates) - count($absent)) * $sprint->daily_minutes;
            $memberDetails[] = [
                'id' => $member->id, 'name' => $member->name, 'absent_dates' => $absent,
                'available_minutes' => $available, 'estimated_minutes' => $estimated,
                'counts_toward_capacity' => $isDeveloper,
            ];
            if ($estimated > $available) {
                $warnings[] = $member->name.' tiene más esfuerzo asignado que horas disponibles en el sprint.';
            }
        }
        $cardDetails = [];
        $developmentEstimate = 0;
        $otherEstimate = 0;
        $unclassifiedEstimate = 0;
        foreach ($sprint->cards as $card) {
            $scope = !$card->assignedTo || $card->assignedTo->roles->isEmpty()
                ? 'unclassified' : ($card->assignedTo->roles->contains('slug', 'developer') ? 'development' : 'other');
            match ($scope) {
                'development' => $developmentEstimate += (int) $card->pivot->estimated_minutes,
                'other' => $otherEstimate += (int) $card->pivot->estimated_minutes,
                default => $unclassifiedEstimate += (int) $card->pivot->estimated_minutes,
            };
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
            $cardDetails[$card->id] = ['absence_dates' => $absent, 'available_minutes' => $available, 'planning_warnings' => $cardWarnings, 'capacity_scope' => $scope];
        }
        $developerCount = $members->filter(fn ($member) => $member->roles->contains('slug', 'developer'))->count();
        if ($members->isNotEmpty() && $developerCount === 0) {
            $warnings[] = 'No hay participantes con el rol Desarrollador. La capacidad de desarrollo es cero.';
        }
        if ($unclassifiedEstimate > 0) {
            $warnings[] = 'Hay esfuerzo sin responsable o con roles sin definir. Asígnalo para clasificarlo; no se incluye en la carga de desarrollo.';
        }
        $gross = count($dates) * $sprint->daily_minutes * $developerCount;
        $lost = array_sum(array_map(fn ($absent) => count($absent) * $sprint->daily_minutes, $absentByUser));
        return [
            'gross_capacity_minutes' => $gross, 'absence_minutes' => $lost, 'capacity_minutes' => $gross - $lost,
            'requires_participants' => $members->isEmpty(), 'participants' => $memberDetails,
            'developer_count' => $developerCount,
            'development_estimated_minutes' => $developmentEstimate, 'other_estimated_minutes' => $otherEstimate,
            'unclassified_estimated_minutes' => $unclassifiedEstimate,
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
