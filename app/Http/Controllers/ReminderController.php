<?php

namespace App\Http\Controllers;

use App\Actions\SaveReminder;
use App\Enums\NotificationEventType;
use App\Enums\OccurrenceStatus;
use App\Http\Requests\ReminderRequest;
use App\Models\NotificationRule;
use App\Services\Notifications\NotificationScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReminderController extends Controller
{
    public function index(Request $request): Response
    {
        $tz = $this->timezone($request);

        $reminders = NotificationRule::query()
            ->where('family_id', $request->user()->family_id)
            ->where('event_type', NotificationEventType::Custom)
            ->with(['recipients:id,name', 'occurrences.targets'])
            ->orderByDesc('event_at')
            ->get()
            ->map(fn (NotificationRule $rule) => [
                'id' => $rule->id,
                'title' => $rule->title,
                'body' => $rule->body,
                'event_at' => $rule->event_at?->setTimezone($tz)->format('Y-m-d\TH:i'),
                'is_active' => $rule->is_active,
                'requires_ack' => $rule->requires_ack,
                'repeat_every_minutes' => $rule->repeat_every_minutes,
                'max_repeats' => $rule->max_repeats,
                'offsets_minutes' => $rule->offsets_minutes,
                'recipients' => $rule->recipients->pluck('name'),
                'state' => $this->state($rule),
            ]);

        return Inertia::render('Recordatorios/Index', ['reminders' => $reminders]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Recordatorios/Form', $this->formProps($request));
    }

    public function store(ReminderRequest $request, SaveReminder $save): RedirectResponse
    {
        $save->handle($request->user(), $request->ruleData($this->timezone($request)), $request->recipientIds());

        return redirect()->route('reminders.index')->with('status', trans('ui.reminders.saved'));
    }

    public function edit(Request $request, NotificationRule $rule): Response
    {
        $this->authorizeRule($request, $rule);
        $tz = $this->timezone($request);

        return Inertia::render('Recordatorios/Form', $this->formProps($request) + [
            'reminder' => [
                'id' => $rule->id,
                'title' => $rule->title,
                'body' => $rule->body,
                'event_at' => $rule->event_at?->setTimezone($tz)->format('Y-m-d\TH:i'),
                'baby_id' => $rule->baby_id,
                'offsets_minutes' => $rule->offsets_minutes,
                'repeat_every_minutes' => $rule->repeat_every_minutes,
                'max_repeats' => $rule->max_repeats,
                'requires_ack' => $rule->requires_ack,
                'ack_scope' => $rule->ack_scope->value,
                'snooze_options' => $rule->snooze_options,
                'quiet_start' => $rule->quiet_start ? substr($rule->quiet_start, 0, 5) : null,
                'quiet_end' => $rule->quiet_end ? substr($rule->quiet_end, 0, 5) : null,
                'respect_quiet_hours' => $rule->respect_quiet_hours,
                'recipients' => $rule->recipients()->pluck('caretakers.id'),
            ],
        ]);
    }

    public function update(ReminderRequest $request, NotificationRule $rule, SaveReminder $save): RedirectResponse
    {
        $this->authorizeRule($request, $rule);

        $save->handle($request->user(), $request->ruleData($this->timezone($request)), $request->recipientIds(), $rule);

        return redirect()->route('reminders.index')->with('status', trans('ui.reminders.saved'));
    }

    public function toggle(Request $request, NotificationRule $rule, NotificationScheduler $scheduler): RedirectResponse
    {
        $this->authorizeRule($request, $rule);

        $active = ! $rule->is_active;
        $rule->update(['is_active' => $active]);

        if (! $active) {
            $scheduler->cancelAll($rule);
        } elseif ($rule->event_at?->isFuture()) {
            $scheduler->schedule($rule, $rule->event_at);
        }

        return back();
    }

    public function destroy(Request $request, NotificationRule $rule, NotificationScheduler $scheduler): RedirectResponse
    {
        $this->authorizeRule($request, $rule);

        $scheduler->cancelAll($rule);
        $rule->delete();

        return redirect()->route('reminders.index')->with('status', trans('ui.reminders.deleted'));
    }

    private function authorizeRule(Request $request, NotificationRule $rule): void
    {
        abort_unless(
            $rule->family_id === $request->user()->family_id && $rule->event_type === NotificationEventType::Custom,
            404,
        );
    }

    private function timezone(Request $request): string
    {
        return $request->user()->family->settings->timezone ?? config('app.timezone');
    }

    private function formProps(Request $request): array
    {
        $family = $request->user()->family;

        return [
            'defaults' => [
                'event_at' => CarbonImmutable::now($this->timezone($request))->addHour()->startOfHour()->format('Y-m-d\TH:i'),
                'recipient' => $request->user()->id,
            ],
            'caretakers' => $family->caretakers()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'badge_color']),
            'babies' => $family->babies()->orderBy('birth_date')->get(['id', 'first_name']),
            'options' => ['offsets' => ReminderRequest::OFFSETS, 'snooze' => ReminderRequest::SNOOZE],
        ];
    }

    /** upcoming | acknowledged | done | paused */
    private function state(NotificationRule $rule): string
    {
        if (! $rule->is_active) {
            return 'paused';
        }

        $statuses = $rule->occurrences->pluck('status');

        return match (true) {
            $statuses->contains(OccurrenceStatus::Active) => 'upcoming',
            $statuses->contains(OccurrenceStatus::Acknowledged) => 'acknowledged',
            default => 'done',
        };
    }
}
