<?php

declare(strict_types=1);

namespace FP\DiscountGift\Core;

use function get_role;

/**
 * Gestione capability del plugin.
 */
final class Roles
{
    public const MANAGE = 'manage_fp_discountgift';
    public const VIEW = 'view_fp_discountgift';

    /**
     * Assegna capability ai ruoli compatibili.
     */
    public static function addCapabilities(): void
    {
        $roles = ['administrator', 'shop_manager'];

        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if (! $role) {
                continue;
            }

            $role->add_cap(self::MANAGE);
            $role->add_cap(self::VIEW);
        }
    }

    /**
     * Rimuove capability custom alla disattivazione.
     */
    public static function removeCapabilities(): void
    {
        $roles = ['administrator', 'shop_manager'];

        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if (! $role) {
                continue;
            }

            $role->remove_cap(self::MANAGE);
            $role->remove_cap(self::VIEW);
        }
    }
}
