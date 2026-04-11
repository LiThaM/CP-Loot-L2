<?php

namespace App\Models;

class User extends \App\Contexts\Identity\Domain\Models\User
{
    protected $fillable = ['name', 'email', 'password', 'cp_id', 'role_id'];

    protected $hidden = ['password', 'remember_token'];
}
