<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Session;

/**
 * Small global functions used by the views and controllers.
 */

/** Read a setting from the environment (Render) or from the .env file (local). */
function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($value === false || $value === '') ? $default : (string) $value;
}

/** Load KEY=value lines from the .env file into the environment. Real environment values win. */
function load_env(string $file): void
{
    if (!is_file($file)) {
        return;
    }

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");

        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

/** Escape text before printing it in HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** The folder the site runs from, e.g. "/TrueBallot/public" on XAMPP or "" on Render. */
function base_url(): string
{
    $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return rtrim($base, '/');
}

/** Link to a page of this site: url('/login') */
function url(string $path = '/'): string
{
    return base_url() . '/' . ltrim($path, '/');
}

/** Link to a file in public/assets */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = BASE_PATH . '/public/assets/' . $path;
    $version = is_file($file) ? '?v=' . filemtime($file) : '';   // a new version number makes browsers load the new file
    return url('assets/' . $path) . $version;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/** Hidden field that every POST form must include. */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Auth::csrfToken()) . '">';
}

/** What the user typed before the form came back with errors (already escaped). */
function old(string $key, string $default = ''): string
{
    return e(Session::old($key, $default));
}

// ---- form errors ----

function form_errors(): array
{
    return (array) Session::flashed('errors', []);
}

function has_error(string $field): bool
{
    return isset(form_errors()[$field]);
}

function field_error(string $field): string
{
    if (!has_error($field)) {
        return '';
    }
    return '<p class="field-error" id="' . e($field) . '-error">' . e(form_errors()[$field]) . '</p>';
}

/** Accessibility attributes for an input that has an error. */
function field_attrs(string $field): string
{
    return has_error($field) ? 'aria-invalid="true" aria-describedby="' . e($field) . '-error"' : '';
}

// ---- display ----

/** prefer_not_to_say -> Prefer not to say */
function label(string $value): string
{
    return ucfirst(str_replace('_', ' ', $value));
}

function status_badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(label($status)) . '</span>';
}

/** 5000 -> ₦5,000.00 */
function naira(float|int $amount): string
{
    return '₦' . number_format((float) $amount, 2);
}

/** 2026-10-18 14:30:00 -> 18 Oct 2026, 2:30 PM */
function format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $time = strtotime($value);
    return $time === false ? $value : date('j M Y, g:i A', $time);
}

/** Default value for a datetime-local input: now + some days, at 9 AM. */
function datetime_input(int $daysFromNow): string
{
    return date('Y-m-d\T09:00', strtotime('+' . $daysFromNow . ' days'));
}

/** Words for an election phase, shown to the user. */
function phase_label(string $phase): string
{
    return [
        'upcoming'          => 'Not started',
        'applications_open' => 'Applications open',
        'waiting'           => 'Voting soon',
        'voting_open'       => 'Voting open',
        'voting_closed'     => 'Voting ended',
        'results_published' => 'Results published',
    ][$phase] ?? label($phase);
}

function phase_badge(string $phase): string
{
    return '<span class="badge badge-' . e($phase) . '">' . e(phase_label($phase)) . '</span>';
}