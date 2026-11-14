<?php
declare(strict_types=1);

use App\App;
use App\Auth;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL μέσα στην εφαρμογή, ανεξάρτητα από τον υποφάκελο όπου είναι στημένη. */
function url(string $path = '/'): string
{
    return App::base() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = dirname(__DIR__) . '/public/assets/' . $path;
    return url('assets/' . $path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Auth::csrf()) . '">';
}

/** «400 γρ.» από ποσότητα + μονάδα. */
function qty(array $ingredient): string
{
    return trim(trim((string) ($ingredient['quantity'] ?? '')) . ' ' . trim((string) ($ingredient['unit'] ?? '')));
}

/** @return list<array{0:string,1:string}> Μερίδες/χρόνοι που έχουν τιμή. */
function recipe_meta(array $recipe): array
{
    $out = [];
    foreach ([
        'Μερίδες' => 'servings', 'Προετοιμασία' => 'prep_time', 'Μαγείρεμα' => 'cook_time', 'Συνολικά' => 'total_time',
    ] as $label => $key) {
        $value = trim((string) ($recipe[$key] ?? ''));
        if ($value !== '') {
            $out[] = [$label, $value];
        }
    }
    return $out;
}

/** Ομάδες υλικών που έχουν τουλάχιστον ένα υλικό. */
function ingredient_groups(array $recipe): array
{
    return array_values(array_filter($recipe['ingredient_groups'] ?? [], fn ($g) => !empty($g['items'])));
}

function has_estimates(array $recipe): bool
{
    foreach (ingredient_groups($recipe) as $group) {
        foreach ($group['items'] as $item) {
            if (($item['source'] ?? '') === 'εκτίμηση') {
                return true;
            }
        }
    }
    return false;
}

function greek_date(string $datetime): string
{
    $months = ['Ιαν', 'Φεβ', 'Μαρ', 'Απρ', 'Μαΐ', 'Ιουν', 'Ιουλ', 'Αυγ', 'Σεπ', 'Οκτ', 'Νοε', 'Δεκ'];
    $t = strtotime($datetime);
    return $t === false ? '' : date('j', $t) . ' ' . $months[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}

/** Μικρό εικονίδιο γραμμής (inline SVG). */
function icon(string $name): string
{
    $paths = [
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'flame' => '<path d="M12 3c1 3 5 5 5 10a5 5 0 0 1-10 0c0-2 1-3 2-4 0 2 1 3 2 3 0-3-1-5 1-9z"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8"/><path d="M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>',
        'timer' => '<circle cx="12" cy="13.5" r="7.5"/><path d="M12 9.5v4l2.5 1.5"/><path d="M9.5 2.5h5"/>',
        'basket' => '<path d="M4 10h16l-1.5 9.5h-13z"/><path d="M8.5 10 12 4l3.5 6"/>',
        'list' => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>',
        'play' => '<circle cx="12" cy="12" r="9"/><path d="m10 8.5 5.5 3.5-5.5 3.5z"/>',
        'download' => '<path d="M12 4v11"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M5 20h14"/>',
        'printer' => '<path d="M7 9V4h10v5"/><rect x="4" y="9" width="16" height="8" rx="2"/><path d="M7 14h10v6H7z"/>',
        'trash' => '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6.5 7l1 13h9l1-13"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" '
        . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/** Πόσα υλικά έχει συνολικά η συνταγή. */
function ingredient_count(array $recipe): int
{
    return array_sum(array_map(fn ($g) => count($g['items']), ingredient_groups($recipe)));
}

/** Χρόνος που αναφέρεται μέσα σε ένα βήμα (π.χ. «15 λεπτά»), ή κενό. */
function step_time(string $step): string
{
    return preg_match('/\d+(?:\s*[-–]\s*\d+)?\s*(?:λεπτ[άόώ]\w*|ώρ[αε]ς?|δευτερόλεπτ\w*)/u', $step, $m) ? $m[0] : '';
}
