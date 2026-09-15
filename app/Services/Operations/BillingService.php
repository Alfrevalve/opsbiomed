<?php

namespace App\Services\Operations;

use App\Models\BillingRecord;
use App\Models\SurgeryCase;
use App\Services\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BillingService
{
    private const NON_DEBT_STATUSES = [
        'pendiente_valorizacion',
        'no_facturable',
        'costo_cero_pendiente_aprobacion',
        'costo_cero_aprobado',
    ];

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function refreshOverdueStatuses(): int
    {
        $records = BillingRecord::query()
            ->whereNotIn('invoice_status', self::NON_DEBT_STATUSES)
            ->whereColumn('amount_paid', '<', 'amount')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->where('payment_status', '!=', 'vencido')
            ->get();

        foreach ($records as $record) {
            $before = $record->only(['payment_status', 'debt_days']);
            $record->update([
                'payment_status' => 'vencido',
                'debt_days' => today()->diffInDays($record->due_date),
            ]);
            $this->auditLogger->record('payment.updated', $record, $before, $record->only(['payment_status', 'debt_days']));
        }

        return $records->count();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SurgeryCase $case, array $data, int $userId): BillingRecord
    {
        return DB::transaction(function () use ($case, $data, $userId): BillingRecord {
            $billing = BillingRecord::query()->lockForUpdate()->firstOrCreate(
                ['case_id' => $case->id],
                [
                    'amount' => (float) ($case->valuation?->total ?? 0),
                    'invoice_status' => 'pendiente_valorizacion',
                    'payment_status' => 'pendiente',
                ],
            );
            $amount = (float) $billing->amount;
            $invoiceStatus = (string) $data['invoice_status'];
            $amountPaid = (float) $data['amount_paid'];

            if ($invoiceStatus === 'facturado' && blank($data['invoice_number'] ?? null)) {
                throw new DomainException('No se puede marcar como facturado sin numero de factura.');
            }

            if ($amountPaid > $amount) {
                throw new DomainException('El monto pagado no puede superar el total valorizado.');
            }

            $balance = round(max(0, $amount - $amountPaid), 2);
            if ($data['payment_status'] === 'pagado' && $balance > 0) {
                throw new DomainException('No se puede marcar como pagado mientras exista saldo pendiente.');
            }

            if ($invoiceStatus === 'costo_cero_aprobado') {
                $amount = 0;
                $amountPaid = 0;
                $balance = 0;
                $paymentStatus = 'pagado';
            } elseif (in_array($invoiceStatus, ['no_facturable', 'costo_cero_pendiente_aprobacion'], true)) {
                $paymentStatus = $invoiceStatus === 'no_facturable' ? 'pagado' : 'pendiente';
            } elseif ($balance === 0) {
                $paymentStatus = 'pagado';
            } elseif (($data['due_date'] ?? $billing->due_date) && now()->startOfDay()->isAfter(Carbon::parse($data['due_date'] ?? $billing->due_date))) {
                $paymentStatus = 'vencido';
            } else {
                $paymentStatus = $data['payment_status'];
            }

            $before = $billing->only([
                'invoice_status',
                'purchase_order',
                'invoice_number',
                'invoice_date',
                'due_date',
                'payment_status',
                'amount_paid',
                'observations',
            ]);
            $billing->update([
                'amount' => $amount,
                'amount_paid' => $amountPaid,
                'invoice_status' => $invoiceStatus,
                'purchase_order' => $data['purchase_order'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'invoice_date' => $data['invoice_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'payment_status' => $paymentStatus,
                'debt_days' => $paymentStatus === 'vencido' && $billing->due_date
                    ? today()->diffInDays($billing->due_date)
                    : 0,
                'no_billing_reason' => in_array($invoiceStatus, ['no_facturable', 'costo_cero_aprobado'], true)
                    ? ($data['observations'] ?? $billing->no_billing_reason)
                    : null,
                'observations' => $data['observations'] ?? null,
                'updated_by' => $userId,
            ]);

            $this->auditLogger->record('billing.updated', $billing, $before, $billing->only(array_keys($before)));
            if ((float) $before['amount_paid'] !== (float) $billing->amount_paid || $before['payment_status'] !== $billing->payment_status) {
                $this->auditLogger->record('payment.updated', $billing, [
                    'amount_paid' => $before['amount_paid'],
                    'payment_status' => $before['payment_status'],
                ], [
                    'amount_paid' => $billing->amount_paid,
                    'payment_status' => $billing->payment_status,
                    'balance' => $billing->balance,
                ]);
            }

            return $billing->refresh();
        });
    }

    /**
     * @param  Collection<int, BillingRecord>  $records
     */
    public function pendingAmount(Collection $records): float
    {
        return round((float) $records
            ->reject(fn (BillingRecord $record): bool => in_array($record->invoice_status, self::NON_DEBT_STATUSES, true))
            ->sum(fn (BillingRecord $record): float => $record->balance), 2);
    }
}
