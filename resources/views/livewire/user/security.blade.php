<div class="mx-auto max-w-2xl space-y-6">

    {{-- ── Status 2FA ─────────────────────────────────────────────────────── --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-base font-semibold text-gray-900 dark:text-slate-100">Verifikasi Dua Langkah (2FA)</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">
                    Lapisan keamanan tambahan: selain password, login juga memerlukan kode 6 digit
                    dari aplikasi authenticator di ponsel Anda.
                </p>
            </div>
            @if($user->hasTwoFactorEnabled())
                <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                    Aktif
                </span>
            @else
                <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-slate-800 dark:text-slate-300">
                    Nonaktif
                </span>
            @endif
        </div>

        @error('enable')
            <p class="mt-3 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror

        @if($user->isAdmin() && ! $user->hasTwoFactorEnabled())
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                Akun administrator sebaiknya mengaktifkan 2FA karena memiliki akses ke seluruh arsip dokumen.
            </div>
        @endif

        <div class="mt-5 flex flex-wrap gap-3">
            @if($user->hasTwoFactorEnabled())
                <button wire:click="regenerateRecoveryCodes"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                    Buat Ulang Kode Pemulihan
                </button>
                <button wire:click="startDisable"
                        class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-500/40 dark:text-red-300 dark:hover:bg-red-500/10">
                    Matikan 2FA
                </button>
            @else
                <button wire:click="startEnable"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Aktifkan 2FA
                </button>
            @endif
        </div>
    </div>

    {{-- ── Kode pemulihan (hanya sekali) ──────────────────────────────────── --}}
    @if($showRecoveryCodes && count($recoveryCodes))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 dark:border-emerald-500/30 dark:bg-emerald-500/10">
            <h2 class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">
                Simpan kode pemulihan ini sekarang
            </h2>
            <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">
                Kode ini <strong>hanya ditampilkan sekali</strong>. Setiap kode bisa dipakai satu kali
                untuk masuk jika Anda kehilangan ponsel. Simpan di tempat aman, bukan di dalam sistem ini.
            </p>

            <div class="mt-4 grid grid-cols-2 gap-2 rounded-lg bg-white p-4 font-mono text-sm dark:bg-slate-900 sm:grid-cols-2">
                @foreach($recoveryCodes as $code)
                    <span class="select-all text-gray-800 dark:text-slate-200">{{ $code }}</span>
                @endforeach
            </div>

            <div class="mt-4 flex gap-3">
                <button wire:click="hideRecoveryCodes"
                        class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Saya sudah menyimpannya
                </button>
            </div>
        </div>
    @endif

    {{-- ── Modal aktifkan 2FA ─────────────────────────────────────────────── --}}
    @if($showEnableModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:key="enable-2fa">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-slate-100">Aktifkan Verifikasi Dua Langkah</h2>

                <ol class="mt-4 space-y-3 text-sm text-gray-600 dark:text-slate-300">
                    <li>
                        <span class="font-semibold text-gray-900 dark:text-slate-100">1.</span>
                        Buka aplikasi authenticator (Google Authenticator, Microsoft Authenticator, Authy).
                    </li>
                    <li>
                        <span class="font-semibold text-gray-900 dark:text-slate-100">2.</span>
                        Pilih "Tambah akun" lalu masukkan kunci di bawah secara manual.
                    </li>
                    <li>
                        <span class="font-semibold text-gray-900 dark:text-slate-100">3.</span>
                        Masukkan kode 6 digit yang muncul untuk mengonfirmasi.
                    </li>
                </ol>

                <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-slate-700 dark:bg-slate-800">
                    <p class="text-xs font-medium text-gray-500 dark:text-slate-400">Kunci setup</p>
                    <p class="mt-1 select-all break-all font-mono text-sm font-semibold tracking-wider text-gray-900 dark:text-slate-100">
                        {{ $pendingSecret }}
                    </p>
                </div>

                <details class="mt-3 text-xs text-gray-500 dark:text-slate-400">
                    <summary class="cursor-pointer hover:text-indigo-600">Tampilkan URI otpauth</summary>
                    <p class="mt-2 select-all break-all rounded bg-gray-50 p-2 font-mono dark:bg-slate-800">{{ $provisioningUri }}</p>
                </details>

                <div class="mt-5">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-200">
                        Kode 6 digit dari aplikasi
                    </label>
                    <input wire:model="confirmCode" wire:keydown.enter="confirmEnable"
                           type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="20"
                           placeholder="000000"
                           class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-center font-mono text-lg tracking-[0.3em]
                                  dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100
                                  focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200" />
                    @error('confirmCode')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-5 flex justify-end gap-3">
                    <button wire:click="$set('showEnableModal', false)"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                        Batal
                    </button>
                    <button wire:click="confirmEnable" wire:loading.attr="disabled"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">
                        Konfirmasi & Aktifkan
                    </button>
                </div>

                <p class="mt-4 text-xs text-gray-400">
                    Waktu di ponsel harus akurat. Kode berganti tiap 30 detik.
                </p>
            </div>
        </div>
    @endif

    {{-- ── Modal matikan 2FA ──────────────────────────────────────────────── --}}
    @if($showDisableModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" wire:key="disable-2fa">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900">
                <h2 class="text-base font-semibold text-gray-900 dark:text-slate-100">Matikan Verifikasi Dua Langkah</h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-slate-400">
                    Akun Anda akan hanya dilindungi password. Masukkan password untuk mengonfirmasi.
                </p>

                <div class="mt-4">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-slate-200">Password</label>
                    <input wire:model="disablePassword" type="password" autocomplete="current-password"
                           class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100
                                  focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200" />
                    @error('disablePassword')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-5 flex justify-end gap-3">
                    <button wire:click="$set('showDisableModal', false)"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                        Batal
                    </button>
                    <button wire:click="confirmDisable"
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                        Matikan 2FA
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
