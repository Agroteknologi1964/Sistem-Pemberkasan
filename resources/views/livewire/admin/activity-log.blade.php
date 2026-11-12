<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Riwayat Aktivitas</h1>
            <p class="text-sm text-gray-500 dark:text-slate-400">Rekam jejak seluruh tindakan user dan sistem.</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-slate-900/60 border-b border-gray-200 dark:border-slate-800">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-slate-400">Waktu</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-slate-400">User</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-slate-400">Aktifitas</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-slate-400">Modul</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($logs as $log)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-800 dark:bg-slate-900/60 transition-colors">
                        <td class="px-4 py-3 text-gray-500 dark:text-slate-400">
                            {{ $log->created_at->format('d M Y, H:i') }}
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-slate-100">
                            {{ $log->causer ? $log->causer->name : 'Sistem' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium 
                                {{ $log->description === 'created' ? 'bg-green-100 text-green-700 dark:text-emerald-300' : 
                                  ($log->description === 'updated' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700 dark:text-red-300') }}">
                                {{ ucfirst($log->description) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-slate-300">
                            {{ class_basename($log->subject_type) }} 
                            (ID: {{ $log->subject_id }})
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-12 text-center text-gray-400 dark:text-slate-500">
                            Belum ada aktivitas tercatat.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        <div class="border-t border-gray-100 dark:border-slate-800 px-4 py-3">
            {{ $logs->links() }}
        </div>
    </div>
</div>