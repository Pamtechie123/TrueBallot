<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Server-side form checks.
 *
 * Example:
 *   $v = Validator::make($data, ['email' => ['required', 'email', 'max:150']]);
 *   if ($v->fails()) { $errors = $v->errors(); }
 *
 * Rules: required, email, min:N, max:N, int, between:A,B, in:a,b,c, same:otherField,
 *        datetime, after:otherField
 * Put "int" before "between". Only the first problem for each field is reported.
 */
final class Validator
{
    private array $errors = [];

    private function __construct(
        private array $data,
        private array $rules,
        private array $labels
    ) {
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        $validator = new self($data, $rules, $labels);
        $validator->run();
        return $validator;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,string> field name => message */
    public function errors(): array
    {
        return $this->errors;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $rules) {
            $value = (string) ($this->data[$field] ?? '');

            foreach ($rules as $rule) {
                [$name, $argument] = array_pad(explode(':', $rule, 2), 2, null);

                if ($name !== 'required' && $value === '') {
                    continue;   // optional empty fields are fine
                }

                $message = $this->check($name, $argument, $field, $value);
                if ($message !== null) {
                    $this->errors[$field] = $message;
                    break;
                }
            }
        }
    }

    private function check(string $rule, ?string $argument, string $field, string $value): ?string
    {
        $label = $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));

        switch ($rule) {
            case 'required':
                return $value === '' ? "$label is required." : null;

            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "$label must be a valid email address.";

            case 'min':
                return mb_strlen($value) < (int) $argument ? "$label must be at least $argument characters long." : null;

            case 'max':
                return mb_strlen($value) > (int) $argument ? "$label must not be longer than $argument characters." : null;

            case 'int':
                return preg_match('/^\d+$/', $value) === 1 ? null : "$label must be a whole number.";

            case 'between':
                [$min, $max] = array_map('intval', explode(',', (string) $argument));
                $number = (int) $value;
                return ($number < $min || $number > $max) ? "$label must be between $min and $max." : null;

            case 'in':
                return in_array($value, explode(',', (string) $argument), true) ? null : "Please choose a valid option for $label.";

            case 'same':
                return $value === (string) ($this->data[$argument] ?? '') ? null : "$label does not match.";

            case 'datetime':   // the format a date-and-time form field sends: 2026-10-18T14:30
                $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
                return ($date !== false && $date->format('Y-m-d\TH:i') === $value) ? null : "$label must be a valid date and time.";

            case 'after':      // must be later than another date-and-time field
                $otherLabel = $this->labels[$argument] ?? ucfirst(str_replace('_', ' ', (string) $argument));
                return $value > (string) ($this->data[$argument] ?? '') ? null : "$label must be later than " . strtolower($otherLabel) . '.';

            default:
                throw new \InvalidArgumentException("Unknown validation rule: $rule");
        }
    }
}
