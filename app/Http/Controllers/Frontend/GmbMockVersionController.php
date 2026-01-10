<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GmbMockVersionController extends Controller
{
    // ১. অ্যাকাউন্ট লিস্ট (Mock)
    public function accounts()
    {
        return response()->json([
            "accounts" => [
                [
                    "name" => "accounts/112233445566",
                    "accountName" => "My Awesome Business Group",
                    "type" => "PERSONAL",
                    "verificationState" => "VERIFIED",
                    "vettedState" => "VETTED"
                ]
            ]
        ]);
    }

    // ২. লোকেশন লিস্ট (Mock)
    public function locations($account)
    {
        // $account আসবে "accounts/112233445566" ফরম্যাটে
        return response()->json([
            "locations" => [
                [
                    "name" => "locations/loc-998877",
                    "title" => "Dhanmondi Branch - Tech Shop",
                    "storeCode" => "DH-01",
                    "categories" => ["service_establishment"]
                ],
                [
                    "name" => "locations/loc-445566",
                    "title" => "Gulshan Branch - Tech Shop",
                    "storeCode" => "GUL-01",
                    "categories" => ["service_establishment"]
                ]
            ]
        ]);
    }

    // ৩. রিভিউ লিস্ট (Mock)
    public function reviews($location)
    {
        return response()->json([
            "reviews" => [
                [
                    "name" => "accounts/112233445566/locations/loc-998877/reviews/rev-1",
                    "reviewer" => [
                        "displayName" => "Ariful Islam",
                        "profilePhotoUrl" => "https://lh3.googleusercontent.com/a-/ACB..."
                    ],
                    "starRating" => "FIVE",
                    "comment" => "Excellent service! Highly recommended.",
                    "createTime" => "2023-10-25T12:30:00Z",
                    "reviewReply" => null // এখনো রিপ্লাই দেওয়া হয়নি
                ],
                [
                    "name" => "accounts/112233445566/locations/loc-998877/reviews/rev-2",
                    "reviewer" => [
                        "displayName" => "Karim Uddin",
                        "profilePhotoUrl" => "https://lh3.googleusercontent.com/a-/XYZ..."
                    ],
                    "starRating" => "THREE",
                    "comment" => "The shop was okay, but the queue was long.",
                    "createTime" => "2023-10-20T09:15:00Z",
                    "reviewReply" => [
                        "comment" => "Thank you for your feedback, Karim. We are working on it.",
                        "updateTime" => "2023-10-21T10:00:00Z"
                    ]
                ]
            ],
            "averageRating" => 4.5,
            "totalReviewCount" => 2
        ]);
    }

    // ৪. রিপ্লাই দেওয়া (Mock Success)
    public function reply(Request $request)
    {
        $request->validate([
            'review_name' => 'required|string',
            'comment'     => 'required|string|max:4000',
        ]);

        // এখানে আমরা সত্যিকারের API কল না করে সরাসরি সাকসেস পাঠাবো
        return response()->json([
            'success' => true,
            'message' => 'Reply sent successfully (Mocked Response)',
            'data' => [
                'comment' => $request->comment,
                'updateTime' => now()->toIso8601String()
            ]
        ]);
    }
}
