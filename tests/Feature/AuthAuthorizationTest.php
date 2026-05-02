<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\RefreshToken;
use App\Models\Role;
use App\Models\ScoreEntry;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_user_can_login_with_email_or_username(): void
    {
        $this->seed();

        $admin = User::where('username', 'admin')->firstOrFail();

        $this->post('/login', [
            'login' => 'admin@vvk.local',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);

        auth()->logout();

        $this->post('/login', [
            'login' => 'admin',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_failed_and_inactive_logins_are_rejected_and_audited(): void
    {
        $this->seed();

        $admin = User::where('username', 'admin')->firstOrFail();

        $this->post('/login', [
            'login' => 'admin',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_failed',
            'subject_id' => $admin->id,
        ]);

        AuditLog::query()->delete();
        $admin->forceFill(['status' => 'inactive'])->save();

        $this->post('/login', [
            'login' => 'admin',
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_failed',
            'subject_id' => $admin->id,
        ]);
    }

    public function test_api_login_profile_refresh_rotation_and_logout(): void
    {
        $this->seed();

        $login = $this->postJson('/api/auth/login', [
            'login' => 'admin',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
                'refresh_token',
                'user' => ['id', 'username', 'email', 'roles', 'permissions', 'context'],
            ]);

        $accessToken = $login->json('access_token');
        $refreshToken = $login->json('refresh_token');

        $this->getJson('/api/auth/profile', [
            'Authorization' => 'Bearer '.$accessToken,
        ])->assertOk()
            ->assertJsonPath('user.username', 'admin');

        $refresh = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $refreshToken,
        ])->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token']);

        $this->postJson('/api/auth/refresh', [
            'refresh_token' => $refreshToken,
        ])->assertUnauthorized();

        $this->postJson('/api/auth/logout', [
            'refresh_token' => $refresh->json('refresh_token'),
        ], [
            'Authorization' => 'Bearer '.$refresh->json('access_token'),
        ])->assertOk();

        $this->assertSame(2, RefreshToken::whereNotNull('revoked_at')->count());
    }

    public function test_password_change_hashes_password_and_creates_audit_log(): void
    {
        $this->seed();

        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->put('/profile/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasNoErrors();

        $admin->refresh();

        $this->assertTrue(Hash::check('new-password', $admin->password));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.password_changed',
            'subject_id' => $admin->id,
        ]);
    }

    public function test_account_and_permission_changes_include_relationships_in_audit_snapshot(): void
    {
        $this->seed();

        $admin = User::where('username', 'admin')->firstOrFail();
        $role = Role::where('slug', 'hoc_sinh')->firstOrFail();

        $this->actingAs($admin)
            ->post('/manage/users', [
                'name' => 'Demo Audit User',
                'username' => 'demoaudit',
                'email' => 'demoaudit@vvk.local',
                'password' => 'password',
                'status' => 'active',
                'role_ids' => [$role->id],
            ])
            ->assertSessionHasNoErrors();

        $log = AuditLog::where('action', 'users.created')->latest('id')->firstOrFail();

        $this->assertSame([$role->id], $log->after_values['role_ids']);
        $this->assertSame('[redacted]', $log->after_values['password']);
    }

    public function test_role_module_permissions_and_context_scope_are_enforced(): void
    {
        $this->seed();

        $accountant = User::where('username', 'ketoan')->firstOrFail();
        $organizer = User::where('username', 'doantruong')->firstOrFail();
        $homeroomTeacher = User::where('username', 'gvcn')->firstOrFail();
        $outsideStudent = Student::where('student_code', 'DEMO0002')->firstOrFail();
        $outsideEnrollment = $outsideStudent->enrollments()->firstOrFail();
        $score = ScoreEntry::firstOrFail();

        $this->actingAs($accountant)
            ->get('/manage/student_fees')
            ->assertOk();

        $this->actingAs($accountant)
            ->get('/manage/student_scores')
            ->assertForbidden();

        $this->actingAs($organizer)
            ->get('/manage/events')
            ->assertOk();

        $this->actingAs($organizer)
            ->get('/manage/student_fees')
            ->assertForbidden();

        $this->actingAs($homeroomTeacher)
            ->post('/manage/conduct_scores', [
                'school_year_id' => $score->school_year_id,
                'semester_id' => $score->semester_id,
                'class_id' => $outsideEnrollment->class_id,
                'student_id' => $outsideStudent->id,
                'score' => 90,
                'rating' => 'Tot',
                'status' => 'approved',
                'note' => 'Ngoai lop chu nhiem.',
            ])
            ->assertForbidden();
    }
}
