import 'package:dio/dio.dart';
import '../config/api_config.dart';

// Error thrown for any failed request, with a readable message and
// optional per-field validation messages.
class ApiException implements Exception {
  final String message;
  final Map<String, String> fieldErrors;
  final int? statusCode;

  ApiException(this.message, {this.fieldErrors = const {}, this.statusCode});

  @override
  String toString() => message;
}

// Thin wrapper around Dio that adds headers and the token, and turns
// errors into ApiException.
class ApiClient {
  final Dio _dio;
  String? token;

  // Called when the server says the token is no longer valid.
  void Function()? onUnauthorized;

  ApiClient()
      : _dio = Dio(BaseOptions(
          baseUrl: ApiConfig.baseUrl,
          connectTimeout: const Duration(seconds: 15),
          receiveTimeout: const Duration(seconds: 20),
          headers: {'Accept': 'application/json'},
        )) {
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) {
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
      onError: (e, handler) {
        // A 401 on login just means wrong password, so it is not a logout.
        final isLogin = e.requestOptions.path == '/login';
        if (e.response?.statusCode == 401 && !isLogin && token != null) {
          onUnauthorized?.call();
        }
        handler.next(e);
      },
    ));
  }

  // GET request returning the decoded json map.
  Future<Map<String, dynamic>> get(String path,
      {Map<String, dynamic>? query}) {
    return _send(() => _dio.get(path, queryParameters: query));
  }

  // POST request with a json body.
  Future<Map<String, dynamic>> post(String path, [Map<String, dynamic>? body]) {
    return _send(() => _dio.post(path, data: body));
  }

  // PUT request with a json body.
  Future<Map<String, dynamic>> put(String path, Map<String, dynamic> body) {
    return _send(() => _dio.put(path, data: body));
  }

  // DELETE request.
  Future<Map<String, dynamic>> delete(String path) {
    return _send(() => _dio.delete(path));
  }

  // POST a multipart form with one file, for example the profile photo.
  Future<Map<String, dynamic>> upload(
      String path, String field, String filePath, String fileName) {
    return _send(() async {
      final form = FormData.fromMap({
        field: await MultipartFile.fromFile(filePath, filename: fileName),
      });
      return _dio.post(path, data: form);
    });
  }

  // POST a multipart form with plain fields plus an optional file, for
  // example the return request form with an optional photo.
  Future<Map<String, dynamic>> postForm(
      String path, Map<String, dynamic> fields,
      {String? fileField, String? filePath, String? fileName}) {
    return _send(() async {
      final map = <String, dynamic>{...fields};
      if (filePath != null && fileField != null) {
        map[fileField] =
            await MultipartFile.fromFile(filePath, filename: fileName);
      }
      return _dio.post(path, data: FormData.fromMap(map));
    });
  }

  // Runs a request and converts Dio errors to ApiException.
  Future<Map<String, dynamic>> _send(Future<Response> Function() call) async {
    try {
      final res = await call();
      final data = res.data;
      return data is Map<String, dynamic> ? data : <String, dynamic>{};
    } on DioException catch (e) {
      throw _toException(e);
    }
  }

  // Reads the {message, errors} format the backend uses.
  ApiException _toException(DioException e) {
    final code = e.response?.statusCode;
    final data = e.response?.data;
    if (data is Map) {
      final fields = <String, String>{};
      final errors = data['errors'];
      if (errors is Map) {
        errors.forEach((key, value) {
          fields[key.toString()] = value is List && value.isNotEmpty
              ? value.first.toString()
              : value.toString();
        });
      }
      // The first field message is more specific than the summary.
      final message = fields.isNotEmpty
          ? fields.values.first
          : (data['message']?.toString() ?? 'Something went wrong.');
      return ApiException(message, fieldErrors: fields, statusCode: code);
    }
    if (code == null) {
      return ApiException('Cannot reach the server. Check your connection.');
    }
    return ApiException('Server error ($code).', statusCode: code);
  }
}
