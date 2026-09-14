<?php

namespace App\Services\Appearance;

use App\Models\BuilderRevision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class BuilderRevisionService
{
    public const MAX_PER_DOCUMENT = 30;

    /**
     * @param  array<string, mixed>  $document
     */
    public static function snapshot(Model $model, array $document): void
    {
        BuilderRevision::query()->create([
            'revisable_type' => $model::class,
            'revisable_id' => $model->getKey(),
            'document' => $document,
            'created_by' => Auth::id(),
        ]);

        $keepIds = BuilderRevision::query()
            ->where('revisable_type', $model::class)
            ->where('revisable_id', $model->getKey())
            ->orderByDesc('id')
            ->limit(self::MAX_PER_DOCUMENT)
            ->pluck('id');

        if ($keepIds->isNotEmpty()) {
            BuilderRevision::query()
                ->where('revisable_type', $model::class)
                ->where('revisable_id', $model->getKey())
                ->whereNotIn('id', $keepIds)
                ->delete();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function list(Model $model): array
    {
        return BuilderRevision::query()
            ->where('revisable_type', $model::class)
            ->where('revisable_id', $model->getKey())
            ->orderByDesc('id')
            ->limit(self::MAX_PER_DOCUMENT)
            ->get()
            ->map(fn (BuilderRevision $row) => [
                'id' => $row->id,
                'created_at' => $row->created_at?->toIso8601String(),
                'created_by' => $row->created_by,
            ])
            ->all();
    }

    public static function find(Model $model, int $revisionId): ?BuilderRevision
    {
        return BuilderRevision::query()
            ->where('revisable_type', $model::class)
            ->where('revisable_id', $model->getKey())
            ->where('id', $revisionId)
            ->first();
    }
}
