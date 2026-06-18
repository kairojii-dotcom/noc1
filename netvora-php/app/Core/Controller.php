<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base controller — shared helpers for request validation.
 */
abstract class Controller
{
    /**
     * Validate input against a simple rule map and return the cleaned data.
     * Rules: required, email, string, int, numeric, min:n, max:n, in:a,b,c, uuid
     */
    protected function validate(Request $request, array $rules): array
    {
        $data = $request->all();
        $errors = [];
        $clean = [];

        foreach ($rules as $field => $ruleStr) {
            $value = $data[$field] ?? null;
            $ruleList = explode('|', $ruleStr);
            $required = in_array('required', $ruleList, true);

            if ($required && ($value === null || $value === '')) {
                $errors[$field] = "$field is required";
                continue;
            }
            if (!$required && ($value === null || $value === '')) {
                continue;
            }

            foreach ($ruleList as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $ok = match ($name) {
                    'email'   => (bool) filter_var($value, FILTER_VALIDATE_EMAIL),
                    'int'     => filter_var($value, FILTER_VALIDATE_INT) !== false,
                    'numeric' => is_numeric($value),
                    'string'  => is_string($value),
                    'min'     => is_numeric($value) ? $value >= (float) $param : mb_strlen((string) $value) >= (int) $param,
                    'max'     => is_numeric($value) ? $value <= (float) $param : mb_strlen((string) $value) <= (int) $param,
                    'in'      => in_array((string) $value, explode(',', (string) $param), true),
                    'uuid'    => (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $value),
                    default   => true,
                };
                if (!$ok) {
                    $errors[$field] = "$field failed rule: $rule";
                    break;
                }
            }
            $clean[$field] = $value;
        }

        if ($errors) {
            Response::error('Validation failed', 422, $errors);
        }
        return $clean;
    }

    protected function paginationParams(Request $request): array
    {
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));
        return [$page, $perPage, ($page - 1) * $perPage];
    }
}
