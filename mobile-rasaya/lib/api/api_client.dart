import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart'
    show kIsWeb, defaultTargetPlatform, TargetPlatform;
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
    _dio.interceptors.add(InterceptorsWrapper(
      onError: (e, handler) async {
        if (e.response?.statusCode == 401) {
          final sp = await SharedPreferences.getInstance();
          await sp.remove(_kTokenKey);
        }
        handler.next(e);
      },
    ));
  }

  Future<ApiResponse> post(String path, Map<String, dynamic> data) async {
    try {
      final response = await _dio.post(path, data: data);
      return ApiResponse(ok: true, data: response.data);
    } on DioError catch (e) {
      return ApiResponse(
        ok: false,
        errorMessage: e.response?.data['message'] ?? e.message,
      );
    }
  }

  Future<ApiResponse> get(String path, {Map<String, dynamic>? query}) async {
    try {
      final response = await _dio.get(path, queryParameters: query);
      return ApiResponse(ok: true, data: response.data);
    } on DioError catch (e) {
      return ApiResponse(
        ok: false,
        errorMessage: e.response?.data['message'] ?? e.message,
      );
    }
  }

  Dio get dio => _dio;
  ApiClient withToken(String token) => ApiClient(token: token);
}
