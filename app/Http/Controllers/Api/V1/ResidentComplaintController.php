<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\RecordsOperationalActivity;
use App\Http\Controllers\Controller;
use App\Models\ResidentComplaint;
use App\Models\User;
use App\Models\UserNotification;
use App\Rules\SecureUploadedFile;
use App\Services\SecureUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ResidentComplaintController extends Controller
{
    use RecordsOperationalActivity;

    public function __construct(
        private readonly SecureUploadService $secureUploads
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ResidentComplaint::class);

        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        $complaints = ResidentComplaint::query()
            ->with('resident')
            ->when(
                $user->isResident(),
                fn ($query) => $query->where(
                    'resident_id',
                    $user->id
                )
            )
            ->latest('submitted_at')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return response()
            ->json([
                'data' => $complaints->getCollection()
                    ->map(
                        fn (ResidentComplaint $complaint): array =>
                            $this->serializeListItem($complaint)
                    )
                    ->values()
                    ->all(),

                'options' => [
                    'statuses' => collect($this->statuses())
                        ->map(
                            fn (string $label, string $value): array => [
                                'value' => $value,
                                'label' => $label,
                            ]
                        )
                        ->values()
                        ->all(),
                ],

                'permissions' => [
                    'can_create' => Gate::allows(
                        'create',
                        ResidentComplaint::class
                    ),
                    'can_process' => $user->isAdmin()
                        || $user->isOfficial(),
                    'can_delete' => $user->isAdmin(),
                ],

                'pagination' => [
                    'current_page' => $complaints->currentPage(),
                    'last_page' => $complaints->lastPage(),
                    'per_page' => $complaints->perPage(),
                    'total' => $complaints->total(),
                    'from' => $complaints->firstItem(),
                    'to' => $complaints->lastItem(),
                ],
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', ResidentComplaint::class);

        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Unauthenticated.');

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
                'regex:/^[0-9+()\-.\\s]*$/',
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
                    ['resident-complaints']
                );
            }

            throw $exception;
        }

        $this->recordOperationalActivity(
            event: 'complaint.created',
            category: 'complaint',
            description:
                'A resident complaint was submitted.',
            metadata: [
                'complaint_id' => (int) $complaint->id,
                'status' => $complaint->status,
                'evidence_attached' =>
                    $evidencePath !== null,
            ],
            request: $request,
        );

        $complaint->load('resident');

        return response()
            ->json([
                'message' =>
                    'Complaint submitted successfully.',
                'data' =>
                    $this->serializeComplaint(
                        $complaint
                    ),
            ], 201)
            ->header('Cache-Control', 'no-store, private');
    }

    public function show(
        Request $request,
        ResidentComplaint $residentComplaint
    ): JsonResponse {
        Gate::authorize(
            'view',
            $residentComplaint
        );

        $residentComplaint->load('resident');

        return response()
            ->json([
                'data' => $this->serializeComplaint(
                    $residentComplaint
                ),
                'options' => [
                    'statuses' => collect($this->statuses())
                        ->map(
                            fn (string $label, string $value): array => [
                                'value' => $value,
                                'label' => $label,
                            ]
                        )
                        ->values()
                        ->all(),
                ],
                'permissions' => [
                    'can_update' => Gate::allows(
                        'update',
                        $residentComplaint
                    ),
                    'can_delete' => Gate::allows(
                        'delete',
                        $residentComplaint
                    ),
                ],
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function updateStatus(
        Request $request,
        ResidentComplaint $residentComplaint
    ): JsonResponse {
        Gate::authorize(
            'update',
            $residentComplaint
        );

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in(
                    array_keys(
                        $this->statuses()
                    )
                ),
            ],
        ]);

        $previousStatus = null;

        DB::transaction(function () use (
            $residentComplaint,
            $validated,
            &$previousStatus
        ): void {
            $lockedComplaint =
                ResidentComplaint::query()
                    ->whereKey(
                        $residentComplaint->id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            Gate::authorize(
                'update',
                $lockedComplaint
            );

            $previousStatus =
                (string) $lockedComplaint->status;

            $lockedComplaint->update([
                'status' =>
                    $validated['status'],
            ]);
        });

        $residentComplaint->refresh();
        $residentComplaint->load('resident');

        $this->notifyResidentStatusUpdated(
            $residentComplaint
        );

        $this->recordOperationalActivity(
            event: 'complaint.status_updated',
            category: 'complaint',
            description:
                'A resident complaint status was updated.',
            metadata: [
                'complaint_id' =>
                    (int) $residentComplaint->id,
                'previous_status' =>
                    $previousStatus,
                'new_status' =>
                    $residentComplaint->status,
            ],
            request: $request,
        );

        return response()
            ->json([
                'message' =>
                    'Complaint status updated successfully.',
                'data' =>
                    $this->serializeComplaint(
                        $residentComplaint
                    ),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function storeProof(
        Request $request,
        ResidentComplaint $residentComplaint
    ): JsonResponse {
        Gate::authorize(
            'update',
            $residentComplaint
        );

        if (
            ! Schema::hasTable(
                'resident_complaint_proofs'
            )
        ) {
            return response()->json([
                'message' =>
                    'Complaint proof table is missing. Please run the migration first.',
            ], 409);
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
        ]);

        $proofPath = null;

        try {
            $proofPath =
                $this->secureUploads->store(
                    $request->file(
                        'proof_picture'
                    ),
                    'complaint_proof'
                );

            DB::transaction(function () use (
                $residentComplaint,
                $request,
                $validated,
                $proofPath
            ): void {
                DB::table(
                    'resident_complaint_proofs'
                )->insert([
                    'resident_complaint_id' =>
                        $residentComplaint->id,
                    'uploaded_by' =>
                        $request->user()->id,
                    'proof_path' =>
                        $proofPath,
                    'proof_note' =>
                        $validated['proof_note']
                            ?? null,
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
            description:
                'A proof image was uploaded for a resident complaint.',
            metadata: [
                'complaint_id' =>
                    (int) $residentComplaint->id,
                'proof_note_provided' =>
                    ! empty(
                        $validated['proof_note']
                    ),
            ],
            request: $request,
        );

        $residentComplaint->load('resident');

        return response()
            ->json([
                'message' =>
                    'Proof picture sent to resident successfully.',
                'data' =>
                    $this->serializeComplaint(
                        $residentComplaint
                    ),
            ], 201)
            ->header('Cache-Control', 'no-store, private');
    }

    public function evidence(
        Request $request,
        ResidentComplaint $residentComplaint
    ): BinaryFileResponse {
        Gate::authorize(
            'view',
            $residentComplaint
        );

        $path =
            $residentComplaint->evidence_path;

        if (
            ! $path
            || str_starts_with(
                (string) $path,
                'http'
            )
        ) {
            abort(404, 'Evidence file not found.');
        }

        return $this->serveFile(
            (string) $path,
            'Evidence file not found.'
        );
    }

    public function proofFile(
        Request $request,
        int $proof
    ): BinaryFileResponse {
        if (
            ! Schema::hasTable(
                'resident_complaint_proofs'
            )
        ) {
            abort(
                404,
                'Complaint proof table not found.'
            );
        }

        $proofRecord = DB::table(
            'resident_complaint_proofs'
        )
            ->where('id', $proof)
            ->first();

        if (! $proofRecord) {
            abort(404, 'Proof picture not found.');
        }

        $complaint =
            ResidentComplaint::query()
                ->find(
                    (int) $proofRecord
                        ->resident_complaint_id
                );

        if (! $complaint) {
            abort(
                404,
                'Related complaint not found.'
            );
        }

        Gate::authorize(
            'view',
            $complaint
        );

        return $this->serveFile(
            (string) $proofRecord->proof_path,
            'Proof picture file is missing.'
        );
    }

    public function destroy(
        Request $request,
        ResidentComplaint $residentComplaint
    ): JsonResponse {
        Gate::authorize(
            'delete',
            $residentComplaint
        );

        $metadata = [
            'complaint_id' =>
                (int) $residentComplaint->id,
            'status' =>
                $residentComplaint->status,
            'resident_id' =>
                $residentComplaint->resident_id,
            'evidence_attached' =>
                ! empty(
                    $residentComplaint
                        ->evidence_path
                ),
        ];

        $filePaths =
            $this->complaintProofPaths(
                $residentComplaint
            );

        if (
            $residentComplaint
                ->evidence_path
        ) {
            $filePaths[] =
                $residentComplaint
                    ->evidence_path;
        }

        DB::transaction(function () use (
            $residentComplaint
        ): void {
            $this
                ->deleteComplaintNotifications(
                    $residentComplaint
                );

            $this->deleteComplaintProofRows(
                $residentComplaint
            );

            $residentComplaint->delete();
        });

        $this->secureUploads->deleteMany(
            $filePaths,
            ['resident-complaints']
        );

        $this->recordOperationalActivity(
            event: 'complaint.deleted',
            category: 'complaint',
            description:
                'A resident complaint was deleted.',
            metadata: $metadata,
            request: $request,
        );

        return response()
            ->json([
                'message' =>
                    'Complaint deleted successfully.',
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    private function serializeListItem(
        ResidentComplaint $complaint
    ): array {
        return [
            'id' => (int) $complaint->id,
            'resident_id' =>
                (int) $complaint->resident_id,
            'complainant_name' =>
                (string) $complaint
                    ->complainant_name,
            'contact_number' =>
                $complaint->contact_number,
            'complaint_address' =>
                (string) $complaint
                    ->complaint_address,
            'status' =>
                (string) $complaint->status,
            'status_label' =>
                $complaint->statusLabel(),
            'submitted_at' =>
                $complaint
                    ->submitted_at
                    ?->toIso8601String(),
            'has_evidence' =>
                ! empty(
                    $complaint->evidence_path
                ),
            'resident' =>
                $complaint->resident
                ? [
                    'id' =>
                        (int) $complaint
                            ->resident->id,
                    'name' =>
                        (string) $complaint
                            ->resident->name,
                    'role' =>
                        (string) $complaint
                            ->resident->role,
                ]
                : null,
        ];
    }

    private function serializeComplaint(
        ResidentComplaint $complaint
    ): array {
        return [
            ...$this->serializeListItem(
                $complaint
            ),
            'complaint_description' =>
                (string) $complaint
                    ->complaint_description,
            'evidence_url' =>
                $complaint->evidence_path
                ? route(
                    'api.v1.resident-complaints.evidence',
                    $complaint
                )
                : null,
            'proofs' =>
                $this->complaintProofs(
                    $complaint
                ),
        ];
    }

    private function complaintProofs(
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
            ->leftJoin(
                'users',
                'users.id',
                '=',
                'resident_complaint_proofs.uploaded_by'
            )
            ->where(
                'resident_complaint_proofs.resident_complaint_id',
                $complaint->id
            )
            ->orderByDesc(
                'resident_complaint_proofs.created_at'
            )
            ->select([
                'resident_complaint_proofs.id',
                'resident_complaint_proofs.proof_note',
                'resident_complaint_proofs.created_at',
                'users.name as uploader_name',
                'users.role as uploader_role',
            ])
            ->get()
            ->map(
                fn ($proof): array => [
                    'id' => (int) $proof->id,
                    'proof_note' =>
                        $proof->proof_note,
                    'created_at' =>
                        $proof->created_at,
                    'uploader_name' =>
                        $proof->uploader_name,
                    'uploader_role' =>
                        $proof->uploader_role,
                    'file_url' => route(
                        'api.v1.resident-complaints.proofs.file',
                        [
                            'proof' =>
                                (int) $proof->id,
                        ]
                    ),
                ]
            )
            ->values()
            ->all();
    }

    private function notifyAdminsAndOfficials(
        ResidentComplaint $complaint
    ): void {
        if (
            ! Schema::hasTable(
                'notifications'
            )
        ) {
            return;
        }

        User::query()
            ->whereIn(
                'role',
                ['admin', 'official', 'dao']
            )
            ->select(['id', 'role'])
            ->chunkById(
                100,
                function ($users) use (
                    $complaint
                ): void {
                    foreach ($users as $user) {
                        UserNotification::updateOrCreate(
                            [
                                'user_id' =>
                                    $user->id,
                                'type' =>
                                    'resident_complaint',
                                'source_id' =>
                                    $complaint->id,
                            ],
                            [
                                'user_id' =>
                                    $user->id,
                                'type' =>
                                    'resident_complaint',
                                'source_id' =>
                                    $complaint->id,
                                'title' =>
                                    'New resident complaint',
                                'message' =>
                                    $complaint
                                        ->complainant_name
                                    . ' submitted a complaint: '
                                    . mb_substr(
                                        $complaint
                                            ->complaint_description,
                                        0,
                                        160
                                    ),
                                'is_read' =>
                                    false,
                                'read_at' =>
                                    null,
                            ]
                        );
                    }
                }
            );
    }

    private function notifyResidentStatusUpdated(
        ResidentComplaint $complaint
    ): void {
        if (
            ! Schema::hasTable(
                'notifications'
            )
        ) {
            return;
        }

        UserNotification::updateOrCreate(
            [
                'user_id' =>
                    $complaint->resident_id,
                'type' =>
                    'resident_complaint_update',
                'source_id' =>
                    $complaint->id,
            ],
            [
                'user_id' =>
                    $complaint->resident_id,
                'type' =>
                    'resident_complaint_update',
                'source_id' =>
                    $complaint->id,
                'title' =>
                    'Complaint status updated',
                'message' =>
                    'Your complaint status is now '
                    . $complaint->statusLabel()
                    . '.',
                'is_read' => false,
                'read_at' => null,
            ]
        );
    }

    private function notifyResidentProofUploaded(
        ResidentComplaint $complaint
    ): void {
        if (
            ! Schema::hasTable(
                'notifications'
            )
        ) {
            return;
        }

        UserNotification::updateOrCreate(
            [
                'user_id' =>
                    $complaint->resident_id,
                'type' =>
                    'resident_complaint_update',
                'source_id' =>
                    $complaint->id,
            ],
            [
                'user_id' =>
                    $complaint->resident_id,
                'type' =>
                    'resident_complaint_update',
                'source_id' =>
                    $complaint->id,
                'title' =>
                    'Complaint proof picture uploaded',
                'message' =>
                    'An admin or official uploaded a proof picture for your complaint.',
                'is_read' => false,
                'read_at' => null,
            ]
        );
    }

    private function deleteComplaintNotifications(
        ResidentComplaint $complaint
    ): void {
        if (
            ! Schema::hasTable(
                'notifications'
            )
        ) {
            return;
        }

        UserNotification::query()
            ->where(
                'source_id',
                $complaint->id
            )
            ->whereIn(
                'type',
                [
                    'resident_complaint',
                    'resident_complaint_update',
                ]
            )
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
                    && trim((string) $path)
                        !== ''
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

    private function serveFile(
        string $path,
        string $missingMessage
    ): BinaryFileResponse {
        $storedFile =
            $this->secureUploads->resolve(
                $path,
                ['resident-complaints'],
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
        $response->headers
            ->addCacheControlDirective(
                'no-store',
                true
            );

        return $response;
    }

    private function statuses(): array
    {
        return [
            'submitted' => 'Submitted',
            'under_review' =>
                'Under Review',
            'in_progress' =>
                'In Progress',
            'resolved' =>
                'Resolved',
            'rejected' =>
                'Rejected',
        ];
    }
}
