<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'roles' => $user->roles()->pluck('name')->values(),
                    'permissions' => $user->permissionKeys(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'school' => config('school.school'),
            'navigation' => fn () => $this->navigation($request),
        ];
    }

    private function navigation(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [];
        }

        $groups = [
            [
                'label' => 'Tổng quan',
                'items' => array_values(array_filter([
                    $user->hasPermission('dashboard.view') ? ['label' => 'Dashboard', 'href' => route('dashboard'), 'icon' => 'LayoutDashboard'] : null,
                    $user->hasPermission('portal.view') ? ['label' => 'Cổng PH/HS', 'href' => route('portal'), 'icon' => 'GraduationCap'] : null,
                    $user->hasPermission('reports.view') ? ['label' => 'Báo cáo', 'href' => route('reports'), 'icon' => 'BarChart3'] : null,
                    $user->hasPermission('audit.view') ? ['label' => 'Audit log', 'href' => route('audit.index'), 'icon' => 'ShieldCheck'] : null,
                ])),
            ],
        ];

        if ($user->hasPermission('activities.campaigns.view')) {
            $groups[] = [
                'label' => 'Phong trào và Đoàn',
                'items' => array_values(array_filter([
                    ['label' => 'Dashboard Đoàn/BTC', 'href' => route('campaigns.dashboard'), 'icon' => 'Trophy'],
                    ['label' => 'Phong trào', 'href' => route('campaigns.index'), 'icon' => 'CalendarDays'],
                    $user->hasPermission('activities.campaign_participants.view') ? ['label' => 'Duyệt đăng ký', 'href' => route('campaigns.registrations'), 'icon' => 'ClipboardCheck'] : null,
                ])),
            ];
        }

        if ($user->hasPermission('activities.events.view')) {
            $groups[] = [
                'label' => 'Hoi thi/Hoi thao',
                'items' => array_values(array_filter([
                    ['label' => 'Dashboard BTC', 'href' => route('events.dashboard'), 'icon' => 'Medal'],
                    ['label' => 'Danh sach su kien', 'href' => route('events.index'), 'icon' => 'CalendarRange'],
                    $user->hasPermission('activities.event_registrations.view') ? ['label' => 'Duyet dang ky', 'href' => route('events.registrations'), 'icon' => 'ClipboardCheck'] : null,
                ])),
            ];
        }

        $moduleLabels = config('school.module_labels');
        $resources = collect(config('school.resources'))
            ->reject(fn (array $resource, string $key): bool => $key === 'events')
            ->filter(fn (array $resource): bool => $user->hasPermission($resource['permission'].'.view'))
            ->groupBy('module');

        foreach ($resources as $module => $items) {
            $groups[] = [
                'label' => $moduleLabels[$module] ?? $module,
                'items' => $items
                    ->map(fn (array $resource, string $key): array => [
                        'label' => $resource['label'],
                        'href' => $this->resourceHref($key),
                        'icon' => $this->iconFor($module),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return array_values(array_filter($groups, fn (array $group): bool => count($group['items']) > 0));
    }

    private function iconFor(string $module): string
    {
        return match ($module) {
            'identity' => 'KeyRound',
            'academic' => 'School',
            'assessment' => 'ClipboardList',
            'conduct' => 'BadgeCheck',
            'activities' => 'Trophy',
            'finance' => 'Receipt',
            'communication' => 'Megaphone',
            default => 'Circle',
        };
    }

    private function resourceHref(string $key): string
    {
        return match ($key) {
            'students' => route('academic.students.index'),
            'teachers' => route('academic.teachers.index'),
            'classes' => route('academic.classes.index'),
            'student_scores' => route('assessment.entry'),
            'score_columns' => route('assessment.score-columns'),
            'conduct_rules' => route('conduct.rules'),
            'conduct_records' => route('conduct.records'),
            'conduct_scores' => route('conduct.classes'),
            'conduct_rating_rules' => route('conduct.rules'),
            'events' => route('events.index'),
            default => route('resources.index', ['resource' => $key]),
        };
    }
}
