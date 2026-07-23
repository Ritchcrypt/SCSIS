@extends('layouts.admin')

@section('content')
    @php
        $authUser = auth()->user();
        $role = strtolower((string) ($authUser?->role ?? ''));

        $indexRoute = match ($role) {
            'admin' => 'admin.resident-complaints.index',
            'official', 'dao' => 'official.resident-complaints.index',
            'resident' => 'resident.resident-complaints.index',
            default => 'dashboard',
        };

        $createRoute = Route::has('resident.resident-complaints.create')
            ? 'resident.resident-complaints.create'
            : null;

        $showRoute = match ($role) {
            'admin' => 'admin.resident-complaints.show',
            'official', 'dao' => 'official.resident-complaints.show',
            'resident' => 'resident.resident-complaints.show',
            default => null,
        };

        $statusClasses = [
            'submitted' => 'bg-blue-100 text-blue-700 border-blue-200',
            'under_review' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            'in_progress' => 'bg-purple-100 text-purple-700 border-purple-200',
            'resolved' => 'bg-green-100 text-green-700 border-green-200',
            'rejected' => 'bg-red-100 text-red-700 border-red-200',
        ];
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-black text-slate-900">
                    {{ $role === 'resident' ? 'My Complaints' : 'Resident Complaints' }}
                </h1>

                <p class="mt-1 text-sm text-slate-600">
                    {{ $role === 'resident'
                        ? 'Submit and track your community complaints.'
                        : 'Review complaints submitted by residents.' }}
                </p>
            </div>

            @if ($canCreateComplaint && $createRoute)
                <a href="{{ route($createRoute) }}"
                   class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-blue-700">
                    Submit Complaint
                </a>
            @endif
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm font-semibold text-green-700">
                {{ session('success') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-blue-950 text-white">
                        <tr>
                            <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide">Complainant</th>
                            <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide">Address</th>
                            <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide">Status</th>
                            <th class="px-5 py-4 text-left text-xs font-black uppercase tracking-wide">Submitted</th>
                            <th class="px-5 py-4 text-right text-xs font-black uppercase tracking-wide">Action</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($complaints as $complaint)
                            @php
                                $statusClass = $statusClasses[$complaint->status] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                            @endphp

                            <tr class="hover:bg-slate-50">

                                <td class="px-5 py-4">
                                    <p class="text-sm font-bold text-slate-900">
                                        {{ $complaint->complainant_name }}
                                    </p>

                                    <p class="text-xs text-slate-500">
                                        {{ $complaint->contact_number ?: 'No contact number' }}
                                    </p>
                                </td>

                                <td class="max-w-sm px-5 py-4 text-sm text-slate-600">
                                    {{ Str::limit($complaint->complaint_address, 70) }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4">
                                    <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold {{ $statusClass }}">
                                        {{ $complaint->statusLabel() }}
                                    </span>
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                    {{ optional($complaint->submitted_at)->format('M d, Y h:i A') }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    @if ($showRoute && Route::has($showRoute))
                                        <a href="{{ route($showRoute, $complaint) }}"
   title="View complaint"
   aria-label="View complaint"
   class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition duration-200 hover:-translate-y-0.5 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 hover:shadow-sm">
    <svg xmlns="http://www.w3.org/2000/svg"
         class="h-5 w-5"
         fill="none"
         viewBox="0 0 24 24"
         stroke="currentColor"
         stroke-width="2">
        <path stroke-linecap="round"
              stroke-linejoin="round"
              d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12z" />
        <path stroke-linecap="round"
              stroke-linejoin="round"
              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-14 text-center">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 text-2xl">
                                        💬
                                    </div>

                                    <p class="mt-4 text-sm font-black text-slate-900">
                                        No complaints found
                                    </p>

                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ $role === 'resident'
                                            ? 'You have not submitted any complaints yet.'
                                            : 'No resident complaints have been submitted yet.' }}
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($complaints->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">
                    {{ $complaints->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection