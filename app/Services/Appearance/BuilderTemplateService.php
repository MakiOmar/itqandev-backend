<?php

namespace App\Services\Appearance;

use App\Models\BuilderTemplate;
use Illuminate\Validation\ValidationException;

final class BuilderTemplateService
{
    /**
     * @param  array{name: string, kind: string, document: array<string, mixed>}  $input
     */
    public function create(array $input, ?int $userId): BuilderTemplate
    {
        $kind = (string) $input['kind'];

        return BuilderTemplate::query()->create([
            'name' => $this->name($input['name']),
            'kind' => $kind,
            'document' => $this->document($kind, $input['document']),
            'created_by' => $userId,
        ]);
    }

    /**
     * @param  array{name?: string, document?: array<string, mixed>}  $input
     */
    public function update(BuilderTemplate $template, array $input): BuilderTemplate
    {
        if (array_key_exists('name', $input)) {
            $template->name = $this->name($input['name']);
        }
        if (array_key_exists('document', $input)) {
            $template->document = $this->document($template->kind, $input['document']);
        }
        $template->save();

        return $template->fresh();
    }

    /**
     * Widget/kit type of a block template, so lists can label it without loading the document.
     */
    public static function blockType(BuilderTemplate $template): ?string
    {
        if ($template->kind !== BuilderTemplate::KIND_BLOCK) {
            return null;
        }
        $doc = is_array($template->document) ? $template->document : [];
        $type = (string) ($doc['type'] ?? '');

        return $type !== '' ? $type : null;
    }

    private function name(mixed $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Name is required.']);
        }

        return $name;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function document(string $kind, array $document): array
    {
        $normalized = PageLayoutDocument::normalizeTemplateNode($kind, $document);
        if ($normalized === null) {
            throw ValidationException::withMessages(['document' => 'The template content is not a valid '.$kind.'.']);
        }

        return $normalized;
    }
}
