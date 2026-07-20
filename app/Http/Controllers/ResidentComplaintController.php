<?php

namespace App\Http\Controllers;

use App\Models\ResidentComplaint;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResidentComplaintController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $role = strtolower((string) $user->role);

        $complaints = ResidentComplaint::query()
            ->with('resident')
            ->when($role === 'resident', function ($query) use ($user) {
                $query->where('resident_id', $user->id);
            })
            ->latest('submitted_at')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('resident-complaints.index', [
            'complaints' => $complaints,
            'canCreateComplaint' => $role === 'resident',
            'canManageComplaints' => in_array($role, ['admin', 'official', 'dao'], true),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        abort_unless(strtolower((string) $user->role) === 'resident', 403);

        return view('resident-complaints.create', [
            'user' => $user,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless(strtolower((string) $user->role) === 'resident', 403);

        $validated = $request->validate([
            'contact_number' => ['nullable', 'string', 'max:30'],
            'complaint_description' => ['required', 'string', 'max:5000'],
            'complaint_address' => ['required', 'string', 'max:1000'],
            'evidence' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $evidencePath = null;

        if ($request->hasFile('evidence')) {
            $evidencePath = $request->file('evidence')->store('resident-complaints', 'public');
        }

        $complaint = ResidentComplaint::create([
            'resident_id' => $user->id,
            'complainant_name' => $user->name,
            'contact_number' => $validated['contact_number'] ?? null,
            'complaint_description' => $validated['complaint_description'],
            'complaint_address' => $validated['complaint_address'],
            'evidence_path' => $evidencePath,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $this->notifyAdminsAndOfficials($complaint);

        return redirect()
            ->to($this->complaintIndexUrl($user))
            ->with('success', 'Complaint submitted successfully.');
    }

    public function show(Request $request, ResidentComplaint $residentComplaint): View
    {
        $user = $request->user();
        $role = strtolower((string) $user->role);

        if ($role === 'resident' && (int) $residentComplaint->resident_id !== (int) $user->id) {
            abort(403);
        }

        return view('resident-complaints.show', [
            'complaint' => $residentComplaint->load('resident'),
            'canManageComplaints' => in_array($role, ['admin', 'official', 'dao'], true),
            'statuses' => $this->statuses(),
        ]);
    }

    public function updateStatus(Request $request, ResidentComplaint $residentComplaint): RedirectResponse
    {
        $user = $request->user();
        $role = strtolower((string) $user->role);

        abort_unless(in_array($role, ['admin', 'official', 'dao'], true), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
        ]);

        $residentComplaint->update([
            'status' => $validated['status'],
        ]);

        $this->notifyResidentStatusUpdated($residentComplaint);

        return back()->with('success', 'Complaint status updated successfully.');
    }

    public function destroy(Request $request, ResidentComplaint $residentComplaint): RedirectResponse
    {
        $user = $request->user();
        $role = strtolower((string) $user->role);

        abort_unless(in_array($role, ['admin', 'official', 'dao'], true), 403);

        DB::transaction(function () use ($residentComplaint): void {
            $this->deleteComplaintNotifications($residentComplaint);

            if ($residentComplaint->evidence_path) {
                Storage::disk('public')->delete($residentComplaint->evidence_path);
            }

            $residentComplaint->delete();
        });

        return redirect()
            ->to($this->complaintIndexUrl($user))
            ->with('success', 'Complaint deleted successfully.');
    }

    private function notifyAdminsAndOfficials(ResidentComplaint $complaint): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        User::query()
            ->whereIn('role', ['admin', 'official', 'dao'])
            ->select(['id', 'role'])
            ->chunkById(100, function ($users) use ($complaint): void {
                foreach ($users as $user) {
                    UserNotification::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'type' => 'resident_complaint',
                            'source_id' => $complaint->id,
                        ],
                        [
                            'user_id' => $user->id,
                            'type' => 'resident_complaint',
                            'source_id' => $complaint->id,
                            'title' => 'New resident complaint',
                            'message' => $complaint->complainant_name . ' submitted a complaint: ' . mb_substr($complaint->complaint_description, 0, 160),
                            'is_read' => false,
                            'read_at' => null,
                        ]
                    );
                }
            });
    }

    private function notifyResidentStatusUpdated(ResidentComplaint $complaint): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        UserNotification::updateOrCreate(
            [
                'user_id' => $complaint->resident_id,
                'type' => 'resident_complaint_update',
                'source_id' => $complaint->id,
            ],
            [
                'user_id' => $complaint->resident_id,
                'type' => 'resident_complaint_update',
                'source_id' => $complaint->id,
                'title' => 'Complaint status updated',
                'message' => 'Your complaint status is now ' . $complaint->statusLabel() . '.',
                'is_read' => false,
                'read_at' => null,
            ]
        );
    }

    private function deleteComplaintNotifications(ResidentComplaint $complaint): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        UserNotification::query()
            ->where('source_id', $complaint->id)
            ->whereIn('type', [
                'resident_complaint',
                'resident_complaint_update',
            ])
            ->delete();
    }

    private function complaintIndexUrl(User $user): string
    {
        $role = strtolower((string) $user->role);

        $routeName = match ($role) {
            'admin' => Route::has('admin.resident-complaints.index') ? 'admin.resident-complaints.index' : null,
            'official', 'dao' => Route::has('official.resident-complaints.index') ? 'official.resident-complaints.index' : null,
            'resident' => Route::has('resident.resident-complaints.index') ? 'resident.resident-complaints.index' : null,
            default => null,
        };

        return $routeName ? route($routeName) : route('dashboard');
    }

    private function statuses(): array
    {
        return [
            'submitted' => 'Submitted',
            'under_review' => 'Under Review',
            'in_progress' => 'In Progress',
            'resolved' => 'Resolved',
            'rejected' => 'Rejected',
        ];
    }
}