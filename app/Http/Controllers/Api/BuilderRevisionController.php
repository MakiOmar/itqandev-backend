<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuilderGlobal;
use App\Models\ChromeLayout;
use App\Models\Form;
use App\Models\Page;
use App\Services\Appearance\BuilderRevisionService;
use App\Services\Appearance\ChromeLayoutService;
use App\Services\Appearance\GlobalWidgetService;
use App\Services\Forms\FormLayoutDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BuilderRevisionController extends Controller
{
    public function __construct(
        private readonly ChromeLayoutService $layouts,
        private readonly GlobalWidgetService $globals,
    ) {}

    public function indexChrome(Request $request, string $kind, int $id): JsonResponse
    {
        $this->authorize('manageSettings');
        $layout = $this->findChrome($kind, $id);

        return response()->json([
            'success' => true,
            'data' => BuilderRevisionService::list($layout),
        ]);
    }

    public function restoreChrome(Request $request, string $kind, int $id, int $revisionId): JsonResponse
    {
        $this->authorize('manageSettings');
        $layout = $this->findChrome($kind, $id);
        $revision = BuilderRevisionService::find($layout, $revisionId);
        if ($revision === null) {
            abort(404, 'Revision not found.');
        }
        $document = is_array($revision->document) ? $revision->document : [];
        $layout = $this->layouts->update($layout, ['document' => $document]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $layout->id,
                'document' => $this->layouts->adminDocumentPayload($layout),
            ],
            'message' => 'Revision restored.',
        ]);
    }

    public function indexForm(int $id): JsonResponse
    {
        $this->authorize('manageSettings');

        return response()->json([
            'success' => true,
            'data' => BuilderRevisionService::list($this->findForm($id)),
        ]);
    }

    public function restoreForm(int $id, int $revisionId): JsonResponse
    {
        $this->authorize('manageSettings');
        $form = $this->findForm($id);
        $revision = BuilderRevisionService::find($form, $revisionId);
        if ($revision === null) {
            abort(404, 'Revision not found.');
        }
        $doc = is_array($revision->document) ? $revision->document : [];
        if (isset($doc['layout'])) {
            $form->layout = FormLayoutDocument::normalizeLayout($doc['layout']);
        }
        if (isset($doc['actions'])) {
            $form->actions = FormLayoutDocument::normalizeActions($doc['actions']);
        }
        if (isset($doc['settings'])) {
            $form->settings = FormLayoutDocument::normalizeSettings($doc['settings']);
        }
        $form->save();

        return response()->json([
            'success' => true,
            'message' => 'Revision restored.',
        ]);
    }

    public function indexGlobal(int $id): JsonResponse
    {
        $this->authorize('manageSettings');
        $global = BuilderGlobal::query()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => BuilderRevisionService::list($global),
        ]);
    }

    public function restoreGlobal(int $id, int $revisionId): JsonResponse
    {
        $this->authorize('manageSettings');
        $global = BuilderGlobal::query()->findOrFail($id);
        $revision = BuilderRevisionService::find($global, $revisionId);
        if ($revision === null) {
            abort(404, 'Revision not found.');
        }
        $this->globals->update($global, ['document' => $revision->document]);

        return response()->json([
            'success' => true,
            'message' => 'Revision restored.',
        ]);
    }

    public function indexPage(int $id): JsonResponse
    {
        $this->authorize('manageSettings');

        return response()->json([
            'success' => true,
            'data' => BuilderRevisionService::list($this->findPage($id)),
        ]);
    }

    public function restorePage(int $id, int $revisionId): JsonResponse
    {
        $this->authorize('manageSettings');
        $page = $this->findPage($id);
        $revision = BuilderRevisionService::find($page, $revisionId);
        if ($revision === null) {
            abort(404, 'Revision not found.');
        }
        $doc = is_array($revision->document) ? $revision->document : [];
        if (isset($doc['layout'])) {
            $page->layout = $doc['layout'];
            $page->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Revision restored.',
        ]);
    }

    private function findChrome(string $kind, int $id): ChromeLayout
    {
        if (! in_array($kind, ChromeLayout::KINDS, true)) {
            $kind = match ($kind) {
                'headers' => ChromeLayout::KIND_HEADER,
                'footers' => ChromeLayout::KIND_FOOTER,
                'bodies' => ChromeLayout::KIND_BODY,
                'singles' => ChromeLayout::KIND_SINGLE,
                'archives' => ChromeLayout::KIND_ARCHIVE,
                'loop-items' => ChromeLayout::KIND_LOOP_ITEM,
                'overlays' => ChromeLayout::KIND_OVERLAY,
                default => $kind,
            };
        }
        if (! in_array($kind, ChromeLayout::KINDS, true)) {
            throw ValidationException::withMessages(['kind' => 'Invalid chrome kind.']);
        }
        $layout = ChromeLayout::query()->where('kind', $kind)->find($id);
        if ($layout === null) {
            abort(404, 'Layout not found.');
        }

        return $layout;
    }

    private function findForm(int $id): Form
    {
        $form = Form::query()->find($id);
        if ($form === null) {
            abort(404, 'Form not found.');
        }

        return $form;
    }

    private function findPage(int $id): Page
    {
        $page = Page::query()->find($id);
        if ($page === null) {
            abort(404, 'Page not found.');
        }

        return $page;
    }
}
