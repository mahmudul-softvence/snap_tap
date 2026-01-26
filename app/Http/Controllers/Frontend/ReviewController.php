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

class ReviewController extends Controller
{

    public function index(Request $request)
    {
        $platform  = strtolower($request->query('platform', 'both')); // facebook / google / both
        $status    = strtolower($request->query('status', ''));       // replied / pending
        $search    = strtolower($request->query('search', ''));
        $sort      = strtolower($request->query('sort', 'latest'));   // latest / oldest / az
        $limit     = intval($request->query('limit', 10));
        $replyType = $request->query('reply_type');                    // ai_reply / manual_reply

        $query = GetReview::where('user_id', auth()->id());

        if ($platform !== 'both') {
            $query->where('provider', $platform);
        }

        if ($status === 'replied') {
            $query->whereNotNull('review_reply_text');
        }

        if ($status === 'pending') {
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

        // if ($limit > 0) {
        //     $query->limit($limit);
        // }

        // $reviews = $query->get();
        $reviews = $query->paginate($limit); 

        $formatted = $reviews->map(function ($review) {

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

                'created_time' => Carbon::parse(
                    $review->reviewed_at ?? $review->created_at
                )->format('Y-m-d H:i:s'),

                'reply_status' => $hasReply ? 'replied' : 'pending',

                'replies' => $hasReply ? [[
                    'reply_id' => $review->review_reply_id,
                    'reply_text' => $review->review_reply_text,
                    'created_time' => Carbon::parse($review->replied_at)->format('Y-m-d H:i:s'),
                    'reply_type' => $review->ai_agent_id ? 'ai_reply' : 'manual_reply',
                ]] : [],
            ];
        });

        return response()->json([
            'success' => true,
            'total' => $formatted->count(),
            'reviews' => $formatted->values(),
        ]);
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

        if ($provider === 'facebook') {

            $page = UserBusinessAccount::where('user_id', auth()->id())
                ->where('provider', 'facebook')
                ->where('provider_account_id', $pageId)
                ->where('status', 'connected')
                ->firstOrFail();

            $review = GetReview::where('provider', 'facebook')
                ->where('provider_review_id', $reviewId)
                ->where('page_id', $pageId)
                ->firstOrFail();

            try {

                if (!empty($review->review_reply_id)) {
                    Http::withToken($page->access_token)
                        ->delete("https://graph.facebook.com/v24.0/{$review->review_reply_id}");
                }

                $response = Http::withToken($page->access_token)
                    ->post("https://graph.facebook.com/v24.0/{$reviewId}/comments", [
                        'message' => $comment,
                    ]);

                $response->throw();
            } catch (\Illuminate\Http\Client\RequestException $e) {

                $error = $e->response->json() ?? [];

                if (
                    isset($error['error']['code'], $error['error']['error_subcode']) &&
                    $error['error']['code'] == 100 &&
                    $error['error']['error_subcode'] == 33
                ) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This review has already been removed from Facebook',
                    ], 400);
                }

                if (isset($error['error']['code']) && $error['error']['code'] == 190) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Facebook access token expired. Please reconnect your page.',
                    ], 400);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Facebook reply failed',
                    'error'   => $error,
                ], 400);
            }

            $newReplyId = $response->json('id');

            $review->update([
                'review_reply_id'   => $newReplyId,
                'review_reply_text' => $comment,
                'replied_at'        => now(),
                'status'            => $replyType === 'ai_reply' ? 'ai_replied' : 'replied',
                'ai_agent_id'       => $replyType === 'ai_reply' ? auth()->id() : null,
            ]);

            return response()->json([
                'success'    => true,
                'reply_id'   => $newReplyId,
                'reply_text' => $comment,
                'mode'       => 'replaced',
            ]);
        }



        if ($provider === 'google') {

            $account = UserBusinessAccount::where('user_id', auth()->id())
                ->where('provider', 'google')
                ->where('provider_account_id', $pageId)
                ->where('status', 'connected')
                ->firstOrFail();

            $response = Http::withToken($account->access_token)
                ->put("https://mybusiness.googleapis.com/v4/{$reviewId}/reply", [
                    'comment' => $comment,
                ]);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'google_response' => $response->json(),
                ], 400);
            }

            GetReview::where('provider', 'google')
                ->where('provider_review_id', $reviewId)
                ->where('page_id', $pageId)
                ->update([
                    'review_reply_id'   => $reviewId,
                    'review_reply_text' => $comment,
                    'replied_at'        => now(),
                    'status'            => $status,
                    'ai_agent_id'       => $replyType === 'ai_reply' ? auth()->id() : null,
                ]);

            return response()->json([
                'success'  => true,
                'message'  => 'Google review replied & saved',
                'reply_id' => $reviewId,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid provider',
        ], 400);
    }

    public function deleteReply(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:facebook,google',
            'page_id'  => 'required|string',
            'reply_id' => 'required|string',
        ]);

        $provider = $request->provider;
        $pageId   = $request->page_id;
        $replyId  = $request->reply_id;

        if ($provider === 'facebook') {
            $page = UserBusinessAccount::where('user_id', auth()->id())
                ->where('provider', 'facebook')
                ->where('provider_account_id', $pageId)
                ->where('status', 'connected')
                ->firstOrFail();

            try {
                $response = Http::withToken($page->access_token)
                    ->delete("https://graph.facebook.com/v17.0/{$replyId}");

                $response->throw();
            } catch (\Illuminate\Http\Client\RequestException $e) {
                $error = $e->response->json() ?? [];

                if (
                    isset($error['error']['code'], $error['error']['error_subcode'])
                    && $error['error']['code'] == 100
                    && $error['error']['error_subcode'] == 33
                ) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This reply has already been removed from Facebook',
                    ], 400);
                }

                if (isset($error['error']['code']) && $error['error']['code'] == 190) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Facebook access token expired. Please reconnect your page.',
                        'error'   => $error,
                    ], 400);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Facebook reply deletion failed',
                    'error' => $error,
                ], 400);
            }

            ReviewReply::where([
                'provider' => 'facebook',
                'page_id'  => $pageId,
                'reply_id' => $replyId,
                'user_id'  => auth()->id(),
            ])->delete();

            GetReview::where([
                'provider' => 'facebook',
                'page_id'  => $pageId,
                'review_reply_id' => $replyId,
            ])->update([
                'review_reply_id'   => null,
                'review_reply_text' => null,
                'replied_at'        => null,
                'status'            => 'pending',
                'ai_agent_id'       => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Facebook reply deleted successfully',
            ]);
        }

        if ($provider === 'google') {
            $account = UserBusinessAccount::where('user_id', auth()->id())
                ->where('provider', 'google')
                ->where('provider_account_id', $pageId)
                ->where('status', 'connected')
                ->firstOrFail();

            try {
                $response = Http::withToken($account->access_token)
                    ->put("https://mybusiness.googleapis.com/v4/{$replyId}/reply", []);

                $response->throw();
            } catch (\Illuminate\Http\Client\RequestException $e) {
                $error = $e->response->json() ?? [];

                if (isset($error['error']['status']) && $error['error']['status'] === 'NOT_FOUND') {
                    return response()->json([
                        'success' => false,
                        'message' => 'This reply has already been removed from Google',
                    ], 400);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Google reply deletion failed',
                    'error' => $error,
                ], 400);
            }

            ReviewReply::where([
                'provider' => 'google',
                'page_id'  => $pageId,
                'reply_id' => $replyId,
                'user_id'  => auth()->id(),
            ])->delete();

            GetReview::where([
                'provider' => 'google',
                'page_id'  => $pageId,
                'review_reply_id' => $replyId,
            ])->update([
                'review_reply_id'   => null,
                'review_reply_text' => null,
                'replied_at'        => null,
                'status'            => 'pending',
                'ai_agent_id'       => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Google reply deleted successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid provider',
        ], 400);
    }



    // public function reply(Request $request)
    // {
    //     $request->validate([
    //         'provider'   => 'required|in:facebook,google',
    //         'review_id'  => 'required|string',
    //         'page_id'    => 'required|string',
    //         'comment'    => 'required|string|max:4000',
    //         'reply_type' => 'nullable|in:ai_reply,manual_reply',
    //     ]);

    //     $provider  = $request->provider;
    //     $reviewId  = $request->review_id;
    //     $pageId    = $request->page_id;
    //     $comment   = $request->comment;
    //     $replyType = $request->reply_type ?? 'manual_reply';

    //     $status = $replyType === 'ai_reply' ? 'ai_replied' : 'replied';

    //     if ($provider === 'facebook') {
    //         // Fetch connected page
    //         $page = UserBusinessAccount::where('user_id', auth()->id())
    //             ->where('provider', 'facebook')
    //             ->where('provider_account_id', $pageId)
    //             ->where('status', 'connected')
    //             ->firstOrFail();

    //         // Check if a reply already exists
    //         $existingReplyId = GetReview::where('provider', 'facebook')
    //             ->where('provider_review_id', $reviewId)
    //             ->where('page_id', $pageId)
    //             ->value('review_reply_id');

    //         // Determine URL for POST request
    //         if ($existingReplyId) {
    //             // Edit existing reply
    //             $url = "https://graph.facebook.com/v24.0/{$existingReplyId}";
    //         } else {
    //             // Create new reply
    //             $url = "https://graph.facebook.com/v24.0/{$reviewId}/comments";
    //         }

    //         // Send POST request to Facebook
    //         try {
    //             $response = Http::withToken($page->access_token)
    //                 ->post($url, [
    //                     'message' => $comment,
    //                 ]);

    //             $response->throw();
    //         } catch (\Illuminate\Http\Client\RequestException $e) {
    //             $error = $e->response->json() ?? [];

    //             // Handle specific errors
    //             if (
    //                 isset($error['error']['code'], $error['error']['error_subcode'])
    //                 && $error['error']['code'] == 100
    //                 && $error['error']['error_subcode'] == 33
    //             ) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'This review has already been removed from Facebook',
    //                 ], 400);
    //             }

    //             if (isset($error['error']['code']) && $error['error']['code'] == 190) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'Facebook access token expired. Please reconnect your page.',
    //                     'error' => $error,
    //                 ], 400);
    //             }

    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Reply failed',
    //                 'error' => $error,
    //             ], 400);
    //         }

    //         // Use existing reply ID or new ID from response
    //         $fbReplyId = $existingReplyId ?? $response->json('id');

    //         // Update database
    //         GetReview::where('provider', 'facebook')
    //             ->where('provider_review_id', $reviewId)
    //             ->where('page_id', $pageId)
    //             ->update([
    //                 'review_reply_id'   => $fbReplyId,
    //                 'review_reply_text' => $comment,
    //                 'replied_at'        => now(),
    //                 'status'            => $status,
    //                 'ai_agent_id'       => $replyType === 'ai_reply' ? auth()->id() : null,
    //             ]);

    //         return response()->json([
    //             'success'    => true,
    //             'reply_text' => $comment,
    //             'reply_id'   => $fbReplyId,
    //         ]);
    //     }

    //     // Google reply logic (if you have it) can go here...
    // }

















    //-------------------------------------------------------------------------
    // public function index(Request $request)
    // {
    //     $reviews = collect([]);

    //     $platform = strtolower($request->query('platform', 'both')); // facebook / google / both
    //     $status   = strtolower($request->query('status', ''));      // replied / pending
    //     $search   = strtolower($request->query('search', ''));      // search by name or comment
    //     $sort     = strtolower($request->query('sort', 'latest'));  // latest / oldest / az
    //     $limit    = intval($request->query('limit', 10));
    //     $replyTypeFilter = $request->query('reply_type');           // ai_reply / manual_reply

    //     // ====================== Facebook ======================
    //     if ($platform === 'facebook' || $platform === 'both') {
    //         $facebookPages = UserBusinessAccount::where('user_id', auth()->id())
    //             ->where('provider', 'facebook')
    //             ->where('status', 'connected')
    //             ->get();

    //         foreach ($facebookPages as $page) {

    //             $pageInfo = Http::withToken($page->access_token)
    //                 ->get("https://graph.facebook.com/v24.0/{$page->provider_account_id}", [
    //                     'fields' => 'name,picture.type(square)',
    //                 ])->json();

    //             $pageName   = $pageInfo['name'] ?? 'My Facebook Page';
    //             $pageAvatar = $pageInfo['picture']['data']['url'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($pageName);

    //             $response = Http::get(
    //                 "https://graph.facebook.com/v24.0/{$page->provider_account_id}/ratings",
    //                 [
    //                     'fields' => 'reviewer{name,picture},review_text,created_time,recommendation_type,open_graph_story{comments.limit(25){id,from,message,created_time}}',
    //                     'access_token' => $page->access_token,
    //                 ]
    //             )->json();

    //             foreach ($response['data'] ?? [] as $review) {

    //                 $pageReplies = [];

    //                 if (!empty($review['open_graph_story']['comments']['data'])) {
    //                     foreach ($review['open_graph_story']['comments']['data'] as $comment) {

    //                         if (($comment['from']['id'] ?? null) == $page->provider_account_id) {

    //                             $dbReply = ReviewReply::where('review_id', $review['open_graph_story']['id'])
    //                                 ->where('page_id', $page->provider_account_id)
    //                                 ->where('comment', $comment['message'] ?? '')
    //                                 ->first();

    //                             if ($replyTypeFilter && (!isset($dbReply) || $dbReply->reply_type !== $replyTypeFilter)) {
    //                                 continue;
    //                             }

    //                             $pageReplies[] = [
    //                                 'reply_id' => $comment['id'],
    //                                 'reply_text' => $comment['message'] ?? '',
    //                                 'created_time' => Carbon::parse($comment['created_time'] ?? now())->format('Y-m-d H:i:s'),
    //                                 'replier_name' => $pageName,
    //                                 'replier_avatar' => $pageAvatar,
    //                                 'reply_type' => $dbReply->reply_type ?? null,
    //                             ];
    //                         }
    //                     }
    //                 }

    //                 $reviewerName = $review['reviewer']['name'] ?? 'Facebook User';
    //                 $rating = (($review['recommendation_type'] ?? 'positive') === 'negative') ? 1 : 5;

    //                 $reviews->push([
    //                     'provider' => 'facebook',
    //                     'review_id' => $review['open_graph_story']['id'] ?? null,
    //                     'page_id' => $page->provider_account_id,
    //                     'review_text' => $review['review_text'] ?? '',
    //                     'rating' => $rating,
    //                     'recommendation_type' => $review['recommendation_type'] ?? 'positive',
    //                     'reviewer_name' => $reviewerName,
    //                     'reviewer_avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($reviewerName) . '&background=0d6efd&color=fff',
    //                     'created_time' => Carbon::parse($review['created_time'] ?? now())->format('Y-m-d H:i:s'),
    //                     'reply_status' => count($pageReplies) ? 'replied' : 'pending',
    //                     'replies' => $pageReplies,
    //                 ]);
    //             }
    //         }
    //     }

    //     // ====================== Google ======================
    //     if ($platform === 'google' || $platform === 'both') {
    //         $googleAccounts = UserBusinessAccount::where('user_id', auth()->id())
    //             ->where('provider', 'google')
    //             ->where('status', 'connected')
    //             ->get();

    //         foreach ($googleAccounts as $account) {
    //             $response = Http::withToken($account->access_token)
    //                 ->get("https://mybusiness.googleapis.com/v4/{$account->provider_account_id}/reviews")
    //                 ->json();

    //             foreach ($response['reviews'] ?? [] as $review) {
    //                 $reviewerName = $review['reviewer']['displayName'] ?? 'Google User';
    //                 $replyText = $review['reviewReply']['comment'] ?? null;

    //                 $dbReply = ReviewReply::where('review_id', $review['name'] ?? '')
    //                     ->where('page_id', $account->provider_account_id)
    //                     ->first();

    //                 if ($replyTypeFilter && (!isset($dbReply) || $dbReply->reply_type !== $replyTypeFilter)) {
    //                     continue; // skip
    //                 }

    //                 $reviews->push([
    //                     'provider' => 'google',
    //                     'review_id' => $review['name'] ?? null,
    //                     'page_id' => $account->provider_account_id,
    //                     'review_text' => $review['comment'] ?? '',
    //                     'rating' => $review['starRating'] ?? null,
    //                     'recommendation_type' => ($review['starRating'] ?? 0) >= 4 ? 'positive' : 'negative',
    //                     'reviewer_name' => $reviewerName,
    //                     'reviewer_avatar' => $review['reviewer']['profilePhotoUrl'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($reviewerName) . '&background=dc3545&color=fff',
    //                     'created_time' => isset($review['createTime']) ? Carbon::parse($review['createTime'])->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'),
    //                     'reply_status' => $replyText ? 'replied' : 'pending',
    //                     'reply_text' => $replyText,
    //                     'reply_type' => $dbReply->reply_type ?? null,
    //                 ]);
    //             }
    //         }
    //     }

    //     if ($search) {
    //         $reviews = $reviews->filter(fn($item) => str_contains(strtolower($item['review_text']), $search) || str_contains(strtolower($item['reviewer_name']), $search));
    //     }

    //     if ($status === 'replied') {
    //         $reviews = $reviews->filter(fn($item) => $item['reply_status'] === 'replied');
    //     } elseif ($status === 'pending') {
    //         $reviews = $reviews->filter(fn($item) => $item['reply_status'] === 'pending');
    //     }

    //     switch ($sort) {
    //         case 'oldest':
    //             $reviews = $reviews->sortBy('created_time');
    //             break;
    //         case 'az':
    //             $reviews = $reviews->sortBy(fn($r) => mb_strtolower($r['reviewer_name']));
    //             break;
    //         case 'latest':
    //         default:
    //             $reviews = $reviews->sortByDesc('created_time');
    //             break;
    //     }

    //     $reviews = $reviews->values();

    //     if ($limit > 0) {
    //         $reviews = $reviews->take($limit);
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'total' => $reviews->count(),
    //         'reviews' => $reviews,
    //     ]);
    // }


    // public function reply(Request $request)
    // {
    //     $request->validate([
    //         'provider' => 'required|string|in:facebook,google',
    //         'review_id' => 'required|string',
    //         'page_id' => 'required|string',
    //         'comment' => 'required|string|max:4000',
    //         'parent_comment_id' => 'nullable|string',
    //         'reply_type' => 'nullable|in:ai_reply,manual_reply',
    //     ]);

    //     $provider  = $request->provider;
    //     $reviewId  = $request->review_id;
    //     $pageId    = $request->page_id;
    //     $comment   = $request->comment;
    //     $parentId  = $request->parent_comment_id;
    //     $replyType = $request->reply_type ?? 'manual_reply';

    //     if ($provider === 'facebook') {

    //         $page = UserBusinessAccount::where('user_id', auth()->id())
    //             ->where('provider', 'facebook')
    //             ->where('provider_account_id', $pageId)
    //             ->where('status', 'connected')
    //             ->firstOrFail();

    //         $targetId = $parentId ?: $reviewId;

    //         $response = Http::withToken($page->access_token)
    //             ->post("https://graph.facebook.com/v17.0/{$targetId}/comments", [
    //                 'message' => $comment,
    //             ]);

    //         if ($response->failed()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'facebook_response' => $response->json(),
    //             ], 400);
    //         }

    //         $fbReplyId = $response->json('id');

    //         if (is_null($parentId) && $fbReplyId) {
    //             ReviewReply::create([
    //                 'user_id'   => auth()->id(),
    //                 'provider'  => 'facebook',
    //                 'page_id'   => $pageId,
    //                 'review_id' => $reviewId,
    //                 'reply_id'  => $fbReplyId,
    //                 'reply_type' => $replyType,
    //                 'comment'   => $comment,
    //             ]);
    //         }

    //         return response()->json([
    //             'success' => true,
    //             'message' => $parentId
    //                 ? 'Facebook child reply sent successfully'
    //                 : 'Facebook review reply sent & stored',
    //             'reply_id' => $fbReplyId,
    //         ]);
    //     }

    //     if ($provider === 'google') {

    //         $account = UserBusinessAccount::where('user_id', auth()->id())
    //             ->where('provider', 'google')
    //             ->where('provider_account_id', $pageId)
    //             ->where('status', 'connected')
    //             ->firstOrFail();

    //         $response = Http::withToken($account->access_token)
    //             ->put("https://mybusiness.googleapis.com/v4/{$reviewId}/reply", [
    //                 'comment' => $comment,
    //             ]);

    //         if ($response->failed()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'google_response' => $response->json(),
    //             ], 400);
    //         }

    //         ReviewReply::updateOrCreate(
    //             [
    //                 'provider'  => 'google',
    //                 'page_id'   => $pageId,
    //                 'review_id' => $reviewId,
    //             ],
    //             [
    //                 'user_id'   => auth()->id(),
    //                 'reply_id'  => $reviewId,
    //                 'reply_type' => $replyType,
    //                 'comment'   => $comment,
    //             ]
    //         );

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Google review replied & stored',
    //             'reply_id' => $reviewId,
    //         ]);
    //     }

    //     return response()->json([
    //         'success' => false,
    //         'message' => 'Invalid provider',
    //     ], 400);
    // }


    // public function deleteReply(Request $request)
    // {
    //     $request->validate([
    //         'provider' => 'required|in:facebook,google',
    //         'page_id'  => 'required|string',
    //         'reply_id' => 'required|string',
    //     ]);

    //     $provider = $request->provider;
    //     $pageId   = $request->page_id;
    //     $replyId  = $request->reply_id;

    //     if ($provider === 'facebook') {

    //         $page = UserBusinessAccount::where('user_id', auth()->id())
    //             ->where('provider', 'facebook')
    //             ->where('provider_account_id', $pageId)
    //             ->where('status', 'connected')
    //             ->firstOrFail();

    //         $response = Http::withToken($page->access_token)
    //             ->delete("https://graph.facebook.com/v17.0/{$replyId}");

    //         if ($response->failed()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'facebook_response' => $response->json(),
    //             ], 400);
    //         }

    //         ReviewReply::where([
    //             'provider' => 'facebook',
    //             'page_id'  => $pageId,
    //             'reply_id' => $replyId,
    //             'user_id'  => auth()->id(),
    //         ])->delete();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Facebook reply deleted securely',
    //         ]);
    //     }

    //     if ($provider === 'google') {

    //         $account = UserBusinessAccount::where('user_id', auth()->id())
    //             ->where('provider', 'google')
    //             ->where('provider_account_id', $pageId)
    //             ->where('status', 'connected')
    //             ->firstOrFail();

    //         $response = Http::withToken($account->access_token)
    //             ->put("https://mybusiness.googleapis.com/v4/{$replyId}/reply", []);

    //         if ($response->failed()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'google_response' => $response->json(),
    //             ], 400);
    //         }

    //         ReviewReply::where([
    //             'provider' => 'google',
    //             'page_id'  => $pageId,
    //             'reply_id' => $replyId,
    //             'user_id'  => auth()->id(),
    //         ])->delete();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Google reply deleted securely',
    //         ]);
    //     }

    //     return response()->json([
    //         'success' => false,
    //         'message' => 'Invalid provider',
    //     ], 400);
    // }

    //-------------------------------------------------------------------------
}
