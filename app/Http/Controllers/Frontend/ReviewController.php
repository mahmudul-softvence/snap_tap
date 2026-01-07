<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\UserBusinessAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $reviews = collect([]);

        $platform = strtolower($request->query('platform', 'both')); // facebook / google / both
        $status   = strtolower($request->query('status', ''));      // replied / pending
        $search   = strtolower($request->query('search', ''));      // search by name or comment
        $sort     = strtolower($request->query('sort', 'latest'));  // latest / oldest / az
        $limit    = intval($request->query('limit', 10));

        if ($platform === 'facebook' || $platform === 'both') {

            $facebookPages = UserBusinessAccount::where('user_id', auth()->id())
                ->where('provider', 'facebook')
                ->where('status', 'connected')
                ->get();

            foreach ($facebookPages as $page) {

                $pageInfo = Http::withToken($page->access_token)
                    ->get("https://graph.facebook.com/v24.0/{$page->provider_account_id}", [
                        'fields' => 'name,picture.type(square)',
                    ])
                    ->json();

                $pageName   = $pageInfo['name'] ?? 'My Facebook Page';
                $pageAvatar = $pageInfo['picture']['data']['url']
                    ?? 'https://ui-avatars.com/api/?name=' . urlencode($pageName);


                $response = Http::get(
                    "https://graph.facebook.com/v24.0/{$page->provider_account_id}/ratings",
                    [
                        'fields' => 'reviewer,review_text,created_time,recommendation_type,
                        open_graph_story{comments.limit(25){id,from,message,created_time,
                        comments.limit(25){id,from,message,created_time}}}',
                        'access_token' => $page->access_token,
                    ]
                )->json();

                foreach ($response['data'] ?? [] as $review) {



                    $pageReplies = [];

                    if (!empty($review['open_graph_story']['comments']['data'])) {
                        foreach ($review['open_graph_story']['comments']['data'] as $comment) {

                            if (($comment['from']['id'] ?? null) == $page->provider_account_id) {

                                $childReplies = [];

                                if (!empty($comment['comments']['data'])) {
                                    foreach ($comment['comments']['data'] as $child) {
                                        $childReplies[] = [
                                            'reply_id'     => $child['id'],
                                            'reply_text'   => $child['message'] ?? '',
                                            'created_time' => Carbon::parse($child['created_time'] ?? now())->format('Y-m-d H:i:s'),
                                            'replier_name' => $child['from']['name'] ?? 'Facebook User',
                                            'replier_avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($child['from']['name'] ?? 'User')
                                        ];
                                    }
                                }

                                $pageReplies[] = [
                                    'reply_id' => $comment['id'],
                                    'reply_text' => $comment['message'] ?? '',
                                    'created_time' => Carbon::parse($comment['created_time'] ?? now())->format('Y-m-d H:i:s'),
                                    'replier_name' => $pageName,
                                    'replier_avatar' => $pageAvatar,
                                    'replies' => $childReplies,
                                ];
                            }
                        }
                    }

                    $reviewerName = $review['reviewer']['name'] ?? 'Facebook User';
                    $rating = (($review['recommendation_type'] ?? 'positive') === 'negative') ? 1 : 5;

                    $reviews->push([
                        'provider' => 'facebook',

                        'review_id'   => $review['open_graph_story']['id'] ?? null,
                        'page_id'     => $page->provider_account_id,
                        'review_text' => $review['review_text'] ?? '',
                        'rating'      => $rating,
                        'recommendation_type' => $review['recommendation_type'] ?? 'positive',

                        'reviewer_name'   => $reviewerName,
                        'reviewer_avatar' =>
                        'https://ui-avatars.com/api/?name=' .
                            urlencode($reviewerName) .
                            '&background=0d6efd&color=fff',

                        'created_time' => Carbon::parse($review['created_time'] ?? now())
                            ->format('Y-m-d H:i:s'),

                        'reply_status' => count($pageReplies) ? 'replied' : 'pending',
                        'replies'      => $pageReplies,
                    ]);
                }
            }
        }


        if ($platform === 'google' || $platform === 'both') {
            $googleAccounts = UserBusinessAccount::where('user_id', auth()->id())
                ->where('provider', 'google')
                ->where('status', 'connected')
                ->get();

            foreach ($googleAccounts as $account) {
                $response = Http::withToken($account->access_token)
                    ->get("https://mybusiness.googleapis.com/v4/{$account->provider_account_id}/reviews")
                    ->json();

                foreach ($response['reviews'] ?? [] as $review) {
                    $reviewerName = $review['reviewer']['displayName'] ?? 'Google User';
                    $replyText   = $review['reviewReply']['comment'] ?? null;

                    $reviews->push([
                        'provider' => 'google',
                        'review_id' => $review['name'] ?? null,
                        'page_id' => $account->provider_account_id,
                        'review_text' => $review['comment'] ?? '',
                        'rating' => $review['starRating'] ?? null,
                        'recommendation_type' => ($review['starRating'] ?? 0) >= 4 ? 'positive' : 'negative',
                        'reviewer_name' => $reviewerName,
                        'reviewer_avatar' => $review['reviewer']['profilePhotoUrl'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($reviewerName) . '&background=dc3545&color=fff',
                        'created_time' => isset($review['createTime']) ? Carbon::parse($review['createTime'])->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'),
                        'reply_status' => $replyText ? 'replied' : 'pending',
                        'reply_text' => $replyText,
                    ]);
                }
            }
        }

        if ($search) {
            $reviews = $reviews->filter(function ($item) use ($search) {
                return str_contains(strtolower($item['review_text']), $search)
                    || str_contains(strtolower($item['reviewer_name']), $search);
            });
        }

        if ($status === 'replied') {
            $reviews = $reviews->filter(fn($item) => $item['reply_status'] === 'replied');
        } elseif ($status === 'pending') {
            $reviews = $reviews->filter(fn($item) => $item['reply_status'] === 'pending');
        }

        switch ($sort) {
            case 'oldest':
                $reviews = $reviews->sortBy('created_time');
                break;
            case 'az':
                $reviews = $reviews->sortBy(fn($r) => mb_strtolower($r['reviewer_name']));
                break;
            case 'latest':
            default:
                $reviews = $reviews->sortByDesc('created_time');
                break;
        }

        $reviews = $reviews->values();

        if ($limit > 0) {
            $reviews = $reviews->take($limit);
        }

        return response()->json([
            'success' => true,
            'total' => $reviews->count(),
            'reviews' => $reviews,
        ]);
    }


    public function reply(Request $request)
    {
        $request->validate([
            'provider' => 'required|string|in:facebook,google',
            'review_id' => 'required|string',
            'page_id' => 'required|string',
            'comment' => 'required|string|max:4000',
            'parent_comment_id' => 'nullable|string',
        ]);

        $provider = $request->provider;
        $reviewId = $request->review_id;
        $pageId   = $request->page_id;
        $comment  = $request->comment;
        $parentCommentId = $request->parent_comment_id ?? null;

        if ($provider === 'facebook') {

            $page = UserBusinessAccount::where('user_id', auth()->id())
                ->where('provider', 'facebook')
                ->where('provider_account_id', $pageId)
                ->where('status', 'connected')
                ->firstOrFail();

            $targetId = $parentCommentId ?? $reviewId;

            $response = Http::withToken($page->access_token)
                ->post("https://graph.facebook.com/v17.0/{$targetId}/comments", [
                    'message' => $comment,
                ]);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'facebook_response' => $response->json(),
                ], 400);
            }

            $type = $parentCommentId ? 'child reply' : 'review reply';

            return response()->json([
                'success' => true,
                'message' => "Facebook {$type} successfully",
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

            return response()->json([
                'success' => true,
                'message' => 'Google review replied successfully',
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

        if ($request->provider === 'facebook') {

            $page = UserBusinessAccount::where('user_id', auth()->id())
                ->where('provider', 'facebook')
                ->where('provider_account_id', $request->page_id)
                ->where('status', 'connected')
                ->firstOrFail();

            $response = Http::withToken($page->access_token)
                ->delete("https://graph.facebook.com/v17.0/{$request->reply_id}");

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete Facebook reply',
                    'facebook_response' => $response->json(),
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Facebook reply deleted successfully',
            ]);
        }

        if ($request->provider === 'google') {

            $account = UserBusinessAccount::where('user_id', auth()->id())
                ->where('provider', 'google')
                ->where('provider_account_id', $request->page_id)
                ->where('status', 'connected')
                ->firstOrFail();

            $response = Http::withToken($account->access_token)
                ->put("https://mybusiness.googleapis.com/v4/{$request->reply_id}/reply", []);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete Google reply',
                    'google_response' => $response->json(),
                ], 400);
            }

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
}
