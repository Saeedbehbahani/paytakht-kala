<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_access_managerial_reports(): void
    {
        $employee = User::factory()->create(['role' => 'EMPLOYEE', 'active' => true]);

        $this->actingAs($employee)->get('/reports')->assertForbidden();
    }

    public function test_inactive_user_is_rejected_from_authenticated_routes(): void
    {
        $inactive = User::factory()->create(['role' => 'EMPLOYEE', 'active' => false]);

        $this->actingAs($inactive)->get('/dashboard')->assertRedirect('/login');
    }

    public function test_employee_dashboard_does_not_render_managerial_dashboard(): void
    {
        $employee = User::factory()->create(['role' => 'EMPLOYEE', 'active' => true]);

        $this->actingAs($employee)
            ->get('/dashboard')
            ->assertOk()
            ->assertViewIs('dashboard.employee');
    }
}
