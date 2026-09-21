// Single place for the server address.
class ApiConfig {
  // Default target is a physical phone connected over USB, reached through
  // `adb reverse tcp:80 tcp:80` (so "localhost" on the phone means the PC).
  // To run on the Android emulator instead, pass
  // --dart-define=API_BASE_URL=http://10.0.2.2/code/ecommerce-project/backend/public/api
  // (10.0.2.2 is the host PC as seen from inside the emulator).
  // Over Wi-Fi instead of USB, use the PC's LAN IP instead of localhost.
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://localhost/code/ecommerce-project/backend/public/api',
  );
}
