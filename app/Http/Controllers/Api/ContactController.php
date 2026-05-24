<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\ContactSubmissionReceived;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
        ]);

        ActivityLogService::record('contact.submitted', null, [
            'email' => $data['email'],
            'subject' => $data['subject'] ?? '',
        ], $request);

        $admins = User::permission('manage system')->get();
        if ($admins->isEmpty()) {
            $admins = User::role(['admin', 'super_admin'])->get();
        }

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new ContactSubmissionReceived($data));
        }

        return response()->json([
            'success' => true,
            'message' => 'Thank you. We will get back to you soon.',
        ]);
    }
}
