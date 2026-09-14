<?php

namespace App\Services\Forms;

use App\Models\Form;
use Illuminate\Support\Carbon;

/**
 * Replace {{field_id}}, {{form_title}}, {{submitted_at}} in action templates.
 */
final class FormMergeTags
{
    /**
     * @param  array<string, mixed>  $values
     */
    public static function apply(string $template, Form $form, array $values): string
    {
        $map = [
            'form_title' => (string) $form->title,
            'submitted_at' => Carbon::now()->toIso8601String(),
        ];
        foreach ($values as $key => $value) {
            $map[(string) $key] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', function (array $m) use ($map) {
            $id = $m[1];

            return array_key_exists($id, $map) ? (string) $map[$id] : '';
        }, $template);
    }
}
