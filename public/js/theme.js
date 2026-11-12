/**
 * Dark mode untuk Sistem Pemberkasan.
 *
 * Strategi: Tailwind dikonfigurasi `darkMode: 'class'`, dan seluruh view memakai
 * utility `dark:` di samping utility terang. File ini hanya menyimpan
 * preferensi pengguna di localStorage dan memasang class `dark` di <html>
 * SEBELUM paint (inline di <head>) supaya tidak ada kedipan putih.
 */
(function () {
    const STORAGE_KEY = 'pemberkasan-theme';

    function getStored() {
        try {
            return localStorage.getItem(STORAGE_KEY);
        } catch (e) {
            return null;
        }
    }

    function getPreferred() {
        const stored = getStored();
        if (stored === 'dark' || stored === 'light') return stored;

        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function apply(theme) {
        document.documentElement.classList.toggle('dark', theme === 'dark');
        document.documentElement.style.colorScheme = theme;
        window.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme } }));
    }

    function set(theme) {
        try {
            localStorage.setItem(STORAGE_KEY, theme);
        } catch (e) {
            // localStorage bisa diblokir (mode privat) — abaikan, tetap apply di sesi ini.
        }
        apply(theme);
    }

    function toggle() {
        set(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
    }

    // Pasang sedini mungkin agar tidak ada flash of wrong theme.
    apply(getPreferred());

    window.theme = { get: getPreferred, set: set, toggle: toggle };

    // Ikuti perubahan preferensi OS selama user belum memilih manual.
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (!getStored()) apply(e.matches ? 'dark' : 'light');
    });
})();
