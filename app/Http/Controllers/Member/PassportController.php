<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\PassportService;
use Illuminate\Http\Request;

class PassportController extends Controller
{
    /** Curated city coordinates (public data) for Passport picks. */
    public const CITIES = [
        ['city' => 'Jakarta', 'province' => 'DKI Jakarta', 'lat' => -6.2088, 'lng' => 106.8456],
        ['city' => 'Bandung', 'province' => 'Jawa Barat', 'lat' => -6.9175, 'lng' => 107.6191],
        ['city' => 'Surabaya', 'province' => 'Jawa Timur', 'lat' => -7.2575, 'lng' => 112.7521],
        ['city' => 'Medan', 'province' => 'Sumatera Utara', 'lat' => 3.5952, 'lng' => 98.6722],
        ['city' => 'Semarang', 'province' => 'Jawa Tengah', 'lat' => -6.9667, 'lng' => 110.4167],
        ['city' => 'Yogyakarta', 'province' => 'DI Yogyakarta', 'lat' => -7.7956, 'lng' => 110.3695],
        ['city' => 'Denpasar', 'province' => 'Bali', 'lat' => -8.6705, 'lng' => 115.2126],
        ['city' => 'Makassar', 'province' => 'Sulawesi Selatan', 'lat' => -5.1477, 'lng' => 119.4327],
        ['city' => 'Palembang', 'province' => 'Sumatera Selatan', 'lat' => -2.9909, 'lng' => 104.7565],
        ['city' => 'Balikpapan', 'province' => 'Kalimantan Timur', 'lat' => -1.2379, 'lng' => 116.8529],
    ];

    public function show(Request $request, PassportService $passport)
    {
        $user = $request->user();

        return $request->wantsJson()
            ? response()->json(['effective' => $passport->effectiveLocation($user), 'is_premium' => $user->isPremium()])
            : view('member.passport.index', ['effective' => $passport->effectiveLocation($user), 'cities' => self::CITIES]);
    }

    public function store(Request $request, PassportService $passport)
    {
        $data = $request->validate([
            'city' => ['required', 'string', 'max:120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        try {
            $passport->set($request->user(), $data + ['country' => 'Indonesia']);
        } catch (\RuntimeException $e) {
            return $request->wantsJson()
                ? response()->json(['message' => $e->getMessage(), 'upgrade' => true], 403)
                : back()->withErrors(['passport' => $e->getMessage()]);
        }

        return $request->wantsJson()
            ? response()->json(['effective' => $passport->effectiveLocation($request->user()->fresh())])
            : back()->with('status', 'Passport aktif: discovery memakai lokasi virtual.');
    }

    public function destroy(Request $request, PassportService $passport)
    {
        $passport->clear($request->user());

        return $request->wantsJson()
            ? response()->json(['message' => 'Passport disabled.'])
            : back()->with('status', 'Kembali ke lokasi saat ini.');
    }
}
