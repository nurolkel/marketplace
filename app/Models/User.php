<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RestaurantRole;
use App\Enums\UserType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Passkey;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserType $type
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Restaurant> $restaurants
 * @property-read Collection<int, Passkey> $passkeys
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => UserType::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Restaurants this user belongs to, in any role.
     *
     * @return BelongsToMany<Restaurant, $this>
     */
    public function restaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The role this user holds at the given restaurant, if any.
     */
    public function roleInRestaurant(Restaurant $restaurant): ?RestaurantRole
    {
        $membership = $this->restaurants()
            ->where('restaurants.id', $restaurant->id)
            ->first();

        $role = $membership?->pivot?->getAttribute('role');

        return is_string($role) ? RestaurantRole::from($role) : null;
    }

    public function hasRoleInRestaurant(Restaurant $restaurant, RestaurantRole $role): bool
    {
        return $this->roleInRestaurant($restaurant) === $role;
    }

    /**
     * Whether the user belongs to the restaurant in any capacity.
     */
    public function isStaffOf(Restaurant $restaurant): bool
    {
        return $this->roleInRestaurant($restaurant) !== null;
    }

    /**
     * Marketplace operator team member. Admins can do anything on the
     * platform via the Gate::before hook.
     */
    public function isMarketplaceAdmin(): bool
    {
        return $this->type === UserType::Admin;
    }

    /**
     * Works at one or more restaurants, in any role.
     */
    public function isRestaurantStaff(): bool
    {
        return $this->restaurants()->exists();
    }

    /**
     * Regular shopper account. This is the default for every registration.
     */
    public function isCustomer(): bool
    {
        return $this->type === UserType::Customer;
    }
}
