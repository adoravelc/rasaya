import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart'
    show kIsWeb, defaultTargetPlatform, TargetPlatform, kDebugMode;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:image_picker/image_picker.dart';

class ApiResponse {
  final bool ok;
  final dynamic data;
  final String errorMessage;

  ApiResponse({required this.ok, this.data, this.errorMessage = ''});
}

class ApiClient {
  final Dio _dio;
  static const _kTokenKey = 'token';

  // Allow override via --dart-define=API_BASE_URL=http://<host>:<port>/api
  static const String _envBase = String.fromEnvironment('API_BASE_URL');
  static const String _webBase = 'http://127.0.0.1:8000/api';
  static const String _androidEmuBase = 'http://10.0.2.2:8000/api';
  static const String _iosSimBase = 'http://127.0.0.1:8000/api';

  static String _resolveBaseUrl() {
    if (_envBase.isNotEmpty) return _envBase;
    if (kIsWeb) return _webBase;
    if (defaultTargetPlatform == TargetPlatform.android) return _androidEmuBase;
    return _iosSimBase;
  }

  ApiClient({String? token})
      : _dio = Dio(BaseOptions(
          baseUrl: _resolveBaseUrl(),
          connectTimeout: const Duration(seconds: 10),
          receiveTimeout: const Duration(seconds: 15),
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            if (token != null) 'Authorization': 'Bearer $token',
          },
        )) {
    // Add logging interceptor for debugging
    if (kDebugMode) {
      _dio.interceptors.add(LogInterceptor(
        requestBody: true,
        responseBody: true,
      ));
    }

    // Handle 401 errors
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        // Get latest token if not provided in constructor
        if (token == null) {
          final prefs = await SharedPreferences.getInstance();
          final savedToken = prefs.getString(_kTokenKey);
          if (savedToken != null) {
            options.headers['Authorization'] = 'Bearer $savedToken';
          }
        }
        handler.next(options);
      },
      onError: (e, handler) async {
        if (e.response?.statusCode == 401) {
          final prefs = await SharedPreferences.getInstance();
          await prefs.remove(_kTokenKey);
          // You might want to trigger a logout event here
        }
        handler.next(e);
      },
    ));
  }

  Future<ApiResponse> post(String path, Map<String, dynamic> data) async {
    try {
      final response = await _dio.post(path, data: data);
      return ApiResponse(ok: true, data: response.data);
    } on DioException catch (e) {
      return ApiResponse(
        ok: false,
        errorMessage: _formatErrorMessage(e),
      );
    }
  }

  Future<ApiResponse> createInputSiswa({
    required String teks,
    DateTime? tanggal,
    List<int>? kategoriIds,
    int? siswaKelasId, // admin can pass; siswa resolved di backend jika null
    int? siswaDilaporKelasId,
    double? avgEmosi,
    String? gambar,
    int statusUpload = 1,
  }) {
    final body = <String, dynamic>{
      'teks': teks,
      if (tanggal != null)
        'tanggal': tanggal.toIso8601String().split('T').first,
      if (kategoriIds != null) 'kategori_ids': kategoriIds,
      if (siswaKelasId != null) 'siswa_kelas_id': siswaKelasId,
      if (siswaDilaporKelasId != null)
        'siswa_dilapor_kelas_id': siswaDilaporKelasId,
      if (avgEmosi != null) 'avg_emosi': avgEmosi,
      if (gambar != null) 'gambar': gambar,
      'status_upload': statusUpload,
    };
    return post('/input-siswa', body);
  }

  Future<ApiResponse> postMultipartFlexible(
    String path, {
    required Map<String, dynamic> fields,
    XFile? xfile, // untuk Android/iOS
    List<int>? bytes, // untuk Web
    String? filename, // untuk Web
    String fileField = 'gambar',
  }) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString(_kTokenKey);

      final form = FormData.fromMap(fields);
      if (xfile != null) {
        if (kIsWeb) {
          final b = await xfile.readAsBytes();
          form.files.add(MapEntry(
            fileField,
            MultipartFile.fromBytes(b, filename: xfile.name),
          ));
        } else {
          form.files.add(MapEntry(
            fileField,
            await MultipartFile.fromFile(xfile.path, filename: xfile.name),
          ));
        }
      } else if (bytes != null && filename != null) {
        form.files.add(MapEntry(
          fileField,
          MultipartFile.fromBytes(bytes, filename: filename),
        ));
      }

      final res = await _dio.post(
        path,
        data: form,
        options: Options(headers: {
          if (token != null) 'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        }),
      );
      return ApiResponse(ok: true, data: res.data);
    } on DioException catch (e) {
      final msg = e.response?.data is Map
          ? (e.response?.data['message'] ?? e.message)
          : e.message;
      return ApiResponse(ok: false, errorMessage: msg ?? 'Request failed');
    } catch (e) {
      return ApiResponse(ok: false, errorMessage: e.toString());
    }
  }

  Future<ApiResponse> postMood(
    int skor, {
    XFile? gambarFile, // Android/iOS
    List<int>? webBytes, // Web
    String? webFilename, // Web
    String? catatan,
    DateTime? tanggal,
  }) {
    final fields = <String, dynamic>{
      'skor': skor,
      if (catatan != null && catatan.isNotEmpty) 'catatan': catatan,
      if (tanggal != null)
        'tanggal': tanggal.toIso8601String().split('T').first,
    };
    return postMultipartFlexible(
      '/mood',
      fields: fields,
      xfile: kIsWeb ? null : gambarFile,
      bytes: kIsWeb ? webBytes : null,
      filename: kIsWeb ? webFilename : null,
      fileField: 'gambar',
    );
  }

  // Mood hari ini (untuk status cepat)
  Future<ApiResponse> getMoodToday({DateTime? tanggal}) {
    final q = tanggal == null
        ? null
        : {'tanggal': tanggal.toIso8601String().split('T').first};
    return get('/mood/today', query: q);
  }

  // Refleksi terbaru (ambil 1 teratas)
  Future<ApiResponse> getLatestRefleksi() {
    return get('/input-siswa', query: {'per_page': 1});
  }

  // Status refleksi hari ini (self vs friend)
  Future<ApiResponse> getRefleksiTodayStatus({int? siswaKelasId}) {
    final q = <String, dynamic>{
      if (siswaKelasId != null) 'siswa_kelas_id': siswaKelasId,
    };
    return get('/input-siswa/today-status', query: q);
  }

  Future<ApiResponse> getMoodHistory(
      {int page = 1,
      int perPage = 20,
      int? siswaKelasId,
      String? tanggalFrom,
      String? tanggalTo}) {
    final q = <String, dynamic>{
      'page': page,
      'per_page': perPage,
      if (siswaKelasId != null) 'siswa_kelas_id': siswaKelasId,
      if (tanggalFrom != null) 'tanggal_from': tanggalFrom,
      if (tanggalTo != null) 'tanggal_to': tanggalTo,
    };
    return get('/mood/history', query: q);
  }

  Future<ApiResponse> get(String path, {Map<String, dynamic>? query}) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString(_kTokenKey);
      final res = await _dio.get(
        path,
        queryParameters: query,
        options: Options(headers: {
          if (token != null) 'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        }),
      );
      return ApiResponse(ok: true, data: res.data);
    } on DioException catch (e) {
      final msg = e.response?.data is Map
          ? (e.response?.data['message'] ?? e.message)
          : e.message;
      return ApiResponse(ok: false, errorMessage: msg ?? 'Request failed');
    } catch (e) {
      return ApiResponse(ok: false, errorMessage: e.toString());
    }
  }

  Future<ApiResponse> getRefleksiHistory({int page = 1, int perPage = 10}) {
    return get('/input-siswa?page=$page&per_page=$perPage');
  }

  // ================== Booking Konseling (Siswa) ==================
  Future<ApiResponse> getAvailableSlots(
      {DateTime? from, DateTime? to, int perPage = 100}) {
    final q = <String, dynamic>{
      if (from != null) 'from': from.toIso8601String().split('T').first,
      if (to != null) 'to': to.toIso8601String().split('T').first,
      'per_page': perPage,
    };
    return get('/slots/available', query: q);
  }

  Future<ApiResponse> bookSlot(int slotId) {
    return post('/bookings', {'slot_id': slotId});
  }

  Future<ApiResponse> getMyBookings() {
    return get('/bookings/me');
  }

  Future<ApiResponse> cancelMyBooking(int bookingId, {String? reason}) {
    final body = <String, dynamic>{};
    if (reason != null && reason.trim().isNotEmpty) {
      body['reason'] = reason.trim();
    }
    return post('/bookings/$bookingId/cancel', body);
  }

  // Akun: ganti password sendiri
  Future<ApiResponse> changePassword({
    required String currentPassword,
    required String newPassword,
  }) {
    return post('/me/password', {
      'current_password': currentPassword,
      'new_password': newPassword,
    });
  }

  // Helper untuk format error message yang lebih konsisten
  String _formatErrorMessage(DioException e) {
    if (e.response?.data is Map) {
      final Map data = e.response!.data;
      if (data.containsKey('message')) {
        return data['message'].toString();
      }
      if (data.containsKey('error')) {
        return data['error'].toString();
      }
    }
    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return 'Koneksi timeout. Periksa internet Anda.';
      case DioExceptionType.badResponse:
        return 'Server error (${e.response?.statusCode})';
      default:
        return e.message ?? 'Terjadi kesalahan';
    }
  }

  Dio get dio => _dio;
  ApiClient withToken(String token) => ApiClient(token: token);
}
