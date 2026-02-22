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
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);

        $query = AiAgent::where('user_id', Auth::id());

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('method', 'like', "%{$search}%");
            });
        }

        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }

        if ($request->input('sort') === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $agents = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $agents
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
