<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\GetReview;
use App\Models\ReviewReply;
use App\Models\UserBusinessAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Jobs\ReviewReplyJob;
use App\Jobs\ReviewReplyDeleteJob;
use LucianoTonet\GroqLaravel\Facades\Groq;
use OpenAI\Laravel\Facades\OpenAI;



class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $accounts = \App\Models\UserBusinessAccount::where('user_id', auth()->id())->get();

        if ($accounts->isEmpty()) {
            return response()->json([
                'current_page' => 1,
                'data' => [],
                'total' => 0,
                'message' => 'No business account found. Please connect an account first.'
            ]);
        }

        $platform  = strtolower($request->query('platform', 'both')); // facebook / google / both
        $status    = strtolower($request->query('status', ''));       // replied / pending
        $search    = strtolower($request->query('search', ''));
        $sort      = strtolower($request->query('sort', 'latest'));   // latest / oldest / az
        $limit     = intval($request->query('per_page', 10));
        $replyType = $request->query('reply_type');                    // ai_reply / manual_reply

        if ($platform !== 'both') {
            $specificAccount = $accounts->where('provider', $platform)->first();

            if (!$specificAccount) {
                return response()->json([
                    'current_page' => 1,
                    'data' => [],
                    'total' => 0,
                    'message' => "No $platform account linked."
                ]);
            }

            if ($specificAccount->status === 'disconnect') {
                return response()->json([
                    'current_page' => 1,
                    'data' => [],
                    'total' => 0,
                    'message' => "Your $platform account has been disconnected by the admin. Please contact support."
                ]);
            }
        } else {
            $connectedProviders = $accounts->where('status', 'connected')->pluck('provider')->toArray();
            if (empty($connectedProviders)) {
                return response()->json([
                    'current_page' => 1,
                    'data' => [],
                    'total' => 0,
                    'message' => "Your business accounts have been disconnected by the admin."
                ]);
            }
        }

        $query = GetReview::where('user_id', auth()->id());

        if ($platform !== 'both') {
            $query->where('provider', $platform);
        } else {
            $connectedProviders = $accounts->where('status', 'connected')->pluck('provider')->toArray();
            $query->whereIn('provider', $connectedProviders);
        }

        if ($status === 'replied') {
            $query->whereNotNull('review_reply_text');
        }

        if ($status === 'pending') {
            $query->whereNull('review_reply_text');
        }

        if ($status === 'ai_replied') {
            $query->whereNull('review_reply_text');
        }

        if ($replyType) {
            $query->where('ai_agent_id', $replyType === 'ai_reply' ? '!=' : '=', null);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(reviewer_name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(review_text) LIKE ?', ["%{$search}%"]);
            });
        }

        switch ($sort) {
            case 'oldest':
                $query->orderBy('reviewed_at', 'asc');
                break;
            case 'az':
                $query->orderBy('reviewer_name', 'asc');
                break;
            case 'latest':
            default:
                $query->orderBy('reviewed_at', 'desc');
                break;
        }

        $reviews = $query->paginate($limit);

        $reviews->getCollection()->transform(function ($review) {

            $hasReply = !empty($review->review_reply_text);

            return [
                'provider' => $review->provider,
                'review_id' => $review->provider_review_id,
                'page_id' => $review->page_id,

                'review_text' => $review->review_text,
                'rating' => (int) $review->rating,
                'recommendation_type' => $review->rating >= 4 ? 'positive' : 'negative',

                'reviewer_name' => $review->reviewer_name,

                'reviewer_avatar' => $review->reviewer_image
                    ?? 'https://ui-avatars.com/api/?name=' . urlencode($review->reviewer_name),

                'created_time' => \Carbon\Carbon::parse(
                    $review->reviewed_at ?? $review->created_at
                )->format('Y-m-d H:i:s'),

                'reply_status' => $hasReply ? 'replied' : 'pending',

                'replies' => $hasReply ? [[
                    'reply_id' => $review->review_reply_id,
                    'reply_text' => $review->review_reply_text,
                    'created_time' => \Carbon\Carbon::parse($review->replied_at)->format('Y-m-d H:i:s'),
                    'reply_type' => $review->ai_agent_id ? 'ai_reply' : 'manual_reply',
                ]] : [],
            ];
        });

        return response()->json($reviews);
    }


    public function reply(Request $request)
    {
        $request->validate([
            'provider'   => 'required|in:facebook,google',
            'review_id'  => 'required|string',
            'page_id'    => 'required|string',
            'comment'    => 'required|string|max:4000',
            'reply_type' => 'nullable|in:ai_reply,manual_reply',
        ]);

        $provider  = $request->provider;
        $reviewId  = $request->review_id;
        $pageId    = $request->page_id;
        $comment   = $request->comment;
        $replyType = $request->reply_type ?? 'manual_reply';

        $status = $replyType === 'ai_reply' ? 'ai_replied' : 'replied';


        UserBusinessAccount::where('user_id', auth()->id())
            ->where('provider', $provider)
            ->where('provider_account_id', $pageId)
            ->where('status', 'connected')
            ->firstOrFail();

        GetReview::where('provider', $provider)
            ->where('provider_review_id', $reviewId)
            ->where('page_id', $pageId)
            ->firstOrFail();



        GetReview::where('provider', $provider)
            ->where('provider_review_id', $reviewId)
            ->where('page_id', $pageId)
            ->update([
                'status'            => 'replied',
                'review_reply_text' => $comment,
                'replied_at'        => now()
            ]);



        ReviewReplyJob::dispatch(
            $provider,
            $reviewId,
            $pageId,
            $comment,
            $replyType,
            auth()->id()
        );



        return response()->json([
            'success' => true,
            'message' => 'Reply is processing in background',
            'status'  => 'queued'
        ]);
    }


    public function deleteReply(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:facebook,google',
            'page_id'  => 'required|string',
            'reply_id' => 'required|string',
        ]);

        GetReview::where('provider', $request->provider)
            ->where('page_id', $request->page_id)
            ->where('review_reply_id', $request->reply_id)
            ->update([
                'review_reply_id'   => null,
                'review_reply_text' => null,
                'replied_at'        => null,
                'status'            => 'pending',
                'ai_agent_id'       => null,
            ]);

        ReviewReplyDeleteJob::dispatch(
            $request->provider,
            $request->page_id,
            $request->reply_id,
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => 'Delete request queued',
        ]);
    }


    public function generate_ai_reply($id)
    {

        $review = GetReview::where('user_id', Auth::id())
            ->where('provider_review_id', $id)
            ->first();


        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
            ], 404);
        }

        $genaratedReply = $this->generateReply($review->review_text);

        return response()->json([
            'success' => true,
            'reply' => $genaratedReply,
            'message' => 'AI reply generated',
        ]);
    }


    protected function generateReply(string $reviewText, string $aiAgentContent = ''): string
    {
        try {
            $response = Groq::chat()->completions()->create([
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Instructions: {$aiAgentContent}
                        Review: {$reviewText}

                        Note:
                        - Do NOT include any personal information such as names, addresses, emails, or phone numbers.
                        - Only generate polite, professional feedback based on the review.
                        - Make sure the reply is in the SAME language as the review.
                        - Keep the reply between 20 to 40 words.
                        - Output ONLY the reply."
                    ],
                ],
            ]);

            $replyText = $response['choices'][0]['message']['content']
                ?? 'Thank you for your review! We appreciate your feedback.';

            return trim($replyText);
        } catch (\Exception $e) {
            return 'Thank you for your review! We appreciate your feedback.';
        }
    }

}
