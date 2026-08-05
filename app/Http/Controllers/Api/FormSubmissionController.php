<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormSubmissionController extends Controller
{
    public function index(Request $request, Form $form)
    {
        $this->authorize('view', $form);

        $status = $request->query('status');
        $query = $form->submissions()->latest('id');
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        return response()->json($query->paginate(max(1, min(100, (int) $request->query('per_page', 25)))));
    }

    public function show(Form $form, FormSubmission $submission)
    {
        $this->authorize('view', $form);
        abort_unless($submission->form_id === $form->id, 404);

        return response()->json($submission);
    }

    public function update(Request $request, Form $form, FormSubmission $submission)
    {
        $this->authorize('update', $form);
        abort_unless($submission->form_id === $form->id, 404);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in([
                FormSubmission::STATUS_NEW,
                FormSubmission::STATUS_READ,
                FormSubmission::STATUS_SPAM,
                FormSubmission::STATUS_ARCHIVED,
            ])],
        ]);
        $submission->update($data);

        return response()->json($submission);
    }

    public function destroy(Form $form, FormSubmission $submission)
    {
        $this->authorize('update', $form);
        abort_unless($submission->form_id === $form->id, 404);
        $submission->delete();

        return response()->noContent();
    }

    public function bulkUpdate(Request $request, Form $form)
    {
        $this->authorize('update', $form);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'status' => ['required', 'string', Rule::in([
                FormSubmission::STATUS_NEW,
                FormSubmission::STATUS_READ,
                FormSubmission::STATUS_SPAM,
                FormSubmission::STATUS_ARCHIVED,
            ])],
        ]);

        $count = $form->submissions()->whereIn('id', $data['ids'])->update(['status' => $data['status']]);

        return response()->json(['updated' => $count]);
    }

    public function exportCsv(Form $form): StreamedResponse
    {
        $this->authorize('view', $form);

        $filename = 'form-'.$form->slug.'-submissions.csv';

        return response()->streamDownload(function () use ($form) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'status', 'locale', 'ip_address', 'created_at', 'payload_json']);
            $form->submissions()->orderBy('id')->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->id,
                        $row->status,
                        $row->locale,
                        $row->ip_address,
                        optional($row->created_at)?->toIso8601String(),
                        json_encode($row->payload, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
