<?php

namespace App\Constants;

class DocumentCategories
{
    public const CATEGORIES = [
        'Identity' => [
            'Aadhaar',
            'PAN',
            'Passport',
            'Driving Licence',
        ],
        'Education' => [
            '10th',
            '12th',
            'Degree',
            'Certificates',
        ],
        'Employment' => [
            'Offer Letter',
            'Appointment Letter',
            'Experience Letter',
            'Relieving Letter',
        ],
        'Company' => [
            'NDA',
            'Policy documents',
            'Agreements',
        ],
    ];

    public static function getCategories(): array
    {
        return array_keys(self::CATEGORIES);
    }

    public static function getTypesForCategory(string $category): array
    {
        return self::CATEGORIES[$category] ?? [];
    }

    public static function isValidCategory(string $category): bool
    {
        return array_key_exists($category, self::CATEGORIES);
    }

    public static function isValidType(string $category, string $type): bool
    {
        if (!self::isValidCategory($category)) {
            return false;
        }

        return in_array($type, self::CATEGORIES[$category], true);
    }
}
