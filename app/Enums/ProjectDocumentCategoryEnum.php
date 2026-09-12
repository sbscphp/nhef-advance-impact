<?php

namespace App\Enums;

enum ProjectDocumentCategoryEnum: string
{
    case FINANCE = 'finance';
    case SALES = 'sales';
    case PRODUCT_DEVELOPMENT = 'product_development';
    case INFORMATION_TECHNOLOGY = 'information_technology';
    case HUMAN_RESOURCES = 'human_resources';
    case LEGAL = 'legal';
    case MARKETING = 'marketing';
    case CUSTOMER_SUPPORT = 'customer_support';
    case OPERATIONS = 'operations';
    case GENERAL_KNOWLEDGE = 'general_knowledge';

    public function label(): string
    {
        return match ($this) {
            self::FINANCE => 'Finance',
            self::SALES => 'Sales',
            self::PRODUCT_DEVELOPMENT => 'Product Development',
            self::INFORMATION_TECHNOLOGY => 'Information Technology',
            self::HUMAN_RESOURCES => 'Human Resources',
            self::LEGAL => 'Legal',
            self::MARKETING => 'Marketing',
            self::CUSTOMER_SUPPORT => 'Customer Support',
            self::OPERATIONS => 'Operations',
            self::GENERAL_KNOWLEDGE => 'General Knowledge',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
