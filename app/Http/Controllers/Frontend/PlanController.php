<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{

   public function index(): JsonResponse
    {
        try {
            $plans = Plan::all();
            $start_date = Carbon::now();
            $end_date = $start_date->copy()->addMonth();
            $data = [];

            foreach ($plans as $plan) {
                $data[] = [
                    'id'         => $plan->id,
                    'name'       => $plan->name,
                    'price'      => $plan->price,
                    'start_date' => $start_date->toDateString(),
                    'end_date'   => $end_date->toDateString(),
                    'interval'      => $plan->interval,
                    'trail_days'      => $plan->trial_days,
                    'feautures'      => $plan->features,

                ];
            }

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => count($data),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch plans',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function showSinglePlan(int $id): JsonResponse
    {
        try {
            $plan = Plan::findOrFail($id);
            $start_date = Carbon::now();
            $end_date = $start_date->copy()->addMonth();

                $data[] = [
                    'id'         => $plan->id,
                    'name'       => $plan->name,
                    'price'      => $plan->price,
                    'start_date' => $start_date->toDateString(),
                    'end_date'   => $end_date->toDateString(),
                    'interval'      => $plan->interval,
                    'trail_days'      => $plan->trial_days,
                    'feautures'      => $plan->features,
                ];

            return response()->json([
                'success' => true,
                'data'    => $data,
                'count'   => count($data),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch plans',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

}
