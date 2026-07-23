<?php

namespace App\Http\Controllers;

use App\Models\ResidentComplaint;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
            'complainant_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'complaint_address' => ['required', 'string', 'max:500'],
            'complaint_description' => ['required', 'string', 'max:3000'],
            'evidence' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:51200'],
        ], [
            'complainant_name.required' => 'Please enter the complainant full name.',
            'complaint_address.required' => 'Please enter the address of the complaint.',
            'complaint_description.required' => 'Please describe the complaint.',
            'evidence.image' => 'The evidence attachment must be an image.',
            'evidence.mimes' => 'The evidence picture must be JPG, JPEG, PNG, or WEBP.',
            'evidence.max' => 'The evidence picture must not exceed 50MB.',
        ]);

        $evidencePath = null;

        if ($request->hasFile('evidence')) {
            $evidencePath = $request->file('evidence')->store('resident-complaints', 'public');
        }

        $complaint = ResidentComplaint::create([
            'resident_id' => $user->id,
            'complainant_name' => $validated['complainant_name'],
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
            'proofs' => $this->complaintProofs($residentComplaint),
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

    public function storeProof(Request $request, ResidentComplaint $residentComplaint): RedirectResponse
    {
        $user = $request->user();
        $role = strtolower((string) $user?->role);

        abort_unless(in_array($role, ['admin', 'official', 'dao'], true), 403);

        if (! Schema::hasTable('resident_complaint_proofs')) {
            return back()->with('error', 'Complaint proof table is missing. Please run the migration first.');
        }

        $validated = $request->validate([
            'proof_picture' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:51200'],
            'proof_note' => ['nullable', 'string', 'max:1000'],
        ], [
            'proof_picture.required' => 'Please attach a proof picture.',
            'proof_picture.image' => 'The proof must be an image.',
            'proof_picture.mimes' => 'The proof picture must be JPG, JPEG, PNG, or WEBP.',
            'proof_picture.max' => 'The proof picture must not exceed 50MB.',
        ]);

        $proofPath = $request->file('proof_picture')->store('resident-complaints/proofs', 'public');

        DB::table('resident_complaint_proofs')->insert([
            'resident_complaint_id' => $residentComplaint->id,
            'uploaded_by' => $user->id,
            'proof_path' => $proofPath,
            'proof_note' => $validated['proof_note'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->notifyResidentProofUploaded($residentComplaint);

        return back()->with('success', 'Proof picture sent to resident successfully.');
    }

    public function proofFile(Request $request, int $proof)
    {
        if (! Schema::hasTable('resident_complaint_proofs')) {
            abort(404, 'Complaint proof table not found.');
        }

        $proofRecord = DB::table('resident_complaint_proofs')
            ->where('id', $proof)
            ->first();

        if (! $proofRecord) {
            abort(404, 'Proof picture not found.');
        }

        $complaint = ResidentComplaint::query()
            ->where('id', $proofRecord->resident_complaint_id)
            ->first();

        if (! $complaint) {
            abort(404, 'Related complaint not found.');
        }

        $user = $request->user();
        $role = strtolower((string) $user?->role);

        $canView = in_array($role, ['admin', 'official', 'dao'], true);

        if ($role === 'resident') {
            $canView = (int) $complaint->resident_id === (int) $user->id;
        }

        if (! $canView) {
            abort(403, 'Unauthorized access.');
        }

        return $this->servePublicStorageFile((string) $proofRecord->proof_path, 'Proof picture file is missing.');
    }

    public function destroy(Request $request, ResidentComplaint $residentComplaint): RedirectResponse
    {
        $user = $request->user();
        $role = strtolower((string) $user->role);

        abort_unless(in_array($role, ['admin', 'official', 'dao'], true), 403);

        DB::transaction(function () use ($residentComplaint): void {
            $this->deleteComplaintNotifications($residentComplaint);
            $this->deleteComplaintProofs($residentComplaint);

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

    private function notifyResidentProofUploaded(ResidentComplaint $complaint): void
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
                'title' => 'Complaint proof picture uploaded',
                'message' => 'An admin or official uploaded a proof picture for your complaint.',
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

    private function deleteComplaintProofs(ResidentComplaint $complaint): void
    {
        if (! Schema::hasTable('resident_complaint_proofs')) {
            return;
        }

        $proofs = DB::table('resident_complaint_proofs')
            ->where('resident_complaint_id', $complaint->id)
            ->get(['id', 'proof_path']);

        foreach ($proofs as $proof) {
            $path = $this->cleanPublicStoragePath((string) $proof->proof_path);

            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        DB::table('resident_complaint_proofs')
            ->where('resident_complaint_id', $complaint->id)
            ->delete();
    }

    private function complaintProofs(ResidentComplaint $residentComplaint)
    {
        if (! Schema::hasTable('resident_complaint_proofs')) {
            return collect();
        }

        return DB::table('resident_complaint_proofs')
            ->leftJoin('users', 'users.id', '=', 'resident_complaint_proofs.uploaded_by')
            ->where('resident_complaint_proofs.resident_complaint_id', $residentComplaint->id)
            ->orderByDesc('resident_complaint_proofs.created_at')
            ->select([
                'resident_complaint_proofs.id',
                'resident_complaint_proofs.proof_path',
                'resident_complaint_proofs.proof_note',
                'resident_complaint_proofs.created_at',
                'users.name as uploader_name',
                'users.role as uploader_role',
            ])
            ->get();
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

    public function evidence(Request $request, ResidentComplaint $residentComplaint)
    {
        $user = $request->user();
        $role = strtolower((string) $user->role);

        if ($role === 'resident' && (int) $residentComplaint->resident_id !== (int) $user->id) {
            abort(403);
        }

        if (! in_array($role, ['admin', 'official', 'dao', 'resident'], true)) {
            abort(403);
        }

        $path = $residentComplaint->evidence_path;

        if (! $path || str_starts_with((string) $path, 'http')) {
            abort(404, 'Evidence file not found.');
        }

        return $this->servePublicStorageFile((string) $path, 'Evidence file not found.');
    }

    private function servePublicStorageFile(string $path, string $missingMessage)
    {
        $cleanPath = $this->cleanPublicStoragePath($path);

        if (
            ! $cleanPath
            || str_contains($cleanPath, '..')
            || ! Storage::disk('public')->exists($cleanPath)
        ) {
            abort(404, $missingMessage);
        }

        $absolutePath = Storage::disk('public')->path($cleanPath);

        if (! is_file($absolutePath)) {
            abort(404, $missingMessage);
        }

        $mimeType = @mime_content_type($absolutePath) ?: 'application/octet-stream';

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($cleanPath) . '"',
        ]);
    }

    private function cleanPublicStoragePath(string $path): string
    {
        $cleanPath = str_replace('\\', '/', trim($path));
        $cleanPath = preg_replace('#^/?storage/#', '', $cleanPath);
        $cleanPath = preg_replace('#^/?public/#', '', $cleanPath);

        return ltrim((string) $cleanPath, '/');
    }
}
