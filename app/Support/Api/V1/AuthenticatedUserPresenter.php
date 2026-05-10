<?php

declare(strict_types=1);

namespace App\Support\Api\V1;

use Illuminate\Contracts\Auth\Authenticatable;

final class AuthenticatedUserPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(Authenticatable $user): array
    {
        return [
            'id' => $user->getAuthIdentifier(),
            'name' => self::stringProperty($user, 'name'),
            'email' => self::stringProperty($user, 'email'),
            'role' => self::stringProperty($user, 'role'),
            'abilities' => self::abilities($user),
        ];
    }

    /**
     * @return list<string>
     */
    private static function abilities(Authenticatable $user): array
    {
        if (self::isAdmin($user)) {
            return [
                'news.read',
                'news.manage',
                'sources.manage',
                'ai-providers.manage',
                'settings.manage',
                'admin.access',
            ];
        }

        return ['news.read'];
    }

    private static function isAdmin(Authenticatable $user): bool
    {
        if (method_exists($user, 'isAdmin')) {
            $result = $user->isAdmin();

            return $result === true;
        }

        return self::stringProperty($user, 'role') === 'admin';
    }

    private static function stringProperty(Authenticatable $user, string $property): string
    {
        if (method_exists($user, 'getAttribute')) {
            $value = $user->getAttribute($property);

            return is_scalar($value) ? (string) $value : '';
        }

        if (! property_exists($user, $property)) {
            return '';
        }

        $value = $user->{$property};

        return is_scalar($value) ? (string) $value : '';
    }
}
