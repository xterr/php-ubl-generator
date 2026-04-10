<?php declare(strict_types=1);

namespace Xterr\UBL\Generator\Resolver;

final class PhpTypeNormalizer
{
    private const array SCALAR_TYPES = [
        'string', 'int', 'float', 'bool', 'array', 'mixed',
        'void', 'never', 'null', 'true', 'false', 'object',
        'callable', 'iterable', 'self', 'static', 'parent',
    ];

    public static function normalize(string $type): string
    {
        if (in_array(strtolower($type), self::SCALAR_TYPES, true)) {
            return strtolower($type);
        }

        if (str_starts_with($type, '\\')) {
            return $type;
        }

        return '\\' . $type;
    }

    /**
     * @param array<string, string> $map
     * @return array<string, string>
     */
    public static function normalizeMap(array $map): array
    {
        return array_map(self::normalize(...), $map);
    }
}
