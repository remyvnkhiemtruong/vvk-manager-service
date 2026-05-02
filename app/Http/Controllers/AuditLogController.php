<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('audit.view'), 403);

        return Inertia::render('Audit/Index', [
            'logs' => AuditLog::with('actor:id,name,email')
                ->latest()
                ->paginate(20)
                ->through(fn (AuditLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'actor' => $log->actor?->name ?? 'System',
                    'subject_type' => class_basename((string) $log->subject_type),
                    'subject_id' => $log->subject_id,
                    'before_values' => $log->before_values,
                    'after_values' => $log->after_values,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at?->format('d/m/Y H:i:s'),
                ]),
        ]);
    }
}

