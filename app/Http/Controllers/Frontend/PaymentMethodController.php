<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentMethodController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $paymentMethods = $user->paymentMethods();
            
            $formattedMethods = [];
            
            foreach ($paymentMethods as $method) {
                $formattedMethods[] = [
                    'id' => $method->id,
                    'type' => $method->type,
                    'card' => [
                        'brand' => $method->card->brand,
                        'last4' => $method->card->last4,
                        'exp_month' => $method->card->exp_month,
                        'exp_year' => $method->card->exp_year,
                    ],
                    'is_default' => $user->defaultPaymentMethod()?->id === $method->id,
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $formattedMethods
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment methods',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|string',
            'set_as_default' => 'boolean',
        ]);
        
        try {
            $user = $request->user();
            $paymentMethod = $user->addPaymentMethod($request->payment_method);
            
            if ($request->boolean('set_as_default')) {
                $user->updateDefaultPaymentMethod($request->payment_method);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Payment method added successfully',
                'data' => [
                    'id' => $paymentMethod->id,
                    'type' => $paymentMethod->type,
                    'card' => [
                        'brand' => $paymentMethod->card->brand,
                        'last4' => $paymentMethod->card->last4,
                    ],
                    'is_default' => $user->defaultPaymentMethod()?->id === $paymentMethod->id,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function setDefault(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            $user->updateDefaultPaymentMethod($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Default payment method updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update default payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            $paymentMethod = $user->findPaymentMethod($id);
            
            if (!$paymentMethod) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment method not found'
                ], 404);
            }
            
            if ($user->subscribed('default') && count($user->paymentMethods()) === 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete the only payment method while having an active subscription'
                ], 400);
            }
            
            $paymentMethod->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Payment method deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payment method',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}