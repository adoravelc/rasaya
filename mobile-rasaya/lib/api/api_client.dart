import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart'
    show kIsWeb, defaultTargetPlatform, TargetPlatform, kDebugMode;
import 'package:shared_preferences/shared_preferences.dart';

class ApiResponse {
  final bool ok;
  final dynamic data;
  final String errorMessage;

  ApiResponse({required this.ok, this.data, this.errorMessage = ''});
}

class ApiClient {
  final Dio _dio;
  static const _kTokenKey = 'token';

  static const String _lanBase = 'http://192.168.1.10:8000/api'; // <- EDIT
  static const String _webBase = 'http://localhost:8000/api';
  static const String _androidEmuBase = 'http://10.0.2.2:8000/api';
  static const String _iosSimBase = 'http://127.0.0.1:8000/api';

  static String _resolveBaseUrl() {
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

  Future<ApiResponse> postMood(int skor, {String? gambar, DateTime? tanggal}) {
    final body = <String, dynamic>{
      'skor': skor,
      if (gambar != null) 'gambar': gambar,
      if (tanggal != null)
        'tanggal': tanggal.toIso8601String().split('T').first,
    };
    return post('/mood', body);
  }

  Future<ApiResponse> getMoodToday({DateTime? tanggal}) {
    final q = tanggal == null
        ? ''
        : '?tanggal=${tanggal.toIso8601String().split('T').first}';
    return get('/mood/today$q');
  }

  Future<ApiResponse> getMoodHistory({int page = 1, int perPage = 20}) {
    return get('/mood/history?page=$page&per_page=$perPage');
  }

  Future<ApiResponse> get(String path, {Map<String, dynamic>? query}) async {
    try {
      final response = await _dio.get(path, queryParameters: query);
      return ApiResponse(ok: true, data: response.data);
    } on DioException catch (e) {
      return ApiResponse(
        ok: false,
        errorMessage: _formatErrorMessage(e),
      );
    }
  }

  Future<ApiResponse> getRefleksiHistory({int page = 1, int perPage = 10}) {
    return get('/input-siswa?page=$page&per_page=$perPage');
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
