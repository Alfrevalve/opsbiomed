<?php

namespace App\Http\Controllers;

use App\Models\OperationalAlert;
use App\Models\SurgeryCase;
use App\Services\Operations\SurgeryScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(SurgeryScheduleService $scheduleService): View
    {
        $from = now();
        $until = $from->copy()->addHours(48);

        return $this->agendaView(
            $this->withSlaAlerts($scheduleService->forRange($from, $until)),
            'Proximas 48 horas',
            'Cirugias programadas y alertas operativas de los proximos dos dias.',
            'next48',
            $from,
        );
    }

    public function day(Request $request, SurgeryScheduleService $scheduleService): View
    {
        $date = $this->selectedDate($request);

        return $this->agendaView(
            $this->withSlaAlerts($scheduleService->forRange($date->copy()->startOfDay(), $date->copy()->endOfDay())),
            'Agenda diaria',
            'Programacion, reservas y recursos para la fecha seleccionada.',
            'day',
            $date,
        );
    }

    public function week(Request $request, SurgeryScheduleService $scheduleService): View
    {
        $date = $this->selectedDate($request);
        $from = $date->copy()->startOfWeek();
        $until = $date->copy()->endOfWeek();

        return $this->agendaView(
            $this->withSlaAlerts($scheduleService->forRange($from, $until)),
            'Agenda semanal',
            'Vista operativa de cirugias, recursos y alertas de la semana.',
            'week',
            $date,
        );
    }

    public function conflicts(Request $request, SurgeryScheduleService $scheduleService): View
    {
        $date = $this->selectedDate($request);
        $from = $date->copy()->startOfDay();
        $until = $from->copy()->addDays(6)->endOfDay();
        $schedule = $scheduleService->forRange($from, $until);

        return view('schedule.conflicts', [
            'conflicts' => $schedule['conflicts'],
            'summary' => $schedule['summary'],
            'from' => $from,
            'until' => $until,
            'selectedDate' => $date,
        ]);
    }

    /**
     * @param  array{
     *     cases: Collection<int, SurgeryCase>,
     *     contexts: Collection<int, array<string, mixed>>,
     *     conflicts: Collection<int, array<string, mixed>>,
     *     summary: array{total_cases: int, critical_conflicts: int, high_conflicts: int, missing_instrumentist: int, incomplete_reservations: int}
     * }  $schedule
     */
    private function agendaView(
        array $schedule,
        string $title,
        string $subtitle,
        string $mode,
        Carbon $selectedDate,
    ): View {
        return view('schedule.index', [
            'cases' => $schedule['cases'],
            'contexts' => $schedule['contexts'],
            'conflicts' => $schedule['conflicts'],
            'summary' => $schedule['summary'],
            'title' => $title,
            'subtitle' => $subtitle,
            'mode' => $mode,
            'selectedDate' => $selectedDate,
            'slaAlertsByCase' => $schedule['sla_alerts_by_case'],
        ]);
    }

    /** @param array<string, mixed> $schedule */
    private function withSlaAlerts(array $schedule): array
    {
        $caseIds = $schedule['cases']->modelKeys();
        $schedule['sla_alerts_by_case'] = empty($caseIds)
            ? collect()
            : OperationalAlert::query()
                ->where('alertable_type', SurgeryCase::class)
                ->whereIn('alertable_id', $caseIds)
                ->whereIn('status', array_merge(OperationalAlert::OPEN_STATUSES, ['expired']))
                ->latest('detected_at')
                ->get()
                ->groupBy('alertable_id');

        return $schedule;
    }

    private function selectedDate(Request $request): Carbon
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return isset($validated['date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['date'])
            : today();
    }
}
