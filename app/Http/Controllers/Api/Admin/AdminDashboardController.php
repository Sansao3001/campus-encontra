<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminActionResource;
use App\Models\AdminAction;
use App\Models\Claim;
use App\Models\Item;
use App\Models\Report;
use App\Models\User;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $claimCounts = [
            'total' => Claim::count(),
            'pending' => Claim::where('status', Claim::STATUS_PENDING)->count(),
            'accepted' => Claim::where('status', Claim::STATUS_ACCEPTED)->count(),
            'rejected' => Claim::where('status', Claim::STATUS_REJECTED)->count(),
            'cancelled' => Claim::where('status', Claim::STATUS_CANCELLED)->count(),
            'completed' => Claim::where('status', Claim::STATUS_COMPLETED)->count(),
        ];

        return response()->json([
            'users' => [
                'total' => User::count(),
                'admins' => User::admins()->count(),
                'students' => User::where('role', User::ROLE_STUDENT)->count(),
                'suspended' => User::whereNotNull('suspended_at')->count(),
            ],
            'items' => [
                'total' => Item::count(),
                'published' => Item::visible()->count(),
                'moderated' => Item::whereNotNull('moderated_at')->count(),
                'lost' => Item::where('type', 'lost')->count(),
                'found' => Item::where('type', 'found')->count(),
                'open' => Item::where('status', 'open')->count(),
                'pending' => Item::where('status', 'pending')->count(),
                'returned' => Item::where('status', 'returned')->count(),
            ],
            'claims' => $claimCounts,
            'reports' => [
                'total' => Report::count(),
                'pending' => Report::where('status', Report::STATUS_PENDING)->count(),
                'reviewing' => Report::where('status', Report::STATUS_REVIEWING)->count(),
                'resolved' => Report::where('status', Report::STATUS_RESOLVED)->count(),
                'dismissed' => Report::where('status', Report::STATUS_DISMISSED)->count(),
            ],
            'recent_actions' => AdminActionResource::collection(
                AdminAction::with('admin:id,name')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get()
            )->resolve(),
        ]);
    }
}
