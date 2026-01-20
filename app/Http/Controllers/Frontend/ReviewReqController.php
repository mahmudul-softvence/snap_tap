<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Jobs\SendReviewMessageJob;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReviewReqController extends Controller
{
    /**
     * List all review requests of the logged-in user
     */
    public function index(): JsonResponse
    {

        $total_request = Review::where('user_id', Auth::id())->count();

        $reviews = Review::where('user_id', Auth::id())->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $reviews,
            'total_request' => $total_request
        ]);
    }

    /**
     * Store new review request
     */
    public function create(Request $request): JsonResponse
    {

        if (true) { //$request->user->onFreeTrial()

            $reviewCount = auth()->user()->reviews()->count();

            if ($reviewCount >= 5) {
                return response()->json([
                    'success' => false,
                    'message' => 'Free trial users can only send up to 5 review requests.'
                ], 403);
            }
        }



        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'required|string|max:20',
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

        $firstMessageDelay = $userSettings->msg_after_checkin;
        $nextMessageDelay = $userSettings->next_message_time;
        $maxRetries = $userSettings->re_try_time;

        SendReviewMessageJob::dispatch(
            $review->id,
            $request->sent_email,
            $request->sent_sms,
            1,
            $nextMessageDelay,
            $maxRetries
        )->delay(now()->addMinute($firstMessageDelay));



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
            'name'        => 'sometimes|string|max:255',
            'email'       => 'sometimes|email|max:255',
            'phone'       => 'nullable|string|max:20',
            'status'      => ['sometimes', Rule::in(['sent', 'reviewed', 'reminded'])],
            'message'     => 'nullable|string',
            'sent_sms'    => 'boolean',
            'sent_email'  => 'boolean',
            'retries'     => 'integer|min:0',
        ]);

        $review->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Review request updated successfully',
            'data' => $review,
        ]);
    }

    /**
     * Delete a review request
     */
    public function destroy($id): JsonResponse
    {
        $review = Review::findOrFail($id);

        $this->authorizeReview($review);

        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'Review request deleted successfully',
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
