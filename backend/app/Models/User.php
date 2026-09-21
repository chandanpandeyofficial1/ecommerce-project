<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'phone'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // True when the user has the admin role.
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // Items currently in the user's cart.
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    // Orders placed by the user.
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
