<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DomainFormat implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * Validates that the value is a properly formatted domain name.
     * Accepts: example.com, sub.example.com, example.co.uk
     * Rejects: just "test", IP addresses, URLs with protocols
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Clean the domain first (remove protocol and www if present)
        $domain = preg_replace('#^https?://#i', '', $value);
        $domain = preg_replace('#^www\.#i', '', $domain);
        $domain = rtrim($domain, '/');

        // Domain format regex:
        // - One or more labels separated by dots
        // - Each label: starts/ends with alphanumeric, can contain hyphens
        // - TLD must be at least 2 characters, letters only
        $pattern = '/^([a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/';

        if (!preg_match($pattern, $domain)) {
            $fail('Le format du domaine est invalide. Exemple: monsite.com');
        }
    }
}
