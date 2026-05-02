<?php

namespace App\Http\Controllers;

use App\Models\FeeInvoice;
use App\Models\Payment;
use App\Models\TeachingAssignment;
use App\Support\Audit\Auditor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller
{
    public function index(Request $request, string $resource): Response
    {
        $definition = $this->definition($resource);
        $this->authorizeResource($request, $definition, 'view');

        /** @var class-string<Model> $model */
        $model = $definition['model'];
        $query = $this->scopeQuery($request, $resource, $model::query());

        if ($search = trim((string) $request->query('search', ''))) {
            $searchColumns = $definition['search'] ?? [];
            $query->where(function (Builder $builder) use ($searchColumns, $search): void {
                foreach ($searchColumns as $column) {
                    $builder->orWhere($column, 'ilike', '%'.$search.'%');
                }
            });
        }

        return Inertia::render('Resource/Index', [
            'resourceKey' => $resource,
            'definition' => $this->publicDefinition($definition),
            'records' => $query
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Model $record): array => $this->recordPayload($record, $definition)),
            'lookups' => $this->lookups($definition),
            'filters' => ['search' => $search],
            'can' => [
                'create' => $request->user()->hasPermission($definition['permission'].'.create'),
                'update' => $request->user()->hasPermission($definition['permission'].'.update'),
                'delete' => $request->user()->hasPermission($definition['permission'].'.delete'),
            ],
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $definition = $this->definition($resource);
        $this->authorizeResource($request, $definition, 'create');

        $data = $this->applyActorDefaults($resource, $this->validated($request, $definition, 'store'), $request);
        $this->enforceRecordScope($request, $resource, $data);

        DB::transaction(function () use ($request, $resource, $definition, $data): void {
            /** @var class-string<Model> $model */
            $model = $definition['model'];
            $syncData = $this->pullSyncData($definition, $data);

            $record = new $model();
            $record->fill($this->modelData($definition, $data));
            $record->save();

            $this->syncRelationships($record, $definition, $syncData);
            $this->afterPersist($resource, $record);

            if ($definition['audit'] ?? false) {
                Auditor::record($resource.'.created', $record, null, $this->recordSnapshot($record), $request);
            }
        });

        return back()->with('success', $definition['label'].' đã được tạo.');
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        $definition = $this->definition($resource);
        $this->authorizeResource($request, $definition, 'update');

        /** @var class-string<Model> $model */
        $model = $definition['model'];
        $record = $this->scopeQuery($request, $resource, $model::query())->findOrFail($id);

        $data = $this->validated($request, $definition, 'update');
        $this->enforceRecordScope($request, $resource, $data);

        DB::transaction(function () use ($request, $resource, $definition, $record, $data): void {
            $before = $this->recordSnapshot($record);
            $syncData = $this->pullSyncData($definition, $data);

            $record->fill($this->modelData($definition, $data));
            $record->save();

            $this->syncRelationships($record, $definition, $syncData);
            $this->afterPersist($resource, $record);

            $after = $this->recordSnapshot($record->fresh());

            if ($definition['revision'] ?? false) {
                $revisionModel = $definition['revision']['model'];
                $revisionModel::create([
                    $definition['revision']['foreign_key'] => $record->id,
                    'before_values' => $before,
                    'after_values' => $after,
                    'changed_by' => $request->user()->id,
                    'reason' => $request->input('revision_reason', 'Cập nhật từ giao diện quản trị'),
                ]);
            }

            if ($definition['audit'] ?? false) {
                Auditor::record($resource.'.updated', $record, $before, $after, $request);
            }
        });

        return back()->with('success', $definition['label'].' đã được cập nhật.');
    }

    public function destroy(Request $request, string $resource, int $id): RedirectResponse
    {
        $definition = $this->definition($resource);
        $this->authorizeResource($request, $definition, 'delete');

        /** @var class-string<Model> $model */
        $model = $definition['model'];
        $record = $this->scopeQuery($request, $resource, $model::query())->findOrFail($id);
        $before = $this->recordSnapshot($record);

        DB::transaction(function () use ($request, $resource, $definition, $record, $before): void {
            $record->delete();

            if ($definition['audit'] ?? false) {
                Auditor::record($resource.'.deleted', $record, $before, null, $request);
            }
        });

        return back()->with('success', $definition['label'].' đã được xóa.');
    }

    private function definition(string $resource): array
    {
        $definition = config('school.resources.'.$resource);

        abort_unless($definition, 404);

        return $definition;
    }

    private function authorizeResource(Request $request, array $definition, string $action): void
    {
        abort_unless($request->user()?->hasPermission($definition['permission'].'.'.$action), 403);
    }

    private function validated(Request $request, array $definition, string $action): array
    {
        $rules = $definition['validation'][$action] ?? [];
        $data = $request->validate($rules);

        foreach ($definition['fields'] as $field) {
            if (($field['type'] ?? null) === 'checkbox') {
                $data[$field['name']] = $request->boolean($field['name']);
            }

            if (($field['skipEmptyOnUpdate'] ?? false) && $action === 'update' && blank($data[$field['name']] ?? null)) {
                unset($data[$field['name']]);
            }
        }

        return collect($data)
            ->map(fn ($value) => $value === '' ? null : $value)
            ->all();
    }

    private function modelData(array $definition, array $data): array
    {
        return Arr::except($data, array_keys($definition['sync'] ?? []));
    }

    private function applyActorDefaults(string $resource, array $data, Request $request): array
    {
        $actorFields = [
            'score_entries' => 'entered_by',
            'disciplinary_cases' => 'created_by',
            'school_events' => 'created_by',
            'payments' => 'collected_by',
            'announcements' => 'created_by',
        ];

        if (isset($actorFields[$resource])) {
            $data[$actorFields[$resource]] = $request->user()->id;
        }

        return $data;
    }

    private function pullSyncData(array $definition, array &$data): array
    {
        $syncData = [];

        foreach (($definition['sync'] ?? []) as $field => $relation) {
            $syncData[$relation] = $data[$field] ?? [];
            unset($data[$field]);
        }

        return $syncData;
    }

    private function syncRelationships(Model $record, array $definition, array $syncData): void
    {
        foreach ($syncData as $relation => $ids) {
            $record->{$relation}()->sync($ids);
        }
    }

    private function publicDefinition(array $definition): array
    {
        $copy = Arr::except($definition, ['model', 'validation']);

        foreach ($copy['fields'] as &$field) {
            if (isset($field['lookup'])) {
                $field['lookup'] = true;
            }
        }

        if (isset($copy['revision'])) {
            $copy['revision'] = true;
        }

        return $copy;
    }

    private function lookups(array $definition): array
    {
        $lookups = [];

        foreach ($definition['fields'] as $field) {
            if (! isset($field['lookup'])) {
                continue;
            }

            $lookup = $field['lookup'];
            $model = $lookup['model'];
            $valueColumn = $lookup['value'];
            $labelColumns = (array) $lookup['label'];

            $lookups[$field['name']] = $model::query()
                ->orderBy($labelColumns[0])
                ->limit(250)
                ->get()
                ->map(fn (Model $item): array => [
                    'value' => $item->{$valueColumn},
                    'label' => collect($labelColumns)
                        ->map(fn (string $column): string => (string) $item->{$column})
                        ->filter()
                        ->implode(' - '),
                ])
                ->values();
        }

        return $lookups;
    }

    private function recordPayload(Model $record, array $definition): array
    {
        $payload = $record->toArray();

        foreach (($definition['sync'] ?? []) as $field => $relation) {
            $payload[$field] = $record->{$relation}()
                ->pluck($record->{$relation}()->getRelated()->getQualifiedKeyName())
                ->values()
                ->all();
        }

        return $payload;
    }

    private function recordSnapshot(?Model $record): ?array
    {
        return $record?->fresh()?->getAttributes() ?? $record?->getAttributes();
    }

    private function scopeQuery(Request $request, string $resource, Builder $query): Builder
    {
        $user = $request->user();

        if ($resource === 'score_entries' && $user?->hasRole('giao_vien_bo_mon') && ! $user->hasRole('admin') && ! $user->hasRole('bgh')) {
            $staffId = $user->staff?->id;
            $assignments = TeachingAssignment::query()
                ->where('teacher_id', $staffId)
                ->get(['class_id', 'subject_id']);

            $subjectIds = $assignments->pluck('subject_id')->unique()->values();
            $classIds = $assignments->pluck('class_id')->unique()->values();

            $query->whereIn('subject_id', $subjectIds)
                ->whereExists(function ($subQuery) use ($classIds): void {
                    $subQuery->selectRaw('1')
                        ->from('class_enrollments')
                        ->whereColumn('class_enrollments.student_id', 'score_entries.student_id')
                        ->whereIn('class_enrollments.class_id', $classIds);
                });
        }

        return $query;
    }

    private function enforceRecordScope(Request $request, string $resource, array $data): void
    {
        $user = $request->user();

        if ($resource !== 'score_entries' || ! $user?->hasRole('giao_vien_bo_mon') || $user->hasRole('admin') || $user->hasRole('bgh')) {
            return;
        }

        $allowed = TeachingAssignment::query()
            ->where('teacher_id', $user->staff?->id)
            ->where('subject_id', $data['subject_id'] ?? null)
            ->whereExists(function ($query) use ($data): void {
                $query->selectRaw('1')
                    ->from('class_enrollments')
                    ->whereColumn('class_enrollments.class_id', 'teaching_assignments.class_id')
                    ->where('class_enrollments.student_id', $data['student_id'] ?? null);
            })
            ->exists();

        abort_unless($allowed, 403, 'Giáo viên bộ môn chỉ được nhập điểm lớp-môn được phân công.');
    }

    private function afterPersist(string $resource, Model $record): void
    {
        if ($resource === 'payments' && $record instanceof Payment) {
            $this->refreshInvoice($record->fee_invoice_id);
        }

        if ($resource === 'fee_invoice_items') {
            $this->refreshInvoice($record->fee_invoice_id);
        }
    }

    private function refreshInvoice(?int $invoiceId): void
    {
        if (! $invoiceId) {
            return;
        }

        $invoice = FeeInvoice::find($invoiceId);

        if (! $invoice) {
            return;
        }

        $itemTotal = DB::table('fee_invoice_items')->where('fee_invoice_id', $invoice->id)->sum('amount');
        $paidTotal = DB::table('payments')->where('fee_invoice_id', $invoice->id)->sum('amount');

        $invoice->forceFill([
            'total_amount' => $itemTotal > 0 ? $itemTotal : $invoice->total_amount,
            'paid_amount' => $paidTotal,
            'status' => $paidTotal <= 0 ? 'unpaid' : ($paidTotal < (float) $invoice->total_amount ? 'partial' : 'paid'),
        ])->save();
    }
}
