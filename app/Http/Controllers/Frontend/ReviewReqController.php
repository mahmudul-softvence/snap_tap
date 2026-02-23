<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Jobs\SendReviewMessageJob;
use App\Models\Review;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReviewReqController extends Controller
{
    /**
     * List all review requests of the logged-in user
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);

        $query = Review::where('user_id', Auth::id());

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->input('sort') === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $reviews = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

    /**
     * Store new review request
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'required|string|max:20',
            'provider'    => 'required|in:google,facebook',
            'status'      => ['required', Rule::in(['sent', 'reviewed', 'reminded'])],
            'message'     => 'required|string',
            'sent_sms'    => 'required|boolean',
            'sent_email'  => 'required|boolean',
            'retries'     => 'integer|min:0',
        ]);

        $review = Review::create([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        /**
         * For Createing job for sheduling the task
         */
        $review = Review::find($review->id);
        $userSettings = $review->user->basicSetting;

        $firstMessageDelay = (int) $userSettings->msg_after_checkin;
        $nextMessageDelay  = (int) $userSettings->next_message_time;
        $maxRetries        = (int) $userSettings->re_try_time;

        SendReviewMessageJob::dispatch(
            $review->id,
            (bool) $request->sent_email,
            (bool) $request->sent_sms,
            1,
            $nextMessageDelay,
            $maxRetries
        )->delay(now()->addHour($firstMessageDelay));

        return response()->json([
            'success' => true,
            'message' => 'Review request created successfully',
            'data' => $review,
        ], 201);
    }

    /**
     * Show a single review request
     */
    public function show($id): JsonResponse
    {
        $review = Review::findOrFail($id);

        $this->authorizeReview($review);

        return response()->json([
            'success' => true,
            'data' => $review,
        ]);
    }

    /**
     * Update a review request
     */
    public function update(Request $request, $id): JsonResponse
    {
        $review = Review::findOrFail($id);

        $this->authorizeReview($review);

        $validated = $request->validate([
            'status'      => ['sometimes', Rule::in(['sent', 'reviewed', 'reminded'])],
            'message'     => 'nullable|string'
        ]);

        $review->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Review request updated successfully',
            'data' => $review,
        ]);
    }

    /**
     * Change review staus
     */

    public function change_review_status($id)
    {
        $review = Review::where('unique_id', $id)->first();

        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        if ($review->status === 'reviewed') {
            return response()->json([
                'message' => 'Review has already been marked as reviewed',
                'unique_id' => $id,
                'review' => $review
            ], 200);
        }

        $review->status = 'reviewed';
        $review->save();

        return response()->json([
            'message' => 'Review status updated successfully',
            'unique_id' => $id,
            'review' => $review
        ], 200);
    }


    /**
     * Private method to authorize the review belongs to the user
     */
    private function authorizeReview(Review $review)
    {
        abort_if($review->user_id != Auth::id(), 403, 'Unauthorized');
    }
}
