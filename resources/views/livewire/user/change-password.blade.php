<div class="mx-auto max-w-md">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <h1 class="mb-5 text-base font-semibold text-gray-900">Ganti Password</h1>

        @if($success)
            <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-800">
                ✅ Password berhasil diperbarui.
            </div>
        @endif

        @if(auth()->user()->force_change_password)
            <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                ⚠️ Anda diwajibkan mengganti password sebelum menggunakan sistem.
            </div>
        @endif

        <div class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">Password Saat Ini</label>
                <input wire:model.defer="current_password" type="password"
                       class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200" />
                @error('current_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">Password Baru</label>
                <input wire:model.defer="password" type="password"
                       class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                       placeholder="Min. 8 karakter" />
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">Konfirmasi Password Baru</label>
                <input wire:model.defer="password_confirmation" type="password"
                       class="w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200" />
            </div>
        </div>

        <div class="mt-5 flex justify-end">
            <button wire:click="save"
                    class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                Simpan Password
            </button>
        </div>
    </div>
</div>
