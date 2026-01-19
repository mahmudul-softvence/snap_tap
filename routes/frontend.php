<?php

use App\Http\Controllers\Frontend\Auth\RegisterController;
use App\Http\Controllers\Frontend\Auth\SocialiteController;
use App\Http\Controllers\Frontend\FacebookController;
use App\Http\Controllers\Frontend\GmbController;
use App\Http\Controllers\Frontend\GmbMockVersionController;
use App\Http\Controllers\Frontend\BasicSettingController;
use App\Http\Controllers\Frontend\DashboardController;
use App\Http\Controllers\Frontend\MessageTemplateController;
use App\Http\Controllers\Frontend\QrController;
use App\Http\Controllers\Frontend\ReviewReqController;
use App\Http\Controllers\Frontend\UserProfileController;
use App\Http\Controllers\Frontend\GoogleBusinessController;
use App\Http\Controllers\Frontend\ReviewController;
use App\Http\Controllers\Frontend\TwoFactorController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Frontend\PaymentMethodController;
use App\Http\Controllers\Frontend\PlanController;
use App\Http\Controllers\Frontend\SubscriptionController;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;
use LucianoTonet\GroqLaravel\Facades\Groq;

Route::middleware('guest:sanctum')->group(function () {

    Route::controller(SocialiteController::class)->group(function () {
        Route::get('/auth/{provider}/redirect', 'redirect');
        Route::get('/auth/{provider}/callback', 'callback');
    });

    Route::controller(RegisterController::class)->group(function () {
        Route::post('register', 'register');
        Route::post('login', 'login');
    });
    Route::post('/2fa/login', [TwoFactorController::class, 'loginVerify']);

    Route::get('change_review_status/{id}', [ReviewReqController::class, 'change_review_status']);
});


Route::middleware('auth:sanctum')->group(function () {

    // Dashboard/Analytics
    Route::controller(DashboardController::class)->group(function () {
        Route::get('dashboard',  'dashboard');
        Route::get('analytics', 'analytics');
    });


    // Logout/Change pwd
    Route::controller(RegisterController::class)->group(function () {
        Route::post('logout',  'logout');
        Route::post('change_pwd', 'change_pwd');
    });

    // Create Qr
    Route::controller(QrController::class)->group(function () {
        Route::get('get_qr/{provider}', 'get_qr');
        Route::post('store_qr/{provider}', 'store_qr');
    });

    // Message Template
    Route::controller(MessageTemplateController::class)->group(function () {
        Route::get('message_template', 'index');
        Route::post('message_template/create', 'create');
        Route::get('message_template/show/{id}', 'show');
        Route::put('message_template/update/{id}', 'update');
        Route::delete('message_template/delete/{id}', 'destroy');
        Route::get('message_template/default_template', 'default_template');
    });

    // User Profile
    Route::controller(UserProfileController::class)->group(function () {
        Route::get('user-profile/show', 'showProfile');
        Route::put('user-profile/update', 'update');
    });

    // Reviews
    Route::controller(ReviewReqController::class)->group(function () {
        Route::get('review_req', 'index');
        Route::post('review_req/create', 'create');
        Route::put('review_req/update/{id}', 'update');
        Route::get('review_req/show/{id}', 'show');
        Route::delete('review_req/delete/{id}', 'destroy');
    });

    // Basic Settings
    Route::controller(BasicSettingController::class)->group(function () {
        Route::get('settings', 'index');
        Route::put('settings/update', 'update');
    });
});



Route::get('/google/gmb/callback', [GmbController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/google/gmb/auth-url', [GmbController::class, 'authUrl']);
    Route::get('/gmb/accounts', [GmbController::class, 'accounts']);
    Route::get('/gmb/locations/{account}', [GmbController::class, 'locations']);
    Route::get('/gmb/reviews/{location}', [GmbController::class, 'reviews']);
    Route::post('/gmb/reply', [GmbController::class, 'reply']);
});

/// fake GMB routes for testing (Mock Version)
// Route::get('/google/gmb/auth-url', [GmbMockVersionController::class, 'authUrl']);
// Route::get('/google/gmb/callback', [GmbMockVersionController::class, 'callback']);

// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('/gmb/accounts', [GmbMockVersionController::class, 'accounts']);
//     Route::get('/gmb/locations/{account}', [GmbMockVersionController::class, 'locations'])->where('account', '.*');
//     Route::get('/gmb/reviews/{location}', [GmbMockVersionController::class, 'reviews'])->where('location', '.*');
//     Route::post('/gmb/reply', [GmbMockVersionController::class, 'reply']);
// });


Route::middleware('auth:sanctum')->group(function () {
    // Payment Methods
    Route::prefix('payment-methods')->group(function () {
        Route::get('/', [PaymentMethodController::class, 'index']);
        Route::post('/', [PaymentMethodController::class, 'store']);
        Route::put('/{id}/default', [PaymentMethodController::class, 'setDefault']);
        Route::delete('/{id}', [PaymentMethodController::class, 'destroy']);
    });

    // Plans
    Route::get('/plans', [PlanController::class, 'index']);

    // Subscriptions for user
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [SubscriptionController::class, 'show']);
        Route::post('/create-payment-intent', [SubscriptionController::class, 'createPaymentIntent']);
        Route::post('/setup-intent', [SubscriptionController::class, 'createSetupIntent']);
        Route::post('/buynow', [SubscriptionController::class, 'buyNow']);
        Route::post('/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('/start-trial', [SubscriptionController::class, 'startFreeTrial']);
        Route::post('/convert-trial', [SubscriptionController::class, 'convertTrialToPaid']);
        Route::patch('/Change-subscription', [SubscriptionController::class, 'changeSubscription']);
        Route::get('/billing-history', [SubscriptionController::class, 'billingHistory']);
    });
});

// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('/gmb/accounts', [GmbMockVersionController::class, 'accounts']);
//     Route::get('/gmb/locations/{account}', [GmbMockVersionController::class, 'locations'])->where('account', '.*');
//     Route::get('/gmb/reviews/{location}', [GmbMockVersionController::class, 'reviews'])->where('location', '.*');
//     Route::post('/gmb/reply', [GmbMockVersionController::class, 'reply']);
// });

Route::get('/facebook/callback', [FacebookController::class, 'callback']);
// Facebook
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/facebook/auth-url', [FacebookController::class, 'authUrl']);
    Route::post('/facebook/connect-page', [FacebookController::class, 'connectPage']);
    Route::get('/facebook/pages', [FacebookController::class, 'pages']);
    Route::get('/facebook/reviews', [FacebookController::class, 'reviews']);
    Route::post('/facebook/reply', [FacebookController::class, 'reply']);
});


Route::middleware('auth:sanctum')->group(function () {
    // Review List + Filters
    Route::get('/reviews', [ReviewController::class, 'index']);
    // Reply to Review and Delete Reply
    Route::post('/reviews/reply', [ReviewController::class, 'reply']);
    Route::delete('/reviews/reply', [ReviewController::class, 'deleteReply']);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/2fa/setup', [TwoFactorController::class, 'setup']);
    Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm']);
    Route::post('/2fa/disable', [TwoFactorController::class, 'disable']);
});







Route::get('/test-ai-reply', function () {
    $reviewText = "SVD Developers is an excellent and highly professional team. Their work quality, timely delivery, and client support are truly impressive. From the beginning to the end of the project, they communicate clearly and handle every requirement with great attention to detail.
They are responsive, skilled, and very committed to delivering the best results. If you are looking for a reliable and trustworthy development team, SVD Developers is definitely a great choice. Highly recommended....!";

    $model = 'openai/gpt-oss-20b';

    $response = Groq::chat()->completions()->create([
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => $reviewText],
        ]
    ]);

    if (isset($response['choices'][0]['message']['content'])) {
        $replyText = $response['choices'][0]['message']['content'];
    } else {
        $replyText = 'Sorry, there was an issue generating the reply.';
    }

    return response()->json([
        'review' => $reviewText,
        'reply' => $replyText,
    ]);
});
