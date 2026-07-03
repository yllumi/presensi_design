<?php
/**
 * ROUTER PHP — DUMMY API PRESENSI KARYAWAN
 * ============================================
 * Jalankan: php -S localhost:8000 index.php
 * 
 * Menghandle semua endpoint API yang diharapkan oleh index.html
 * dan menyajikan file statis (HTML, CSS, JS, dll).
 */

// ============================================================
// KONFIGURASI DUMMY DATA
// ============================================================
// Ubah-ubah nilai di bawah ini untuk mensimulasikan berbagai
// skenario tanpa mengubah logika router.
// ============================================================

date_default_timezone_set('Asia/Jakarta');

$DUMMY = [
  // --- Data Karyawan ---
  'employee' => [
    'id'   => 1,
    'name' => 'Ahmad Faqih',
    'role' => 'Pengajar',
    'unit' => 'Tahfidz & Bahasa Arab',
  ],

  // --- Data Lokasi Kantor ---
  'office_location' => [
    'id'           => 1,
    'name'         => 'Pondok Pesantren Al-Barakah',
    'latitude'     => -6.917464,
    'longitude'    => 107.619123,
    'radius_meter' => 150,
  ],

  // --- Riwayat Presensi (30 hari terakhir) ---
  'history' => [],

  // --- Apakah hari ini sudah check-in? ---
  'checked_in'  => false,

  // --- Apakah hari ini sudah check-out? ---
  'checked_out' => false,

  // --- Waktu check-in hari ini (null = belum) ---
  'check_in_time'  => null,

  // --- Waktu check-out hari ini (null = belum) ---
  'check_out_time' => null,

  // --- Jarak saat check-in (meter) ---
  'check_in_distance_meter' => null,

  // --- Jarak saat check-out (meter) ---
  'check_out_distance_meter' => null,

  // --- Status presensi hari ini: "hadir" | "terlambat" | null ---
  'status' => null,
];

// ============================================================
// GENERATE DUMMY HISTORY (30 hari ke belakang)
// ============================================================
$statuses = ['hadir', 'hadir', 'hadir', 'terlambat', 'hadir', 'hadir', 'hadir', 'hadir', 'terlambat', 'hadir'];
for ($i = 1; $i <= 30; $i++) {
  $date = date('Y-m-d', strtotime("-{$i} days"));
  // Skip hari Sabtu & Minggu
  $dow = date('w', strtotime($date));
  if ($dow == 0 || $dow == 6) continue;

  $status = $statuses[array_rand($statuses)];
  $hIn  = $status === 'terlambat' ? sprintf('07:%02d', rand(15, 45)) : sprintf('07:%02d', rand(00, 10));
  $hOut = sprintf('15:%02d', rand(00, 30));
  $distIn  = rand(10, $status === 'terlambat' ? 180 : 80);
  $distOut = rand(15, 90);

  $DUMMY['history'][] = [
    'date'                      => $date,
    'check_in_time'             => $hIn,
    'check_out_time'            => $hOut,
    'status'                    => $status,
    'check_in_distance_meter'   => $distIn,
    'check_out_distance_meter'  => $distOut,
  ];
}

// ============================================================
// ROUTER
// ============================================================
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// CORS & JSON header default untuk endpoint API
$isApi = strpos($uri, '/api') === 0;

if ($isApi) {
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Accept, X-CSRF-TOKEN');
  header('Content-Type: application/json; charset=utf-8');

  if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
  }
}

// --- ROUTING ---
switch (true) {

  // --------------------------------------------------------
  // GET /api/me
  // --------------------------------------------------------
  case $method === 'GET' && $uri === '/api/me':
    echo json_encode($DUMMY['employee']);
    exit;

  // --------------------------------------------------------
  // GET /api/office-locations/active
  // --------------------------------------------------------
  case $method === 'GET' && $uri === '/api/office-locations/active':
    echo json_encode($DUMMY['office_location']);
    exit;

  // --------------------------------------------------------
  // GET /api/attendance/today
  // --------------------------------------------------------
  case $method === 'GET' && $uri === '/api/attendance/today':
    echo json_encode([
      'checked_in'              => $DUMMY['checked_in'],
      'checked_out'             => $DUMMY['checked_out'],
      'check_in_time'           => $DUMMY['check_in_time'],
      'check_out_time'          => $DUMMY['check_out_time'],
      'check_in_distance_meter' => $DUMMY['check_in_distance_meter'],
      'status'                  => $DUMMY['status'],
    ]);
    exit;

  // --------------------------------------------------------
  // POST /api/attendance/check-in
  // --------------------------------------------------------
  case $method === 'POST' && $uri === '/api/attendance/check-in':
    $input = json_decode(file_get_contents('php://input'), true);
    $lat  = $input['latitude']  ?? null;
    $lng  = $input['longitude'] ?? null;

    // Hitung jarak dummy dari koordinat yang dikirim
    $dist = $lat && $lng
      ? dummyDistanceMeters($lat, $lng, $DUMMY['office_location']['latitude'], $DUMMY['office_location']['longitude'])
      : 0;

    $radius = $DUMMY['office_location']['radius_meter'];

    if ($dist > $radius) {
      http_response_code(422);
      echo json_encode([
        'success' => false,
        'message' => "Anda berada di luar radius ({$dist} m). Maksimal {$radius} m dari pesantren.",
      ]);
      exit;
    }

    // Simulasi: status "terlambat" jika setelah jam 07:15
    $now   = time();
    $jam   = (int)date('H', $now);
    $menit = (int)date('i', $now);
    $batas = 7 * 60 + 15; // 07:15
    $sekarang = $jam * 60 + $menit;

    $status = $sekarang > $batas ? 'terlambat' : 'hadir';
    $time   = date('H:i');

    // Update state dummy (hanya bertahan selama request berlangsung)
    // Untuk simulasi berkelanjutan pakai session, untuk sementara ini
    // cukup return response saja.
    echo json_encode([
      'success'       => true,
      'check_in_time' => $time,
      'distance_meter'=> $dist,
      'status'        => $status,
    ]);
    exit;

  // --------------------------------------------------------
  // POST /api/attendance/check-out
  // --------------------------------------------------------
  case $method === 'POST' && $uri === '/api/attendance/check-out':
    $input = json_decode(file_get_contents('php://input'), true);
    $lat = $input['latitude']  ?? null;
    $lng = $input['longitude'] ?? null;

    $dist = $lat && $lng
      ? dummyDistanceMeters($lat, $lng, $DUMMY['office_location']['latitude'], $DUMMY['office_location']['longitude'])
      : 0;

    $radius = $DUMMY['office_location']['radius_meter'];

    if ($dist > $radius) {
      http_response_code(422);
      echo json_encode([
        'success' => false,
        'message' => "Anda berada di luar radius ({$dist} m). Maksimal {$radius} m dari pesantren.",
      ]);
      exit;
    }

    echo json_encode([
      'success'        => true,
      'check_out_time' => date('H:i'),
      'distance_meter' => $dist,
    ]);
    exit;

  // --------------------------------------------------------
  // GET /api/attendance/history
  // --------------------------------------------------------
  case $method === 'GET' && $uri === '/api/attendance/history':
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 30;
    $data  = array_slice($DUMMY['history'], 0, $limit);
    echo json_encode($data);
    exit;

  // --------------------------------------------------------
  // DEFAULT: Sajikan file statis (HTML, CSS, JS, dll.)
  // --------------------------------------------------------
  default:
    if ($isApi) {
      http_response_code(404);
      echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
      exit;
    }
    // Untuk file statis, PHP built-in server handle sendiri
    return false;
}

// ============================================================
// HELPER
// ============================================================
function dummyDistanceMeters($lat1, $lon1, $lat2, $lon2) {
  $R = 6371000;
  $toRad = fn($d) => ($d * M_PI) / 180;
  $dLat = $toRad($lat2 - $lat1);
  $dLon = $toRad($lon2 - $lon1);
  $a = sin($dLat / 2) ** 2 +
       cos($toRad($lat1)) * cos($toRad($lat2)) * sin($dLon / 2) ** 2;
  return round($R * 2 * atan2(sqrt($a), sqrt(1 - $a)));
}
