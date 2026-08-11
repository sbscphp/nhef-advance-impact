<?php

namespace App\Enums;

/**
 * UI-level grouping of the granular ModuleEnums keys used to tag notifications, matching the
 * admin "Notification" screen's left-hand category filter (System Notification / Core Platform /
 * Alumni & Advancement Suite / Impact & Grant / Others). This grouping isn't specified anywhere
 * in the BRD or existing module mapping (see PermissionModuleMapper, which only lists modules
 * flat) - it's inferred from the design. Adjust modules() below if a category should cover a
 * different set of ModuleEnums values.
 */
enum NotificationCategoryEnum: string
{
    case SYSTEM = 'system_notification';
    case CORE_PLATFORM = 'core_platform';
    case ALUMNI_ADVANCEMENT = 'alumni_advancement_suite';
    case IMPACT_GRANT = 'impact_grant';
    case OTHERS = 'others';

    public function label(): string
    {
        return match ($this) {
            self::SYSTEM => 'System Notification',
            self::CORE_PLATFORM => 'Core Platform',
            self::ALUMNI_ADVANCEMENT => 'Alumni & Advancement Suite',
            self::IMPACT_GRANT => 'Impact & Grant',
            self::OTHERS => 'Others',
        };
    }

    /**
     * @return list<string> ModuleEnums values grouped under this category.
     */
    public function modules(): array
    {
        return match ($this) {
            self::SYSTEM => [
                ModuleEnums::authentication->value,
                ModuleEnums::settings->value,
                ModuleEnums::system_configuration->value,
                ModuleEnums::user_management->value,
                ModuleEnums::audit_trail->value,
                ModuleEnums::custom_field->value,
            ],
            self::CORE_PLATFORM => [
                ModuleEnums::fundraising->value,
                ModuleEnums::donation->value,
                ModuleEnums::events->value,
            ],
            self::ALUMNI_ADVANCEMENT => [
                ModuleEnums::alumni->value,
                ModuleEnums::constituent_management->value,
                ModuleEnums::mentorship->value,
                ModuleEnums::networking->value,
                ModuleEnums::crm->value,
            ],
            self::IMPACT_GRANT => [
                ModuleEnums::reporting->value,
            ],
            self::OTHERS => self::othersModules(),
        };
    }

    public static function forModule(?string $module): self
    {
        if ($module === null || $module === '') {
            return self::OTHERS;
        }

        foreach (self::cases() as $category) {
            if ($category !== self::OTHERS && in_array($module, $category->modules(), true)) {
                return $category;
            }
        }

        return self::OTHERS;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Everything not explicitly placed in another category: communications, dashboard, guest,
     * and any future ModuleEnums case nobody has categorized yet.
     *
     * @return list<string>
     */
    private static function othersModules(): array
    {
        $categorized = array_merge(
            self::SYSTEM->modules(),
            self::CORE_PLATFORM->modules(),
            self::ALUMNI_ADVANCEMENT->modules(),
            self::IMPACT_GRANT->modules(),
        );

        return array_values(array_diff(ModuleEnums::values(), $categorized));
    }
}
