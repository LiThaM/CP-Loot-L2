<?php

namespace App\Models;

class User extends \App\Contexts\Identity\Domain\Models\User
{
    // Mirrors the Identity User's fillable (so profile/preferences fields like
    // theme_preference actually persist) plus role_id, which this auth model
    // allows for admin user-management. Without theme_preference here, saving
    // the dark-mode preference was silently dropped by fill().
    protected $fillable = ['name', 'email', 'password', 'cp_id', 'role_id', 'membership_status', 'theme_preference', 'language_preference', 'changelog_last_seen_at', 'changelog_emails_enabled', 'main_class_id', 'main_race', 'main_level', 'avatar_path'];

    protected $hidden = ['password', 'remember_token'];
}
