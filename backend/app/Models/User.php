<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $attributes = ['is_active' => true];

    protected $fillable = ['name', 'phone', 'email', 'phone_verified_at', 'date_of_birth', 'gender'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['phone_verified_at' => 'datetime', 'date_of_birth' => 'date', 'is_active' => 'boolean', 'password' => 'hashed'];
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function favorites()
    {
        return $this->belongsToMany(Product::class, 'favorites');
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }
}
