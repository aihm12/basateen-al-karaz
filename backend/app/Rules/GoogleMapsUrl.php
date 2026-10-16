<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class GoogleMapsUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $p = parse_url($value);
        $host = strtolower($p['host'] ?? '');
        $path = $p['path'] ?? '';
        $valid = ($p['scheme'] ?? '') === 'https' && ! isset($p['user']) && ! isset($p['pass']) && ! isset($p['port']) && (
            ($host === 'maps.app.goo.gl' && strlen($path) > 1) || ($host === 'goo.gl' && str_starts_with($path, '/maps/')) ||
            (in_array($host, ['www.google.com', 'google.com', 'maps.google.com'], true) && ($host === 'maps.google.com' || $path === '/maps' || str_starts_with($path, '/maps/'))));
        if (! $valid) {
            $fail('ألصق رابط مشاركة الموقع من Google Maps بصيغة https.');
        }
    }
}
