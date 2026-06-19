@php
    $todaySummary = $this->getTodaySummary();
    $recentSummaries = $this->getRecentSummaries();
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Today's Attendance
            </x-slot>

            <x-slot name="description">
                View today's attendance result and jump straight into your attendance history or correction workflow.
            </x-slot>

            @if ($todaySummary)
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Attendance Date</p>
                        <p class="mt-2 text-lg font-semibold text-gray-950">{{ $todaySummary->attendance_date->toFormattedDateString() }}</p>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Status</p>
                        <span class="mt-2 inline-flex rounded-full px-3 py-1 text-sm font-medium ring-1 ring-inset {{ $this->getStatusBadgeClasses($todaySummary->status) }}">
                            {{ $this->getStatusLabel($todaySummary->status) }}
                        </span>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Actual Clock In</p>
                        <p class="mt-2 text-lg font-semibold text-gray-950">{{ $todaySummary->actual_in_at?->format('H:i') ?? '-' }}</p>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Actual Clock Out</p>
                        <p class="mt-2 text-lg font-semibold text-gray-950">{{ $todaySummary->actual_out_at?->format('H:i') ?? '-' }}</p>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Late Minutes</p>
                        <p class="mt-2 text-lg font-semibold text-gray-950">{{ $todaySummary->late_minutes ?? 0 }}</p>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Early Leave Minutes</p>
                        <p class="mt-2 text-lg font-semibold text-gray-950">{{ $todaySummary->early_out_minutes ?? 0 }}</p>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Work Minutes</p>
                        <p class="mt-2 text-lg font-semibold text-gray-950">{{ $todaySummary->work_minutes ?? 0 }}</p>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Shift</p>
                        <p class="mt-2 text-lg font-semibold text-gray-950">{{ $todaySummary->shiftPattern?->name ?? '-' }}</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Scheduled Work Window</p>
                        <p class="mt-2 text-base font-medium text-gray-950">
                            {{ $todaySummary->scheduled_start_at?->format('H:i') ?? '-' }}
                            -
                            {{ $todaySummary->scheduled_end_at?->format('H:i') ?? '-' }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Work Location</p>
                        <p class="mt-2 text-base font-medium text-gray-950">{{ $todaySummary->workLocation?->name ?? '-' }}</p>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-3">
                    <x-filament::button :href="$this->getCorrectionCreateUrl($todaySummary)" tag="a" icon="heroicon-o-pencil-square">
                        Request Correction
                    </x-filament::button>

                    <x-filament::button :href="$this->getHistoryUrl()" tag="a" color="gray" icon="heroicon-o-clock">
                        View History
                    </x-filament::button>

                    <x-filament::button :href="$this->getCorrectionsUrl()" tag="a" color="gray" icon="heroicon-o-document-text">
                        My Corrections
                    </x-filament::button>
                </div>
            @else
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600">
                    No attendance summary is available for today yet.
                </div>

                <div class="mt-4 flex flex-wrap gap-3">
                    <x-filament::button :href="$this->getHistoryUrl()" tag="a" color="gray" icon="heroicon-o-clock">
                        View History
                    </x-filament::button>

                    <x-filament::button :href="$this->getCorrectionCreateUrl()" tag="a" color="gray" icon="heroicon-o-pencil-square">
                        Request Correction
                    </x-filament::button>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Recent Attendance
            </x-slot>

            <x-slot name="description">
                Last 7 days of your attendance summaries.
            </x-slot>

            @if ($recentSummaries->isEmpty())
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600">
                    No recent attendance summaries are available yet.
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Actual In</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Actual Out</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Work Minutes</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Action</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-200">
                                @foreach ($recentSummaries as $summary)
                                    <tr class="bg-white">
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $summary->attendance_date->toDateString() }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $this->getStatusBadgeClasses($summary->status) }}">
                                                {{ $this->getStatusLabel($summary->status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $summary->actual_in_at?->format('H:i') ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $summary->actual_out_at?->format('H:i') ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $summary->work_minutes ?? 0 }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            <a
                                                href="{{ $this->getCorrectionCreateUrl($summary) }}"
                                                class="font-medium text-primary-600 hover:text-primary-500"
                                            >
                                                Request Correction
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
