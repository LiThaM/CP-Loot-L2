<?php

namespace Tests\Feature;

use App\Contexts\Identity\Domain\Models\Role;
use App\Contexts\Identity\Domain\Models\User;
use App\Mail\NewTicketReplyAdminNotification;
use App\Mail\TicketReplyAuthorNotification;
use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TicketReplyEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $admin;

    private User $leader;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.support.mail_to' => 'support@test.local']);

        Mail::fake();

        $memberRole = Role::firstOrCreate(['name' => 'cp_member'], ['display_name' => 'Member']);
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $leaderRole = Role::firstOrCreate(['name' => 'cp_leader'], ['display_name' => 'CP Leader']);

        $this->author = User::forceCreate([
            'name' => 'Author',
            'email' => 'author@t.l',
            'password' => bcrypt('x'),
            'role_id' => $memberRole->id,
            'membership_status' => 'approved',
        ]);

        $this->admin = User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@t.l',
            'password' => bcrypt('x'),
            'role_id' => $adminRole->id,
            'membership_status' => 'approved',
        ]);

        $this->leader = User::forceCreate([
            'name' => 'Leader',
            'email' => 'leader@t.l',
            'password' => bcrypt('x'),
            'role_id' => $leaderRole->id,
            'membership_status' => 'approved',
        ]);
    }

    private function makeTicket(array $overrides = []): SupportTicket
    {
        return SupportTicket::create(array_merge([
            'user_id' => $this->author->id,
            'subject' => 'Test subject',
            'message' => 'Test message',
            'name' => $this->author->name,
            'email' => $this->author->email,
            'status' => 'open',
            'type' => 'bug',
            'ticket_number' => SupportTicket::generateTicketNumber(),
        ], $overrides));
    }

    public function test_author_replying_notifies_support_only(): void
    {
        $ticket = $this->makeTicket();

        $this->actingAs($this->author)
            ->post(route('tickets.reply', $ticket), ['message' => 'Following up on my own ticket'])
            ->assertRedirect();

        Mail::assertSent(NewTicketReplyAdminNotification::class, function ($mail) {
            return $mail->hasTo('support@test.local');
        });
        Mail::assertNotSent(TicketReplyAuthorNotification::class);
    }

    public function test_admin_replying_notifies_author_only(): void
    {
        $ticket = $this->makeTicket();

        $this->actingAs($this->admin)
            ->post(route('tickets.reply', $ticket), ['message' => 'Admin response'])
            ->assertRedirect();

        Mail::assertSent(TicketReplyAuthorNotification::class, function ($mail) {
            return $mail->hasTo($this->author->email);
        });
        Mail::assertNotSent(NewTicketReplyAdminNotification::class);
    }

    public function test_assigned_leader_replying_notifies_author_and_support(): void
    {
        $ticket = $this->makeTicket([
            'type' => 'data_discrepancy',
            'assigned_to_user_id' => $this->leader->id,
        ]);

        $this->actingAs($this->leader)
            ->post(route('tickets.reply', $ticket), ['message' => 'Leader response'])
            ->assertRedirect();

        Mail::assertSent(TicketReplyAuthorNotification::class, function ($mail) {
            return $mail->hasTo($this->author->email);
        });
        Mail::assertSent(NewTicketReplyAdminNotification::class, function ($mail) {
            return $mail->hasTo('support@test.local');
        });
    }
}
