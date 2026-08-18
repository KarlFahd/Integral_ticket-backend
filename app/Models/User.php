<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'role',
        'is_admin',
        'is_hr',
        'email',
        'password',
        'totp_secret',
        'two_factor_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'is_admin'           => 'boolean',
            'is_hr'              => 'boolean',
            'two_factor_enabled' => 'boolean',
        ];
    }

    /** @return HasMany<TicketNotification, $this> */
    public function ticketNotifications(): HasMany
    {
        return $this->hasMany(TicketNotification::class);
    }

    /** @return HasMany<Event, $this> */
    public function createdEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by_user_id');
    }

    /** @return BelongsToMany<Event, $this> */
    public function eventParticipations(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_participants');
    }

    /** @return HasMany<EventReminder, $this> */
    public function eventReminders(): HasMany
    {
        return $this->hasMany(EventReminder::class);
    }
}
