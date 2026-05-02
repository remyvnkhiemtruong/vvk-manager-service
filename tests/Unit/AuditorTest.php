<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\Auditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_auditor_redacts_password_values(): void
    {
        $user = User::create([
            'name' => 'Audit Demo',
            'email' => 'audit@example.test',
            'password' => 'password',
            'status' => 'active',
        ]);

        $this->actingAs($user);

        Auditor::record('users.updated', $user, ['password' => 'secret'], ['password' => 'new-secret']);

        $log = AuditLog::firstOrFail();

        $this->assertSame('[redacted]', $log->before_values['password']);
        $this->assertSame('[redacted]', $log->after_values['password']);
    }
}

