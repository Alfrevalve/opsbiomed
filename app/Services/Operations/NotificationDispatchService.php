<?php

namespace App\Services\Operations;

use App\Models\NotificationLog;
use App\Models\OperationalAlert;
use App\Services\Audit\AuditLogger;
use Throwable;

class NotificationDispatchService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function dispatchInternal(OperationalAlert $alert): ?NotificationLog
    {
        $recipient = $alert->responsibleUser;

        $payload = [
            'alert_id' => $alert->id,
            'alert_code' => $alert->alert_code,
            'module' => $alert->module,
            'priority' => $alert->priority,
            'entity_type' => $alert->alertable_type,
            'entity_id' => $alert->alertable_id,
        ];
        $subject = 'SLA operativo: '.$alert->module;

        if ($recipient === null) {
            $log = NotificationLog::create([
                'operational_alert_id' => $alert->id,
                'user_id' => null,
                'channel' => 'database',
                'status' => 'failed',
                'attempts' => 0,
                'subject' => $subject,
                'payload' => $payload,
                'error_message' => 'No existe un usuario activo para el rol responsable.',
            ]);
            $this->auditLogger->record('notification.failed', $log, [], [
                'alert_id' => $alert->id,
                'channel' => 'database',
                'reason' => 'responsible_user_not_found',
            ]);

            return null;
        }

        try {
            $log = NotificationLog::create([
                'operational_alert_id' => $alert->id,
                'user_id' => $recipient->id,
                'channel' => 'database',
                'status' => 'sent',
                'attempts' => 1,
                'subject' => $subject,
                'payload' => $payload,
                'sent_at' => now(),
            ]);

            $this->auditLogger->record('notification.sent', $log, [], [
                'alert_id' => $alert->id,
                'recipient_id' => $recipient->id,
                'channel' => 'database',
            ]);

            return $log;
        } catch (Throwable $exception) {
            $this->auditLogger->record('notification.failed', $alert, [], [
                'alert_id' => $alert->id,
                'recipient_id' => $recipient->id,
                'channel' => 'database',
                'exception_class' => $exception::class,
            ]);

            return null;
        }
    }

    /**
     * Record the prepared external channel without sending anything automatically.
     */
    public function prepareExternal(OperationalAlert $alert, string $channel): NotificationLog
    {
        $subject = 'SLA operativo: '.$alert->module;

        return NotificationLog::create([
            'operational_alert_id' => $alert->id,
            'user_id' => $alert->responsible_user_id,
            'channel' => $channel,
            'status' => 'pending',
            'attempts' => 0,
            'subject' => $subject,
            'payload' => [
                'alert_id' => $alert->id,
                'module' => $alert->module,
                'priority' => $alert->priority,
            ],
        ]);
    }
}
