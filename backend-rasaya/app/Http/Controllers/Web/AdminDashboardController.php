<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserLoginHistory;
use App\Models\Siswa;
use App\Models\Guru;
use App\Models\InputSiswa;
use App\Models\InputGuru;
use App\Models\PemantauanEmosiSiswa;
use App\Models\SlotBooking;
use App\Models\AnalisisEntry;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    /**
     * Main admin dashboard - overview/analytics
     */
    public function index()
    {
        $activeTa = TahunAjaran::aktif()->first();
        
        // Global Statistics
        $stats = [
            'total_users' => User::count(),
            'total_siswa' => Siswa::count(),
            'total_guru' => Guru::count(),
            'total_admin' => User::where('role', 'admin')->count(),
            
            // Today's activity
            'today_refleksi_siswa' => InputSiswa::whereDate('tanggal', today())->count(),
            'today_refleksi_guru' => InputGuru::whereDate('tanggal', today())->count(),
            'today_mood_tracking' => PemantauanEmosiSiswa::whereDate('tanggal', today())->count(),
            'today_logins' => UserLoginHistory::whereDate('logged_in_at', today())->count(),
            
            // This week
            'week_refleksi_siswa' => InputSiswa::whereBetween('tanggal', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'week_konseling_bookings' => SlotBooking::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            
            // All time
            'total_refleksi_siswa' => InputSiswa::count(),
            'total_refleksi_guru' => InputGuru::count(),
            'total_mood_tracking' => PemantauanEmosiSiswa::count(),
            'total_analisis' => AnalisisEntry::count(),
            'total_konseling_bookings' => SlotBooking::count(),
        ];
        
        // Daily input trend (last 7 days)
        $dailyTrend = InputSiswa::select(
                DB::raw('DATE(tanggal) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->where('tanggal', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        // Mood distribution (last 30 days)
        $moodDistribution = PemantauanEmosiSiswa::select('skor', DB::raw('COUNT(*) as count'))
            ->where('tanggal', '>=', now()->subDays(30))
            ->groupBy('skor')
            ->orderBy('skor')
            ->get();
        
        // Recent analyses
        $recentAnalyses = AnalisisEntry::with(['siswaKelas.siswa.user', 'siswaKelas.kelas'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
        
        // Active konseling bookings
        $activeBookings = SlotBooking::with(['slot.guru.user', 'siswaKelas.siswa.user', 'siswaKelas.kelas'])
            ->where('status', 'booked')
            ->whereHas('slot', function($q) {
                $q->where('status', 'published')
                  ->where('start_at', '>=', now());
            })
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();
        
        // Pending password reset requests (last 7 days)
        $resetRequests = User::whereNotNull('reset_requested_at')
            ->where('reset_requested_at', '>=', now()->subDays(7))
            ->whereNull('deleted_at')
            ->orderByDesc('reset_requested_at')
            ->limit(25)
            ->get();

        return view('roles.admin.dashboard.index', compact(
            'stats', 
            'dailyTrend', 
            'moodDistribution', 
            'recentAnalyses', 
            'activeBookings',
            'activeTa',
            'resetRequests'
        ));
    }
    
    /**
     * Login history - all user login/logout activities
     */
    public function loginHistory(Request $request)
    {
        $role = $request->input('role');
        $search = trim((string) $request->input('search'));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        
        $histories = UserLoginHistory::with(['user.guru'])
            ->when($role, function($q) use ($role) {
                $q->whereHas('user', fn($u) => $u->where('role', $role));
            })
            ->when($search, function($q) use ($search) {
                $q->whereHas('user', function($u) use ($search) {
                    $like = "%{$search}%";
                    $u->where('name', 'like', $like)
                      ->orWhere('identifier', 'like', $like)
                      ->orWhere('email', 'like', $like);
                });
            })
            ->when($dateFrom, fn($q) => $q->where('logged_in_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('logged_in_at', '<=', $dateTo . ' 23:59:59'))
            ->orderByDesc('logged_in_at')
            ->paginate(50)
            ->withQueryString();
        
        // Summary statistics
        $summary = [
            'total_logins' => UserLoginHistory::count(),
            'today_logins' => UserLoginHistory::whereDate('logged_in_at', today())->count(),
            'active_sessions' => UserLoginHistory::whereNull('logged_out_at')->count(),
        ];
        
        return view('roles.admin.dashboard.login-history', compact('histories', 'summary', 'role', 'search', 'dateFrom', 'dateTo'));
    }

    /**
     * Refleksi history - all student reflections (personal + friend reports)
     */
    public function refleksiHistory(Request $request)
    {
        $search = trim((string) $request->input('search'));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $kelasId = $request->input('kelas_id');
        $jenis = $request->input('jenis'); // all | pribadi | teman
        $friendOnly = $request->boolean('friend_only', false); // backward compat

        $refleksis = InputSiswa::with(['siswaKelas.siswa.user', 'siswaKelas.kelas', 'siswaDilaporKelas.siswa.user'])
            ->when($dateFrom, fn($q) => $q->whereDate('tanggal', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('tanggal', '<=', $dateTo))
            ->when($kelasId, function($q) use ($kelasId) {
                $q->whereHas('siswaKelas', fn($sk) => $sk->where('kelas_id', $kelasId));
            })
            ->when($jenis === 'pribadi', fn($q) => $q->where('is_friend', false))
            ->when($jenis === 'teman' || $friendOnly, fn($q) => $q->where('is_friend', true))
            ->when($search, function($q) use ($search) {
                $q->whereHas('siswaKelas.siswa.user', function($uq) use ($search) {
                    $like = "%{$search}%";
                    $uq->where('name', 'like', $like)
                       ->orWhere('identifier', 'like', $like);
                });
            })
            ->orderByDesc('tanggal')
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $summary = [
            'total' => InputSiswa::count(),
            'today' => InputSiswa::whereDate('tanggal', today())->count(),
            'friend_reports' => InputSiswa::where('is_friend', true)->count(),
        ];

        // For kelas filter dropdown
        $kelasList = DB::table('kelass')->whereNull('deleted_at')->orderBy('tingkat')->orderBy('rombel')->get();

        return view('roles.admin.dashboard.refleksi-history', compact('refleksis', 'summary', 'search', 'dateFrom', 'dateTo', 'kelasId', 'kelasList', 'jenis'));
    }

    /**
     * Mood history - all mood tracking entries
     */
    public function moodHistory(Request $request)
    {
        $search = trim((string) $request->input('search'));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $kelasId = $request->input('kelas_id');
        $minSkor = $request->input('min_skor');
        $maxSkor = $request->input('max_skor');

        $moods = PemantauanEmosiSiswa::with(['siswaKelas.siswa.user', 'siswaKelas.kelas'])
            ->when($dateFrom, fn($q) => $q->whereDate('tanggal', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('tanggal', '<=', $dateTo))
            ->when($kelasId, function($q) use ($kelasId) {
                $q->whereHas('siswaKelas', fn($sk) => $sk->where('kelas_id', $kelasId));
            })
            ->when(strlen((string)$minSkor) > 0, fn($q) => $q->where('skor', '>=', (int) $minSkor))
            ->when(strlen((string)$maxSkor) > 0, fn($q) => $q->where('skor', '<=', (int) $maxSkor))
            ->when($search, function($q) use ($search) {
                $q->whereHas('siswaKelas.siswa.user', function($uq) use ($search) {
                    $like = "%{$search}%";
                    $uq->where('name', 'like', $like)
                       ->orWhere('identifier', 'like', $like);
                });
            })
            ->orderByDesc('tanggal')
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $summary = [
            'total' => PemantauanEmosiSiswa::count(),
            'today' => PemantauanEmosiSiswa::whereDate('tanggal', today())->count(),
            'avg30' => round((float) PemantauanEmosiSiswa::where('tanggal', '>=', now()->subDays(30))->avg('skor'), 2),
        ];

        // For kelas filter dropdown
        $kelasList = DB::table('kelass')->whereNull('deleted_at')->orderBy('tingkat')->orderBy('rombel')->get();

        return view('roles.admin.dashboard.mood-history', compact('moods', 'summary', 'search', 'dateFrom', 'dateTo', 'kelasId', 'minSkor', 'maxSkor', 'kelasList'));
    }
    
    /**
     * User activity detail - show all activities of a specific user
     */
    public function userActivity(Request $request, $userId)
    {
        $user = User::with(['siswa', 'guru'])->findOrFail($userId);
        
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        
        $activities = [];
        
        // If user is siswa
        if ($user->role === 'siswa' && $user->siswa) {
            $siswaKelasIds = DB::table('siswa_kelass')
                ->where('siswa_id', $user->id)
                ->pluck('id');
            
            // Refleksi pribadi
            $refleksi = InputSiswa::whereIn('siswa_kelas_id', $siswaKelasIds)
                ->when($dateFrom, fn($q) => $q->where('tanggal', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->where('tanggal', '<=', $dateTo))
                ->orderByDesc('tanggal')
                ->get()
                ->map(fn($r) => [
                    'type' => 'Refleksi Siswa',
                    'date' => $r->tanggal,
                    'description' => 'Kondisi: ' . ($r->kondisi_siswa ?? '-') . ($r->is_friend ? ' (Laporan Teman)' : ''),
                    'created_at' => $r->created_at,
                ]);
            
            // Mood tracking
            $moodTracking = PemantauanEmosiSiswa::whereIn('siswa_kelas_id', $siswaKelasIds)
                ->when($dateFrom, fn($q) => $q->where('tanggal', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->where('tanggal', '<=', $dateTo))
                ->orderByDesc('tanggal')
                ->get()
                ->map(fn($m) => [
                    'type' => 'Mood Tracking',
                    'date' => $m->tanggal,
                    'description' => "Sesi {$m->sesi}: Skor {$m->skor}/5" . ($m->catatan ? " - {$m->catatan}" : ''),
                    'created_at' => $m->created_at,
                ]);
            
            // Konseling bookings
            $bookings = SlotBooking::whereIn('siswa_kelas_id', $siswaKelasIds)
                ->with('slot.guru')
                ->when($dateFrom, fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->whereDate('created_at', '<=', $dateTo))
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($b) => [
                    'type' => 'Booking Konseling',
                    'date' => optional($b->slot)->tanggal ?? $b->created_at->format('Y-m-d'),
                    'description' => "Status: {$b->status}" . (optional($b->slot)->guru ? " - " . $b->slot->guru->name : ''),
                    'created_at' => $b->created_at,
                ]);
            
            $activities = collect()
                ->merge($refleksi)
                ->merge($moodTracking)
                ->merge($bookings)
                ->sortByDesc('created_at')
                ->values();
        }
        
        // If user is guru
        if ($user->role === 'guru' && $user->guru) {
            $observasi = InputGuru::where('guru_id', $user->id)
                ->when($dateFrom, fn($q) => $q->where('tanggal', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->where('tanggal', '<=', $dateTo))
                ->with('siswaKelas.siswa.user')
                ->orderByDesc('tanggal')
                ->get()
                ->map(fn($o) => [
                    'type' => 'Observasi Guru',
                    'date' => $o->tanggal,
                    'description' => 'Kondisi: ' . ($o->kondisi_siswa ?? '-') . ' - Siswa: ' . optional(optional($o->siswaKelas)->siswa->user)->name,
                    'created_at' => $o->created_at,
                ]);
            
            $activities = $observasi->sortByDesc('created_at')->values();
        }
        
        // Login history
        $loginHistory = UserLoginHistory::where('user_id', $userId)
            ->when($dateFrom, fn($q) => $q->whereDate('logged_in_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('logged_in_at', '<=', $dateTo))
            ->orderByDesc('logged_in_at')
            ->limit(50)
            ->get();
        
        return view('roles.admin.dashboard.user-activity', compact('user', 'activities', 'loginHistory', 'dateFrom', 'dateTo'));
    }
    
    /**
     * Audit logs - track important data changes (read-only)
     */
    public function auditLogs(Request $request)
    {
        $type = $request->input('type');
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        
        $logs = collect();
        
        // Recent user changes
        if (!$type || $type === 'users') {
            $userLogs = User::select('id', 'role', 'name', 'email', 'created_at', 'updated_at')
                ->when($search, function($q) use ($search) {
                    $like = "%{$search}%";
                    $q->where('name', 'like', $like)
                      ->orWhere('email', 'like', $like);
                })
                ->when($dateFrom, fn($q) => $q->where('updated_at', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->where('updated_at', '<=', $dateTo . ' 23:59:59'))
                ->latest('updated_at')
                ->limit(50)
                ->get()
                ->map(fn($u) => [
                    'type' => 'User',
                    'action' => $u->created_at->eq($u->updated_at) ? 'Created' : 'Updated',
                    'description' => "{$u->name} ({$u->role})",
                    'timestamp' => $u->updated_at,
                    'details' => "Email: {$u->email}",
                ]);
            $logs = $logs->merge($userLogs);
        }
        
        // Recent kelas changes
        if (!$type || $type === 'kelas') {
            $kelasLogs = DB::table('kelass')
                ->join('tahun_ajarans', 'kelass.tahun_ajaran_id', '=', 'tahun_ajarans.id')
                ->leftJoin('jurusans', 'kelass.jurusan_id', '=', 'jurusans.id')
                ->select(
                    'kelass.id',
                    'kelass.tingkat',
                    'kelass.rombel',
                    'jurusans.nama as jurusan_nama',
                    'tahun_ajarans.nama as ta_nama',
                    'kelass.created_at',
                    'kelass.updated_at'
                )
                ->when($dateFrom, fn($q) => $q->where('kelass.updated_at', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->where('kelass.updated_at', '<=', $dateTo . ' 23:59:59'))
                ->whereNull('kelass.deleted_at')
                ->latest('kelass.updated_at')
                ->limit(50)
                ->get()
                ->map(fn($k) => [
                    'type' => 'Kelas',
                    'action' => $k->created_at == $k->updated_at ? 'Created' : 'Updated',
                    'description' => "{$k->tingkat} " . ($k->jurusan_nama ?? '') . " {$k->rombel}",
                    'timestamp' => Carbon::parse($k->updated_at),
                    'details' => "TA: {$k->ta_nama}",
                ]);
            $logs = $logs->merge($kelasLogs);
        }
        
        // Recent siswa-kelas assignments
        if (!$type || $type === 'siswa_kelas') {
            $siswaKelasLogs = DB::table('siswa_kelass')
                ->join('users', 'siswa_kelass.siswa_id', '=', 'users.id')
                ->join('kelass', 'siswa_kelass.kelas_id', '=', 'kelass.id')
                ->select(
                    'siswa_kelass.id',
                    'users.name as siswa_name',
                    'kelass.tingkat',
                    'kelass.rombel',
                    'siswa_kelass.is_active',
                    'siswa_kelass.created_at',
                    'siswa_kelass.updated_at'
                )
                ->when($dateFrom, fn($q) => $q->where('siswa_kelass.updated_at', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->where('siswa_kelass.updated_at', '<=', $dateTo . ' 23:59:59'))
                ->latest('siswa_kelass.updated_at')
                ->limit(50)
                ->get()
                ->map(fn($sk) => [
                    'type' => 'Siswa-Kelas',
                    'action' => $sk->created_at == $sk->updated_at ? 'Assigned' : 'Updated',
                    'description' => "{$sk->siswa_name} → {$sk->tingkat} {$sk->rombel}",
                    'timestamp' => Carbon::parse($sk->updated_at),
                    'details' => 'Status: ' . ($sk->is_active ? 'Active' : 'Inactive'),
                ]);
            $logs = $logs->merge($siswaKelasLogs);
        }
        
        // Sort all logs by timestamp
        $logs = $logs->sortByDesc('timestamp')->values();
        
        // Paginate manually
        $perPage = 50;
        $currentPage = $request->input('page', 1);
        $pagedLogs = $logs->forPage($currentPage, $perPage);
        
        return view('roles.admin.dashboard.audit-logs', compact('logs', 'pagedLogs', 'type', 'search', 'dateFrom', 'dateTo'));
    }
}
