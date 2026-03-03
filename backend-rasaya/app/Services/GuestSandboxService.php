<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GuestSandboxService
{
    private const TTL_SECONDS = 14400; // 4 jam

    public function isGuestGuruBk(Request $request): bool
    {
        return $request->hasSession()
            && (bool) $request->session()->get('guest_mode', false)
            && $request->session()->get('guest_role') === 'guru-bk';
    }

    public function isGuestSiswa(Request $request): bool
    {
        $user = $request->user();
        if (!$user || $user->role !== 'siswa') {
            return false;
        }

        $guestIdentifier = (string) config('auth.guest_accounts.siswa.identifier', 'guest_siswa');
        return $guestIdentifier !== '' && $user->identifier === $guestIdentifier;
    }

    public function clearForRequest(Request $request): void
    {
        foreach ([
            'refleksi',
            'refleksi_seq',
            'mood',
            'mood_seq',
            'booking',
            'booking_seq',
            'guru_bk_observasi',
            'guru_bk_observasi_seq',
            'guru_bk_slots',
            'guru_bk_slots_seq',
            'guru_bk_deleted_slot_ids',
            'guru_bk_booking_status',
        ] as $bucket) {
            Cache::forget($this->key($request, $bucket));
        }
    }

    public function getGuruBkObservasi(Request $request): array
    {
        return Cache::get($this->key($request, 'guru_bk_observasi'), []);
    }

    public function putGuruBkObservasi(Request $request, array $items): void
    {
        Cache::put($this->key($request, 'guru_bk_observasi'), array_values($items), self::TTL_SECONDS);
    }

    public function nextGuruBkObservasiId(Request $request): int
    {
        $id = (int) Cache::increment($this->key($request, 'guru_bk_observasi_seq'));
        Cache::put($this->key($request, 'guru_bk_observasi_seq'), $id, self::TTL_SECONDS);
        return $id;
    }

    public function getGuruBkSlots(Request $request): array
    {
        return Cache::get($this->key($request, 'guru_bk_slots'), []);
    }

    public function putGuruBkSlots(Request $request, array $items): void
    {
        Cache::put($this->key($request, 'guru_bk_slots'), array_values($items), self::TTL_SECONDS);
    }

    public function nextGuruBkSlotId(Request $request): int
    {
        $id = (int) Cache::increment($this->key($request, 'guru_bk_slots_seq'));
        Cache::put($this->key($request, 'guru_bk_slots_seq'), $id, self::TTL_SECONDS);
        return $id;
    }

    public function getGuruBkDeletedSlotIds(Request $request): array
    {
        return Cache::get($this->key($request, 'guru_bk_deleted_slot_ids'), []);
    }

    public function putGuruBkDeletedSlotIds(Request $request, array $ids): void
    {
        $normalized = array_values(array_unique(array_map('intval', $ids)));
        Cache::put($this->key($request, 'guru_bk_deleted_slot_ids'), $normalized, self::TTL_SECONDS);
    }

    public function getGuruBkBookingStatus(Request $request): array
    {
        return Cache::get($this->key($request, 'guru_bk_booking_status'), []);
    }

    public function putGuruBkBookingStatus(Request $request, array $items): void
    {
        Cache::put($this->key($request, 'guru_bk_booking_status'), $items, self::TTL_SECONDS);
    }

    public function getRefleksi(Request $request): array
    {
        return Cache::get($this->key($request, 'refleksi'), []);
    }

    public function putRefleksi(Request $request, array $items): void
    {
        Cache::put($this->key($request, 'refleksi'), array_values($items), self::TTL_SECONDS);
    }

    public function nextRefleksiId(Request $request): int
    {
        $id = (int) Cache::increment($this->key($request, 'refleksi_seq'));
        Cache::put($this->key($request, 'refleksi_seq'), $id, self::TTL_SECONDS);
        return $id;
    }

    public function getMood(Request $request): array
    {
        return Cache::get($this->key($request, 'mood'), []);
    }

    public function putMood(Request $request, array $items): void
    {
        Cache::put($this->key($request, 'mood'), array_values($items), self::TTL_SECONDS);
    }

    public function nextMoodId(Request $request): int
    {
        $id = (int) Cache::increment($this->key($request, 'mood_seq'));
        Cache::put($this->key($request, 'mood_seq'), $id, self::TTL_SECONDS);
        return $id;
    }

    public function getBooking(Request $request): array
    {
        return Cache::get($this->key($request, 'booking'), []);
    }

    public function putBooking(Request $request, array $items): void
    {
        Cache::put($this->key($request, 'booking'), array_values($items), self::TTL_SECONDS);
    }

    public function nextBookingId(Request $request): int
    {
        $id = (int) Cache::increment($this->key($request, 'booking_seq'));
        Cache::put($this->key($request, 'booking_seq'), $id, self::TTL_SECONDS);
        return $id;
    }

    private function key(Request $request, string $bucket): string
    {
        $tokenId = optional($request->user()?->currentAccessToken())->id;
        $userId = optional($request->user())->id ?? 'guest';
        $scope = $tokenId ? ('token_' . $tokenId) : ('user_' . $userId);
        return "guest_sandbox:{$scope}:{$bucket}";
    }
}
