<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RecordsOperationalActivity;
use App\Models\ResidentComplaint;
use App\Models\User;
use App\Models\UserNotification;
use App\Rules\SecureUploadedFile;
use App\Services\SecureUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ResidentComplaintController extends Controller
{
    use RecordsOperationalActivity;

    public function __construct(
        private readonly SecureUploadService $secureUploads
    ) {
    }

    public function index(Request $request): View
{
    Gate::authorize('viewAny', ResidentComplaint::class);

    $user = $request->user();
    $role = strtolower(trim((string) $user->role));

    $complaints = ResidentComplaint::query()
        ->with('resident')
        ->when($user->isResident(), function ($query) use ($user) {
            /*
            |--------------------------------------------------------------------------
            | Resident record isolation
            |--------------------------------------------------------------------------
            |
            | Residents receive only their own complaint records from the
            | database query. Other complaints are never loaded into memory.
            |
            */

            $query->where('resident_id', $user->id);
        })
        ->latest('submitted_at')
        ->latest()
        ->paginate(10)
        ->withQueryString();

    return view('resident-complaints.index', [
        'complaints' => $complaints,
        'canCreateComplaint' => Gate::allows(
            'create',
            ResidentComplaint::class
        ),
        'canManageComplaints' => $user->isAdmin()
            || $user->isOfficial(),
    ]);
}

    public function create(Request $request): View
{
    Gate::authorize('create', ResidentComplaint::class);

    return view('resident-complaints.create', [
        'user' => $request->user(),
    ]);
}

    public function store(
        Request $request
    ): RedirectResponse {
        Gate::authorize(
            'create',
            ResidentComplaint::class
        );

        $user = $request->user();

        $validated = $request->validate([
            'complainant_name' => [
                'required',
                'string',
                'max:255',
            ],
            'contact_number' => [
                'nullable',
                'string',
                'max:30',
                'regex:/^[0-9+()\-.\s]*$/',
            ],
            'complaint_address' => [
                'required',
                'string',
                'max:500',
            ],
            'complaint_description' => [
                'required',
                'string',
                'max:3000',
            ],
            'evidence' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                new SecureUploadedFile(
                    'complaint_evidence'
                ),
            ],
        ], [
            'complainant_name.required' =>
                'Please enter the complainant full name.',

            'complaint_address.required' =>
                'Please enter the address of the complaint.',

            'complaint_description.required' =>
                'Please describe the complaint.',

            'evidence.image' =>
                'The evidence attachment must be an image.',

            'evidence.mimes' =>
                'The evidence picture must be JPG, JPEG, PNG, or WEBP.',

            'evidence.max' =>
                'The evidence picture must not exceed 10MB.',
        ]);

        $evidencePath = null;

        try {
            if ($request->hasFile('evidence')) {
                $evidencePath = $this->secureUploads->store(
                    $request->file('evidence'),
                    'complaint_evidence'
                );
            }

            $complaint = DB::transaction(function () use (
                $user,
                $validated,
                $evidencePath
            ): ResidentComplaint {
                $complaint = ResidentComplaint::create([
                    /*
                    |--------------------------------------------------------------
                    | Server-controlled ownership
                    |--------------------------------------------------------------
                    |
                    | Ownership comes from the authenticated account. The request
                    | cannot select or overwrite resident_id.
                    |
                    */

                    'resident_id' => $user->id,
                    'complainant_name' =>
                        $validated['complainant_name'],
                    'contact_number' =>
                        $validated['contact_number'] ?? null,
                    'complaint_description' =>
                        $validated['complaint_description'],
                    'complaint_address' =>
                        $validated['complaint_address'],
                    'evidence_path' => $evidencePath,
                    'status' => 'submitted',
                    'submitted_at' => now(),
                ]);

                $this->notifyAdminsAndOfficials(
                    $complaint
                );

                return $complaint;
            });
        } catch (Throwable $exception) {
            if ($evidencePath) {
                $this->secureUploads->delete(
                    $evidencePath,
                    [
                        'resident-complaints',
                    ]
                );
            }

            throw $exception;
        }

        $this->recordOperationalActivity(
            event: 'complaint.created',
            category: 'complaint',
            description: 'A resident complaint was submitted.',
            metadata: [
                'complaint_id' => (int) $complaint->id,
                'status' => $complaint->status,
                'evidence_attached' => $evidencePath !== null,
            ],
            request: $request,
        );

        return redirect()
            ->to(
                $this->complaintIndexUrl(
                    $user
                )
            )
            ->with(
                'success',
                'Complaint submitted successfully.'
            );
    }

    public function show(
    Request $request,
    ResidentComplaint $residentComplaint
): View {
    Gate::authorize('view', $residentComplaint);

    return view('resident-complaints.show', [
        'complaint' => $residentComplaint->load('resident'),
        'canManageComplaints' => Gate::allows(
            'update',
            $residentComplaint
        ),
        'statuses' => $this->statuses(),
        'proofs' => $this->complaintProofs($residentComplaint),
    ]);
}

    public function updateStatus(
    Request $request,
    ResidentComplaint $residentComplaint
): RedirectResponse {
    Gate::authorize('update', $residentComplaint);

    $validated = $request->validate([
        'status' => [
            'required',
            Rule::in(array_keys($this->statuses())),
        ],
    ]);

    $previousStatus = null;

    DB::transaction(function () use (
        $residentComplaint,
        $validated,
        &$previousStatus
    ): void {
        $lockedComplaint = ResidentComplaint::query()
            ->whereKey(
                $residentComplaint->id
            )
            ->lockForUpdate()
            ->firstOrFail();

        Gate::authorize(
            'update',
            $lockedComplaint
        );

        $previousStatus = (string) $lockedComplaint->status;

        $lockedComplaint->update([
            'status' => $validated['status'],
        ]);
    });

    $residentComplaint->refresh();

    $this->notifyResidentStatusUpdated($residentComplaint);

    $this->recordOperationalActivity(
        event: 'complaint.status_updated',
        category: 'complaint',
        description: 'A resident complaint status was updated.',
        metadata: [
            'complaint_id' => (int) $residentComplaint->id,
            'previous_status' => $previousStatus,
            'new_status' => $residentComplaint->status,
        ],
        request: $request,
    );

    return back()->with(
        'success',
        'Complaint status updated successfully.'
    );
}

    public function storeProof(
        Request $request,
        ResidentComplaint $residentComplaint
    ): RedirectResponse {
        Gate::authorize(
            'update',
            $residentComplaint
        );

        $user = $request->user();

        if (! Schema::hasTable('resident_complaint_proofs')) {
            return back()->with(
                'error',
                'Complaint proof table is missing. Please run the migration first.'
            );
        }

        $validated = $request->validate([
            'proof_picture' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                new SecureUploadedFile(
                    'complaint_proof'
                ),
            ],
            'proof_note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ], [
            'proof_picture.required' =>
                'Please attach a proof picture.',

            'proof_picture.image' =>
                'The proof must be an image.',

            'proof_picture.mimes' =>
                'The proof picture must be JPG, JPEG, PNG, or WEBP.',

            'proof_picture.max' =>
                'The proof picture must not exceed 10MB.',
        ]);

        $proofPath = null;

        try {
            $proofPath = $this->secureUploads->store(
                $request->file('proof_picture'),
                'complaint_proof'
            );

            DB::transaction(function () use (
                $residentComplaint,
                $user,
                $validated,
                $proofPath
            ): void {
                DB::table(
                    'resident_complaint_proofs'
                )->insert([
                    'resident_complaint_id' =>
                        $residentComplaint->id,
                    'uploaded_by' => $user->id,
                    'proof_path' => $proofPath,
                    'proof_note' =>
                        $validated['proof_note'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->notifyResidentProofUploaded(
                    $residentComplaint
                );
            });
        } catch (Throwable $exception) {
            if ($proofPath) {
                $this->secureUploads->delete(
                    $proofPath,
                    [
                        'resident-complaints/proofs',
                    ]
                );
            }

            throw $exception;
        }

        $this->recordOperationalActivity(
            event: 'complaint.proof_uploaded',
            category: 'complaint',
            description: 'A proof image was uploaded for a resident complaint.',
            metadata: [
                'complaint_id' =>
                    (int) $residentComplaint->id,
                'proof_note_provided' =>
                    ! empty($validated['proof_note']),
            ],
            request: $request,
        );

        return back()->with(
            'success',
            'Proof picture sent to resident successfully.'
        );
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
        ->find((int) $proofRecord->resident_complaint_id);

    if (! $complaint) {
        abort(404, 'Related complaint not found.');
    }

    /*
    |--------------------------------------------------------------------------
    | Proof ownership authorization
    |--------------------------------------------------------------------------
    |
    | The proof route is shared by authenticated roles, so the related
    | complaint must be authorized before its file path is accessed.
    |
    */

    Gate::authorize('view', $complaint);

    return $this->servePublicStorageFile(
        (string) $proofRecord->proof_path,
        'Proof picture file is missing.'
    );
}

    public function destroy(
        Request $request,
        ResidentComplaint $residentComplaint
    ): RedirectResponse {
        Gate::authorize(
            'delete',
            $residentComplaint
        );

        $user = $request->user();

        $auditMetadata = [
            'complaint_id' =>
                (int) $residentComplaint->id,
            'status' => $residentComplaint->status,
            'resident_id' =>
                $residentComplaint->resident_id,
            'evidence_attached' =>
                ! empty(
                    $residentComplaint->evidence_path
                ),
        ];

        /*
        |--------------------------------------------------------------------------
        | Commit database deletion before deleting physical files
        |--------------------------------------------------------------------------
        */

        $filePaths = $this->complaintProofPaths(
            $residentComplaint
        );

        if ($residentComplaint->evidence_path) {
            $filePaths[] =
                $residentComplaint->evidence_path;
        }

        DB::transaction(function () use (
            $residentComplaint
        ): void {
            $this->deleteComplaintNotifications(
                $residentComplaint
            );

            $this->deleteComplaintProofRows(
                $residentComplaint
            );

            $residentComplaint->delete();
        });

        $this->secureUploads->deleteMany(
            $filePaths,
            [
                'resident-complaints',
            ]
        );

        $this->recordOperationalActivity(
            event: 'complaint.deleted',
            category: 'complaint',
            description: 'A resident complaint was deleted.',
            metadata: $auditMetadata,
            request: $request,
        );

        return redirect()
            ->to(
                $this->complaintIndexUrl(
                    $user
                )
            )
            ->with(
                'success',
                'Complaint deleted successfully.'
            );
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

    private function complaintProofPaths(
        ResidentComplaint $complaint
    ): array {
        if (
            ! Schema::hasTable(
                'resident_complaint_proofs'
            )
        ) {
            return [];
        }

        return DB::table(
            'resident_complaint_proofs'
        )
            ->where(
                'resident_complaint_id',
                $complaint->id
            )
            ->pluck('proof_path')
            ->filter(
                fn (mixed $path): bool =>
                    is_scalar($path)
                    && trim((string) $path) !== ''
            )
            ->map(
                fn (mixed $path): string =>
                    (string) $path
            )
            ->unique()
            ->values()
            ->all();
    }

    private function deleteComplaintProofRows(
        ResidentComplaint $complaint
    ): void {
        if (
            ! Schema::hasTable(
                'resident_complaint_proofs'
            )
        ) {
            return;
        }

        DB::table(
            'resident_complaint_proofs'
        )
            ->where(
                'resident_complaint_id',
                $complaint->id
            )
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

    public function evidence(
    Request $request,
    ResidentComplaint $residentComplaint
) {
    Gate::authorize('view', $residentComplaint);

    $path = $residentComplaint->evidence_path;

    if (
        ! $path
        || str_starts_with((string) $path, 'http')
    ) {
        abort(404, 'Evidence file not found.');
    }

    return $this->servePublicStorageFile(
        (string) $path,
        'Evidence file not found.'
    );
}

    private function servePublicStorageFile(
        string $path,
        string $missingMessage
    ) {
        $storedFile = $this->secureUploads->resolve(
            $path,
            [
                'resident-complaints',
            ],
            [
                'image/jpeg',
                'image/png',
                'image/webp',
            ]
        );

        if (! $storedFile) {
            abort(404, $missingMessage);
        }

        $response = response()->file(
            $storedFile['absolute_path'],
            [
                'Content-Type' =>
                    $storedFile['mime_type'],
                'Content-Disposition' =>
                    'inline; filename="'
                    . basename(
                        $storedFile['path']
                    )
                    . '"',
                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );

        $response->setPrivate();
        $response->headers->addCacheControlDirective(
            'no-store',
            true
        );

        return $response;
    }

    private function cleanPublicStoragePath(string $path): string
    {
        $cleanPath = str_replace('\\', '/', trim($path));
        $cleanPath = preg_replace('#^/?storage/#', '', $cleanPath);
        $cleanPath = preg_replace('#^/?public/#', '', $cleanPath);

        return ltrim((string) $cleanPath, '/');
    }
}
