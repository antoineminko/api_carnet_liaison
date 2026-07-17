<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AI\AiService;
use Exception;

class AiController extends Controller
{
    protected AiService $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function summarize(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        try {
            $summary = $this->aiService->generateSummary($request->input('content'));
            return response()->json([
                'success' => true,
                'summary' => $summary
            ]);
        } catch (Exception $e) {
            // Return 503 if AI provider fails or is not configured
            return response()->json([
                'success' => false,
                'message' => 'Service IA indisponible.',
                'error' => $e->getMessage()
            ], 503);
        }
    }
}
