<?php

use App\Models\User;

it('focuses the password field and closes the delete account modal with Escape', function (): void {
    User::factory()->withStreamerTeam()->create([
        'email' => 'browser-profile-modal@example.test',
    ]);

    $this->visit('/login')
        ->fill('Email address', 'browser-profile-modal@example.test')
        ->fill('Password', 'password')
        ->keys('#password', 'Enter')
        ->assertPathIs('/dashboard');

    $page = $this->visit('/user/profile')
        ->assertPathIs('/user/profile')
        ->click('button[wire\\:click="confirmUserDeletion"]')
        ->wait(0.5)
        ->assertVisible('.jetstream-modal.show')
        ->assertVisible('.jetstream-modal.show h5.modal-title')
        ->assertSeeIn('.jetstream-modal.show .modal-body', 'Are you sure you want to delete your account?')
        ->assertScript("document.activeElement.getAttribute('placeholder')", 'Password');

    $page->script("window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))");

    $page->wait(0.1)
        ->assertScript("document.querySelector('.jetstream-modal.show') === null")
        ->assertScript("document.activeElement.textContent.trim()", 'Delete Account');
});
