<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\PengaturanAplikasi;
use App\Models\User;
use App\Support\TahunAjaranTerpilih;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View as ViewView;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Tahun ajaran yang sedang dipakai request ini, dipakai global scope
        // dan stempel baris baru. Scoped: sekali diselesaikan per request.
        $this->app->scoped(TahunAjaranTerpilih::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::define('admin', fn (User $user): bool => $user->role === Role::Admin && $user->is_active);

        // Guru piket: pengisi absensi siswa untuk seluruh sekolah.
        //
        // Piket memakai akun ber-role admin, jadi isinya memang sama dengan
        // gate 'admin' di atas. Gerbangnya tetap dipisah supaya izin "siapa
        // yang boleh mengisi absensi siswa" punya satu tempat yang terbaca
        // jelas di route -- kalau nanti piket jadi penugasan tersendiri,
        // yang berubah cuma baris ini, bukan setiap route yang memakainya.
        Gate::define('piket', fn (User $user): bool => $user->role === Role::Admin && $user->is_active);

        // Wilayah kerja pegawai sekolah: tap absen, jadwal, riwayat, izin.
        // Admin ikut lolos karena admin di sekolah ini juga tap absen sendiri.
        // Gate ini sengaja tidak memeriksa is_active supaya guru yang sedang
        // dinonaktifkan tidak berubah perilakunya diam-diam oleh perubahan ini;
        // pemeriksaan is_active saat login adalah keputusan terpisah.
        Gate::define('pegawai', fn (User $user): bool => in_array($user->role, [Role::Guru, Role::Admin], true));
        Gate::define('orang-tua', fn (User $user): bool => $user->role === Role::OrangTua && $user->is_active);

        // Nama dan favicon dipakai di <head> root template, yang dirender di
        // luar Inertia. Ditunda lewat closure supaya tabelnya tidak disentuh
        // saat migrasi belum jalan.
        View::composer('app', function (ViewView $view): void {
            $aplikasi = PengaturanAplikasi::current();

            $view->with([
                'namaAplikasi' => $aplikasi->nama,
                'faviconAplikasi' => $aplikasi->faviconUrl(),
            ]);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
