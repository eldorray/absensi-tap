<?php

namespace App\Notifications;

use App\Enums\Role;
use App\Models\Izin;
use App\Models\IzinOrangTua;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Push ke HP admin saat ada pengajuan izin baru yang menunggu keputusan.
 */
class IzinDiajukan extends Notification
{
    public function __construct(
        public readonly string $judul,
        public readonly string $isi,
        public readonly string $url,
    ) {}

    public static function dariIzinGuru(Izin $izin): self
    {
        return new self(
            'Izin guru: '.$izin->user->name,
            ucfirst($izin->tipe->value).', '.self::rentang($izin->tanggal_mulai, $izin->tanggal_selesai),
            route('admin.izin.index'),
        );
    }

    public static function dariIzinOrangTua(IzinOrangTua $izin): self
    {
        return new self(
            'Izin siswa: '.$izin->siswa->nama,
            ucfirst($izin->tipe->value).', '.self::rentang($izin->tanggal_mulai, $izin->tanggal_selesai).' (oleh '.$izin->nama_pengaju.')',
            route('admin.izin-orang-tua.index'),
        );
    }

    /**
     * Dikirim setelah response selesai supaya pengaju tidak menunggu layanan
     * push. Hanya admin aktif yang punya langganan yang diambil.
     */
    public function kirimKeAdmin(): void
    {
        // ponytail: defer() tanpa queue worker; pindah ke ShouldQueue kalau admin banyak.
        defer(fn () => NotificationFacade::send(
            User::query()
                ->where('role', Role::Admin)
                ->where('is_active', true)
                ->whereHas('pushSubscriptions')
                ->get(),
            $this,
        ));
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->judul)
            ->body($this->isi)
            ->icon('/pwa-192.png')
            ->data(['url' => $this->url])
            // Apple: tanpa urgency high, pengiriman boleh ditunda demi daya baterai HP.
            ->options(['urgency' => 'high']);
    }

    private static function rentang(CarbonInterface $mulai, CarbonInterface $selesai): string
    {
        return $mulai->isSameDay($selesai)
            ? $mulai->translatedFormat('d F Y')
            : $mulai->translatedFormat('d F').' - '.$selesai->translatedFormat('d F Y');
    }
}
