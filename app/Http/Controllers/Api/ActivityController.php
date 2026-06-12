<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewActivityLogs');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'max:120'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($filters['per_page'] ?? 20);

        $query = ActivityLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('action', 'like', $term)
                    ->orWhere('subject_type', 'like', $term)
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', $term)->orWhere('email', 'like', $term));
            });
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function (ActivityLog $log) {
            return [
                'id' => (string) $log->id,
                'userId' => $log->user_id ? (string) $log->user_id : '',
                'userName' => $log->user?->name ?? 'System',
                'action' => $log->action,
                'resource' => $log->subject_type
                    ? class_basename($log->subject_type).($log->subject_id ? ' #'.$log->subject_id : '')
                    : '',
                'ipAddress' => $log->ip_address ?? '',
                'createdAt' => $log->created_at?->toIso8601String() ?? '',
                'properties' => $log->properties ?? [],
            ];
        });

        return response()->json($paginator);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewActivityLogs');

        $filters = $request->validate([
            'date_from' => ['nullable', 'date', 'before_or_equal:today'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $filename = 'activity-logs-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'user', 'action', 'resource', 'ip', 'created_at']);

            $query = ActivityLog::query()
                ->with('user:id,name,email')
                ->orderByDesc('created_at');

            if (! empty($filters['date_from'])) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }
            if (! empty($filters['date_to'])) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            $query->chunk(200, function ($logs) use ($handle) {
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            $log->id,
                            $log->user?->email ?? '',
                            $log->action,
                            $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '',
                            $log->ip_address ?? '',
                            $log->created_at?->toDateTimeString() ?? '',
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
