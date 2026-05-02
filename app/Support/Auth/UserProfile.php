<?php

namespace App\Support\Auth;

use App\Models\SchoolClass;
use App\Models\TeachingAssignment;
use App\Models\User;

class UserProfile
{
    public function forUser(User $user): array
    {
        $user->loadMissing('roles.permissions', 'staff', 'student', 'guardian.students');

        $staffId = $user->staff?->id;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'status' => $user->status,
            'last_login_at' => $user->last_login_at?->toISOString(),
            'roles' => $user->roles
                ->map(fn ($role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ])
                ->values(),
            'permissions' => $user->permissionKeys(),
            'context' => [
                'staff' => $user->staff ? [
                    'id' => $user->staff->id,
                    'teacher_code' => $user->staff->teacher_code,
                    'full_name' => $user->staff->full_name,
                    'position' => $user->staff->position,
                    'department' => $user->staff->department,
                ] : null,
                'student' => $user->student ? [
                    'id' => $user->student->id,
                    'student_code' => $user->student->student_code,
                    'full_name' => $user->student->full_name,
                    'status' => $user->student->status,
                ] : null,
                'guardian' => $user->guardian ? [
                    'id' => $user->guardian->id,
                    'full_name' => $user->guardian->full_name,
                    'students' => $user->guardian->students
                        ->map(fn ($student): array => [
                            'id' => $student->id,
                            'student_code' => $student->student_code,
                            'full_name' => $student->full_name,
                        ])
                        ->values(),
                ] : null,
                'homeroom_class_ids' => $staffId ? SchoolClass::query()
                    ->where('homeroom_teacher_id', $staffId)
                    ->pluck('id')
                    ->values() : [],
                'teaching_assignments' => $staffId ? TeachingAssignment::query()
                    ->where('teacher_id', $staffId)
                    ->where('status', 'active')
                    ->get(['class_id', 'subject_id', 'semester_id'])
                    ->map(fn (TeachingAssignment $assignment): array => [
                        'class_id' => $assignment->class_id,
                        'subject_id' => $assignment->subject_id,
                        'semester_id' => $assignment->semester_id,
                    ])
                    ->values() : [],
            ],
        ];
    }
}
