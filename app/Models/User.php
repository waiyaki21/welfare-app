<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        'google_id', 'avatar', 'role',
        'email_verified_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];

    public function getInitialsAttribute(): string
    {
        return collect(explode(' ', $this->name))
            ->take(2)->map(fn ($w) => strtoupper($w[0]))->implode('');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasGoogleAuth(): bool
    {
        return !empty($this->google_id);
    }

    public function hasPasswordAuth(): bool
    {
        return !empty($this->password);
    }
}
