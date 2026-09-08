<?php

namespace App\Enums;

enum CustomFieldTypeEnum: string
{
    case TEXT = 'text';
    case LONG_TEXT = 'long_text';
    case NUMBER = 'number';
    case DATE = 'date';
    case DROPDOWN = 'dropdown';
    case MULTI_SELECT = 'multi_select';
    case CHECKBOX = 'checkbox';
    case USER_LOOKUP = 'user_lookup';
    case FILE_UPLOAD = 'file_upload';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Text',
            self::LONG_TEXT => 'Long Text',
            self::NUMBER => 'Number',
            self::DATE => 'Date',
            self::DROPDOWN => 'Dropdown',
            self::MULTI_SELECT => 'Multiple Select',
            self::CHECKBOX => 'Checkbox',
            self::USER_LOOKUP => 'User Lookup',
            self::FILE_UPLOAD => 'File Upload',
        };
    }

    public function requiresOptions(): bool
    {
        return $this === self::DROPDOWN || $this === self::MULTI_SELECT;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
