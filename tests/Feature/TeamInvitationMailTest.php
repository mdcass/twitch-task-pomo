<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Support\Branding\ProductBrand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Tests\TestCase;

class TeamInvitationMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_invitation_mail_uses_the_branded_mail_shell(): void
    {
        $team = Team::factory()->streamer()->create();
        $invitation = $team->teamInvitations()->create([
            'email' => 'invitee@example.test',
            'role' => 'moderator',
        ]);

        $html = app(Markdown::class)->render('emails.team-invitation', [
            'invitation' => $invitation,
            'acceptUrl' => 'https://example.test/team-invitations/accept',
        ])->toHtml();

        $this->assertStringContainsString(ProductBrand::productName(), $html);
        $this->assertStringContainsString(ProductBrand::productTagline(), $html);
        $this->assertStringContainsString('You have been invited to join '.$team->name.' on '.ProductBrand::productName().'.', $html);
        $this->assertStringContainsString('Accept Invitation', $html);
        $this->assertStringNotContainsString('<pre', $html);
        $this->assertStringNotContainsString('&lt;table', $html);
        $this->assertStringNotContainsString('notification-logo-v2.1.png', $html);
        $this->assertMatchesRegularExpression('/<a[^>]*class="button button-primary"/', $html);
    }
}
