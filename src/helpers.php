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
