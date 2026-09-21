import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user.dart';
import '../services/api_client.dart';

// Holds the login state and the saved token.
class AuthProvider extends ChangeNotifier {
  final ApiClient api;
  User? user;
  // False until the saved token has been checked once at startup.
  bool ready = false;

  AuthProvider(this.api) {
    // If the server rejects our token, drop the session locally.
    api.onUnauthorized = _clearSession;
  }

  bool get loggedIn => user != null;

  // Reads the saved token and asks the server who we are.
  Future<void> init() async {
    final saved = await _readToken();
    if (saved != null) {
      api.token = saved;
      try {
        final res = await api.get('/me');
        user = User.fromJson(res['data'] as Map<String, dynamic>);
      } catch (_) {
        api.token = null;
        await _saveToken(null);
      }
    }
    ready = true;
    notifyListeners();
  }

  // Logs in and stores the token. Throws ApiException on failure.
  Future<void> login(String email, String password) async {
    final res = await api.post('/login', {'email': email, 'password': password});
    await _acceptAuth(res['data'] as Map<String, dynamic>);
  }

  // Creates an account and logs in. Throws ApiException on failure.
  Future<void> register(String name, String email, String phone,
      String password, String confirm) async {
    final body = <String, dynamic>{
      'name': name,
      'email': email,
      'password': password,
      'password_confirmation': confirm,
    };
    if (phone.isNotEmpty) body['phone'] = phone;
    final res = await api.post('/register', body);
    await _acceptAuth(res['data'] as Map<String, dynamic>);
  }

  // Uploads a new profile photo and updates the user. Throws ApiException.
  Future<void> uploadAvatar(String filePath, String fileName) async {
    final res =
        await api.upload('/profile/avatar', 'avatar', filePath, fileName);
    user = User.fromJson(res['data'] as Map<String, dynamic>);
    notifyListeners();
  }

  // Removes the profile photo and updates the user. Throws ApiException.
  Future<void> removeAvatar() async {
    final res = await api.delete('/profile/avatar');
    user = User.fromJson(res['data'] as Map<String, dynamic>);
    notifyListeners();
  }

  // Tells the server to drop the token, then clears it locally either way.
  Future<void> logout() async {
    try {
      await api.post('/logout');
    } catch (_) {
      // Even if the call fails we still want to sign out on this device.
    }
    await _clearSession();
  }

  // Stores the token and user from a login or register response.
  Future<void> _acceptAuth(Map<String, dynamic> data) async {
    api.token = data['token'] as String;
    await _saveToken(api.token);
    user = User.fromJson(data['user'] as Map<String, dynamic>);
    notifyListeners();
  }

  // Forgets the token and user.
  Future<void> _clearSession() async {
    api.token = null;
    user = null;
    await _saveToken(null);
    notifyListeners();
  }

  // Preferences can fail on some devices, so errors are ignored.
  Future<String?> _readToken() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getString('token');
    } catch (_) {
      return null;
    }
  }

  // Saves the token, or removes it when null.
  Future<void> _saveToken(String? token) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      if (token == null) {
        await prefs.remove('token');
      } else {
        await prefs.setString('token', token);
      }
    } catch (_) {
      // Not being able to save only means the user logs in again next time.
    }
  }
}
