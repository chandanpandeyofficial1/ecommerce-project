import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'providers/auth_provider.dart';
import 'providers/cart_provider.dart';
import 'providers/catalog_provider.dart';
import 'providers/order_provider.dart';
import 'providers/return_request_provider.dart';
import 'screens/home_shell.dart';
import 'screens/login_screen.dart';
import 'services/api_client.dart';

// Global key so we can pop back to the root when the session ends.
final navigatorKey = GlobalKey<NavigatorState>();

// App entry point.
void main() {
  runApp(const GroceryApp());
}

// Sets up the providers and the theme.
class GroceryApp extends StatelessWidget {
  const GroceryApp({super.key});

  @override
  Widget build(BuildContext context) {
    // One shared api client so every provider uses the same token.
    final api = ApiClient();
    return MultiProvider(
      providers: [
        Provider<ApiClient>.value(value: api),
        ChangeNotifierProvider(create: (_) => AuthProvider(api)..init()),
        ChangeNotifierProvider(create: (_) => CatalogProvider(api)),
        ChangeNotifierProvider(create: (_) => CartProvider(api)),
        ChangeNotifierProvider(create: (_) => OrderProvider(api)),
        ChangeNotifierProvider(create: (_) => ReturnRequestProvider(api)),
      ],
      child: MaterialApp(
        title: 'Mini Grocery',
        debugShowCheckedModeBanner: false,
        navigatorKey: navigatorKey,
        theme: _buildTheme(),
        home: const AuthGate(),
      ),
    );
  }

  // Green and teal Material 3 theme with rounded shapes.
  ThemeData _buildTheme() {
    final scheme = ColorScheme.fromSeed(
        seedColor: const Color(0xFF2E9E6B), secondary: const Color(0xFF00897B));
    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: const Color(0xFFF6FAF7),
      appBarTheme: const AppBarTheme(
          centerTitle: false, backgroundColor: Color(0xFFF6FAF7)),
      cardTheme: CardThemeData(
        elevation: 0,
        color: Colors.white,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(color: scheme.outlineVariant),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
    );
  }
}

// Shows a spinner while the token is checked, then home or login.
class AuthGate extends StatefulWidget {
  const AuthGate({super.key});

  @override
  State<AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<AuthGate> {
  bool _wasLoggedIn = false;

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    // When the user logs out (or gets a 401) clear per-user data and close
    // any screens that are still open above the gate.
    if (_wasLoggedIn && !auth.loggedIn) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        navigatorKey.currentState?.popUntil((r) => r.isFirst);
        if (!mounted) return;
        context.read<CartProvider>().reset();
        context.read<OrderProvider>().reset();
      });
    }
    _wasLoggedIn = auth.loggedIn;

    if (!auth.ready) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    return auth.loggedIn ? const HomeShell() : const LoginScreen();
  }
}
