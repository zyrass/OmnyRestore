<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ContactEmailSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_routes_notifications_to_contact_email_when_defined(): void
    {
        $user = User::factory()->create([
            'email' => 'collab@omny.internal',
            'contact_email' => 'real-security-address@gmail.com',
        ]);

        $this->assertEquals('real-security-address@gmail.com', $user->routeNotificationForMail());
    }

    /** @test */
    public function it_routes_notifications_to_login_email_when_contact_email_is_null(): void
    {
        $user = User::factory()->create([
            'email' => 'collab@omny.internal',
            'contact_email' => null,
        ]);

        $this->assertEquals('collab@omny.internal', $user->routeNotificationForMail());
    }

    /** @test */
    public function reset_password_notification_is_sent_to_contact_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'collab@omny.internal',
            'contact_email' => 'real-security-address@gmail.com',
        ]);

        Volt::test('pages.auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routeNotificationForMail() === 'real-security-address@gmail.com';
            }
        );
    }

    /** @test */
    public function admin_can_create_member_with_contact_email(): void
    {
        $admin = User::factory()->create([
            'role' => 'super-admin',
        ]);

        $this->actingAs($admin);

        Volt::test('pages.admin.team.roles')
            ->set('newMemberName', 'Secure Operator')
            ->set('newMemberEmail', 'op1@omny.internal')
            ->set('newMemberContactEmail', 'op1.real@gmail.com')
            ->set('newMemberRole', 'operator')
            ->set('newMemberPassword', 'P@ssw0rdProSecure123!')
            ->call('addMember')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'Secure Operator',
            'email' => 'op1@omny.internal',
            'contact_email' => 'op1.real@gmail.com',
            'role' => 'operator',
        ]);
    }

    /** @test */
    public function admin_can_edit_member_contact_email(): void
    {
        $admin = User::factory()->create([
            'role' => 'super-admin',
        ]);

        $member = User::factory()->create([
            'role' => 'operator',
            'email' => 'op2@omny.internal',
            'contact_email' => null,
        ]);

        $this->actingAs($admin);

        Volt::test('pages.admin.team.roles')
            ->call('startEditRole', $member->id)
            ->assertSet('editingContactEmail', '')
            ->set('editingContactEmail', 'op2.updated@gmail.com')
            ->set('editingRole', 'operator')
            ->call('saveRole')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'contact_email' => 'op2.updated@gmail.com',
        ]);
    }
}
