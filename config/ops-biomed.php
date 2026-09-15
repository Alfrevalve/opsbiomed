<?php

return [
    'min_surgeries' => (int) env('OPS_MIN_SURGERIES', 3),
    'target_surgeries' => (int) env('OPS_TARGET_SURGERIES', 5),
    'ysan_lead_time_hours' => (int) env('OPS_YSAN_LEAD_TIME_HOURS', 48),
    'critical_roles' => ['Administrador', 'Gerencia', 'Direccion Tecnica', 'Cobranza'],
    'sensitive_patient_fields' => ['document_number', 'phone', 'notes'],
];
