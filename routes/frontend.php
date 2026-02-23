<?php

use App\Http\Controllers\Frontend\AiAgentController;
use App\Http\Controllers\Frontend\Auth\ForgotPasswordController;
use App\Http\Controllers\Frontend\Auth\RegisterController;
use App\Http\Controllers\Frontend\Auth\SocialiteController;
use App\Http\Controllers\Frontend\FacebookController;
use App\Http\Controllers\Frontend\GmbController;
use App\Http\Controllers\Frontend\BasicSettingController;
use App\Http\Controllers\Frontend\DashboardController;
use App\Http\Controllers\Frontend\MessageTemplateController;
use App\Http\Controllers\Frontend\NotificationController;
use App\Http\Controllers\Frontend\QrController;
use App\Http\Controllers\Frontend\ReviewReqController;
use App\Http\Controllers\Frontend\UserProfileController;
use App\Http\Controllers\Frontend\ReviewController;
use App\Http\Controllers\Frontend\TwoFactorController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Frontend\PaymentMethodController;
use App\Http\Controllers\Frontend\PlanController;
use App\Http\Controllers\Frontend\SubscriptionController;


Route::controller(ForgotPasswordController::class)->group(function () {
    Route::post('forgot-password', 'forgotPassword')->middleware('throttle:forgot-password');
    Route::post('verify-otp', 'verifyOtp');
    Route::post('resend-otp', 'resendOtp')->middleware('throttle:resend-otp');
    Route::post('reset-password', 'resetPassword');
});

Route::controller(SocialiteController::class)->group(function () {
    Route::get('/auth/{provider}/redirect', 'redirect');
    Route::get('/auth/{provider}/callback', 'callback');
});

Route::controller(RegisterController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::get('auth/verify_email/{id}/{hash}', 'verify_email')
        ->middleware('signed')->name('verification.verify');
    Route::post('auth/resend_verification', 'resend_verification');
});

Route::post('/2fa/login', [TwoFactorController::class, 'loginVerify']);
//Email 2FA OTP sent
Route::post('/2fa/email/send', [TwoFactorController::class, 'sendEmailCode']);

Route::get('change_review_status/{id}', [ReviewReqController::class, 'change_review_status']);



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
        Route::get('auth/me', 'authMe');
        Route::get('user-profile/show', 'showProfile');
        Route::put('user-profile/update', 'update');
        Route::get('user-profile/integration', 'integration');
        Route::put('user-profile/business-account/status', 'toggleIntegrationStatus');
    });

    // Reviews
    Route::controller(ReviewReqController::class)->group(function () {
        Route::get('review_req', 'index');
        Route::post('review_req/create', 'create');
        Route::put('review_req/update/{id}', 'update');
        Route::get('review_req/show/{id}', 'show');
        Route::delete('review_req/delete/{id}', 'destroy');
    });

    // Ai Agent Routes
    Route::controller(AiAgentController::class)->group(function () {
        Route::get('ai_agents', 'index');
        Route::post('ai_agents/create', 'create');
        Route::put('ai_agents/update/{id}', 'update');
        Route::get('ai_agents/show/{id}', 'show');
        Route::delete('ai_agents/delete/{id}', 'destroy');
    });

    // Basic Settings
    Route::controller(BasicSettingController::class)->group(function () {
        Route::get('settings', 'index');
        Route::put('settings/update', 'update');
    });
});



Route::get('/google/gmb/callback', [GmbController::class, 'callback']);

Route::middleware('auth:sanctum', 'hasSubscription')->group(function () {
    Route::get('/google/gmb/auth-url', [GmbController::class, 'authUrl']);
    Route::get('/gmb/accounts', [GmbController::class, 'accounts']);
    Route::get('/gmb/locations/{account}', [GmbController::class, 'locations']);
    Route::get('/gmb/reviews/{location}', [GmbController::class, 'reviews']);
    Route::post('/gmb/reply', [GmbController::class, 'reply']);


    //mock account
    Route::post('/create-mock-google', [GmbController::class, 'createMockGoogle']);
    Route::get('/google-gmb-account', [GmbController::class, 'googleAccounts']);
});


// For Subscription & plan.
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
    Route::get('/plans/single/{id}', [PlanController::class, 'showSinglePlan']);

    // Subscriptions for user
    Route::prefix('subscriptions')->group(function () {
        Route::get('/', [SubscriptionController::class, 'show']);
        Route::post('/create-payment-intent', [SubscriptionController::class, 'createPaymentIntent']);
        Route::post('/setup-intent', [SubscriptionController::class, 'createSetupIntent']);
        Route::post('/buynow', [SubscriptionController::class, 'buyNow']);
        Route::post('/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('/start-trial', [SubscriptionController::class, 'startFreeTrial']);
        Route::post('/convert-trial', [SubscriptionController::class, 'convertTrialToPaid']);
        Route::patch('/change-subscription', [SubscriptionController::class, 'changeSubscription']);
        Route::get('/billing-history', [SubscriptionController::class, 'billingHistory']);
    });
});


Route::get('/facebook/callback', [FacebookController::class, 'callback']);
// Facebook
Route::middleware('auth:sanctum', 'hasSubscription')->group(function () {
    Route::get('facebook/auth-url', [FacebookController::class, 'authUrl']);
    Route::get('facebook/session', [FacebookController::class, 'getSessionData']);
    Route::post('facebook/connect-page', [FacebookController::class, 'connectPage']);
    Route::get('facebook/pages', [FacebookController::class, 'pages']);
    Route::get('facebook/reviews', [FacebookController::class, 'reviews']);
    Route::post('facebook/reply', [FacebookController::class, 'reply']);
});


Route::middleware('auth:sanctum', 'hasSubscription')->group(function () {
    // Review List + Filters
    Route::get('/reviews', [ReviewController::class, 'index']);
    // Reply to Review and Delete Reply
    Route::post('/reviews/reply', [ReviewController::class, 'reply']);
    Route::delete('/reviews/reply-delete', [ReviewController::class, 'deleteReply']);
    Route::post('/reviews/generate-ai-reply/{id}', [ReviewController::class, 'generate_ai_reply']);
});

//authenticator app
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/2fa/setup', [TwoFactorController::class, 'setup']);
    Route::post('/2fa/confirm', [TwoFactorController::class, 'confirm']);
    Route::post('/2fa/disable', [TwoFactorController::class, 'disable']);
});



Route::middleware('auth:sanctum')->group(function () {

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/view-all', [NotificationController::class, 'viewAll']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
});



