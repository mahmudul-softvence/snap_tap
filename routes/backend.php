<?php

use App\Http\Controllers\Frontend\UserProfileController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\AdminPlanController;
use App\Http\Controllers\Backend\AdminSubscriptionController;
use App\Http\Controllers\Backend\UserProfileManageController;

Route::controller(UserProfileController::class)->group(function () {
    Route::put('admin-profile/update', 'adminProfileUpdate')->middleware(['role:super_admin', 'auth:sanctum']);
});

Route::middleware('role:super_admin', 'auth:sanctum')->group(function () {
    Route::get('/admin/settings', [SettingController::class, 'index']);
    Route::post('/admin/settings/update', [SettingController::class, 'updateSettings']);
});

//ADMIN PLAN AND SUBSCRIPTION.
Route::middleware('role:super_admin', 'auth:sanctum')->group(function () {
    Route::get('/adminplan', [AdminPlanController::class, 'index']);
    Route::post('/plan/create', [AdminPlanController::class, 'store']);
    Route::delete('/plan/delete/{id}', [AdminPlanController::class, 'deletePlan']);
    Route::put('/plan/edit/{id}', [AdminPlanController::class, 'editPlan']);
    Route::get('/admin-dashboard', [AdminSubscriptionController::class, 'adminDashboard']);
    Route::get('/admin-subscription-dashboard', [AdminSubscriptionController::class, 'adminSubscriptionDashboard']);
    Route::post('/admin/subscriptions/change-subscription', [AdminSubscriptionController::class, 'changeSubscription']);
    Route::patch('/admin/subscriptions/change/{id}', [AdminSubscriptionController::class, 'changeStatus']);
    Route::delete('/admin/subscriptions/delete/{id}', [AdminSubscriptionController::class, 'deleteSubscription']);
    Route::get('admin/customers', [AdminSubscriptionController::class, 'getCustomerList']);
    Route::get('admin/customers/subscription/{id}', [AdminSubscriptionController::class, 'customerSubscription']);
    Route::get('admin/customers/billing-history/{id}', [AdminSubscriptionController::class, 'billingHistory']);
    Route::get('admin/customers/billing-history', [AdminSubscriptionController::class, 'allBillingHistory']);
});

//user profile details
Route::middleware('role:super_admin', 'auth:sanctum')->group(function () {
    Route::get('admin/ai_agent/{id}', [UserProfileManageController::class, 'user_ai_agents']);


    Route::get('/admin/user-profile-show/{id}', [UserProfileManageController::class, 'userDetailsShow']);
    Route::put('/admin/user-profile-Update/{id}', [UserProfileManageController::class, 'userDetailsUpdate']);
    Route::get('/admin/user-profile-integration/{id}', [UserProfileManageController::class, 'userIntegrationDetails']);
    Route::put('/admin/user-profile-integration-status-update/{id}', [UserProfileManageController::class, 'userIntegrationStatusUpdate']);

    Route::delete('/admin/user-business-account-remove/{id}', [UserProfileManageController::class, 'removeUserProviderAccount']);
});
