<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\PublishedServicesQuery;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;

/**
 * Published services for the marketing site (no auth).
 */
class PublicServiceController extends Controller
{
    public function index(Request $request)
    {
        $present = TranslatableContentPresenter::requestedPresentationLocale($request);

        return response()->json(['data' => PublishedServicesQuery::publicPayload($present)]);
    }
}
