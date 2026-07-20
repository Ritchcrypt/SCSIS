@extends('layouts.admin')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="text-2xl font-black text-slate-900">
                Submit Complaint
            </h1>

            <p class="mt-1 text-sm text-slate-600">
                Report a non-emergency community concern to the barangay office.
            </p>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
                <p class="font-black">Please fix the following:</p>

                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
              action="{{ route('resident.resident-complaints.store') }}"
              enctype="multipart/form-data"
              class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-bold text-slate-700">
                        Complainant Full Name
                    </label>

                    <input type="text"
                           value="{{ $user->name }}"
                           disabled
                           class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-700">
                </div>

                <div>
                    <label for="contact_number" class="mb-2 block text-sm font-bold text-slate-700">
                        Contact Number
                    </label>

                    <input id="contact_number"
                           name="contact_number"
                           type="text"
                           value="{{ old('contact_number') }}"
                           placeholder="Optional"
                           class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
            </div>

            <div>
                <label for="complaint_address" class="mb-2 block text-sm font-bold text-slate-700">
                    Address / Location of Complaint
                </label>

                <textarea id="complaint_address"
                          name="complaint_address"
                          rows="3"
                          required
                          placeholder="Example: Purok 2, near covered court, Dao, Capiz"
                          class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">{{ old('complaint_address') }}</textarea>
            </div>

            <div>
                <label for="complaint_description" class="mb-2 block text-sm font-bold text-slate-700">
                    Complaint Description
                </label>

                <textarea id="complaint_description"
                          name="complaint_description"
                          rows="6"
                          required
                          placeholder="Describe the complaint clearly."
                          class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">{{ old('complaint_description') }}</textarea>
            </div>

            <div>
                <label for="evidence" class="mb-2 block text-sm font-bold text-slate-700">
                    Evidence Picture
                </label>

                <input id="evidence"
                       name="evidence"
                       type="file"
                       accept="image/jpeg,image/png,image/webp"
                       class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-bold file:text-blue-700 hover:file:bg-blue-100">

                <p class="mt-2 text-xs text-slate-500">
                    Accepted: JPG, PNG, WEBP. Maximum size: 4MB.
                </p>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-5">
                <a href="{{ route('resident.resident-complaints.index') }}"
                   class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    Cancel
                </a>

                <button type="submit"
                        class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">
                    Submit Complaint
                </button>
            </div>
        </form>
    </div>
@endsection