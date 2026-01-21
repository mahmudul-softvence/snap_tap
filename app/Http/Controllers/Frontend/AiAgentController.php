<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\AiAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiAgentController extends Controller
{
    /**
     * Authorization helper (same style as authorizeReview)
     */
    private function authorizeAiAgent(AiAgent $agent)
    {
        abort_if($agent->user_id != Auth::id(), 403, 'Unauthorized');
    }

    /**
     * List AI Agents
     */
    public function index(): JsonResponse
    {
        $data = AiAgent::where('user_id', Auth::id())
            ->latest()
            ->get();

        return response()->json([
            'success'  => true,
            'data' => $data
        ]);
    }

    /**
     * Create AI Agent
     */
    public function create(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'content'    => 'required|string',
            'method'      => 'required|string|max:255',
            'review_type' => 'required|integer',
            'is_active'   => 'sometimes|boolean',
        ]);

        $data['user_id'] = Auth::id();

        $agent = AiAgent::create($data);

        return response()->json([
            'success'  => true,
            'message' => 'AI Agent created successfully',
            'data'    => $agent
        ], 201);
    }

    /**
     * View AI Agent
     */
    public function show($id): JsonResponse
    {
        $agent = AiAgent::findOrFail($id);
        $this->authorizeAiAgent($agent);

        return response()->json([
            'success'  => true,
            'data'    => $agent
        ]);
    }

    /**
     * Update AI Agent
     */
    public function update(Request $request, $id): JsonResponse
    {
        $agent = AiAgent::findOrFail($id);
        $this->authorizeAiAgent($agent);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'content'    => 'sometimes|string',
            'method'      => 'sometimes|string|max:255',
            'review_type' => 'sometimes|integer',
            'is_active'   => 'sometimes|boolean',
        ]);

        $agent->update($data);

        return response()->json([
            'success'  => true,
            'message' => 'AI Agent updated successfully',
            'data'    => $agent
        ]);
    }

    /**
     * Delete AI Agent
     */
    public function destroy($id): JsonResponse
    {
        $agent = AiAgent::findOrFail($id);
        $this->authorizeAiAgent($agent);

        $agent->delete();

        return response()->json([
            'success'  => true,
            'message' => 'AI Agent deleted successfully'
        ]);
    }
}
