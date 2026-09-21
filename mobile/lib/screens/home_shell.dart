import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../providers/cart_provider.dart';
import '../providers/catalog_provider.dart';
import '../providers/order_provider.dart';
import 'cart_screen.dart';
import 'home_screen.dart';
import 'orders_screen.dart';
import 'profile_screen.dart';

// Main screen with the bottom navigation bar. Each tab has its own
// Navigator, so screens opened inside a tab keep the bar visible.
class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  // Shows the Home tab at its first screen. Full screen flows such as
  // checkout call this for "Back to shop".
  static void goHome() => _HomeShellState._current?._showHomeRoot();

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  // The shell that is currently on screen, used by goHome.
  static _HomeShellState? _current;

  int _index = 0;

  // One navigator key per tab: Home, Cart, Orders, Profile.
  final _navKeys = List.generate(4, (_) => GlobalKey<NavigatorState>());

  // Loads the first data once the user is logged in.
  @override
  void initState() {
    super.initState();
    _current = this;
    final catalog = context.read<CatalogProvider>();
    catalog.loadCategories();
    catalog.refresh();
    context.read<CartProvider>().load();
    context.read<OrderProvider>().refresh();
  }

  // Forget this shell when it leaves the screen.
  @override
  void dispose() {
    if (_current == this) _current = null;
    super.dispose();
  }

  // Pops the Home tab to its first screen and selects it.
  void _showHomeRoot() {
    _navKeys[0].currentState?.popUntil((r) => r.isFirst);
    setState(() => _index = 0);
  }

  // Tapping the current tab goes back to its first screen, another tab
  // is just shown (its own stack is kept).
  void _onTabSelected(int i) {
    if (i == _index) {
      _navKeys[i].currentState?.popUntil((r) => r.isFirst);
    } else {
      setState(() => _index = i);
    }
  }

  // Android back: pop the tab stack, then go to Home, then leave the app.
  void _onBack(bool didPop, Object? result) {
    if (didPop) return;
    final nav = _navKeys[_index].currentState;
    if (nav != null && nav.canPop()) {
      nav.pop();
    } else if (_index != 0) {
      setState(() => _index = 0);
    } else {
      SystemNavigator.pop();
    }
  }

  // Builds the navigator for one tab with its first screen.
  Widget _tab(int i, Widget first) {
    return Navigator(
      key: _navKeys[i],
      onGenerateRoute: (_) => MaterialPageRoute(builder: (_) => first),
    );
  }

  @override
  Widget build(BuildContext context) {
    final count = context.watch<CartProvider>().count;
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: _onBack,
      child: Scaffold(
        body: IndexedStack(
          index: _index,
          children: [
            _tab(0, const HomeScreen()),
            _tab(1, const CartScreen()),
            _tab(2, const OrdersScreen()),
            _tab(3, const ProfileScreen()),
          ],
        ),
        bottomNavigationBar: NavigationBar(
          selectedIndex: _index,
          onDestinationSelected: _onTabSelected,
          destinations: [
            const NavigationDestination(
              icon: Icon(Icons.home_outlined),
              label: 'Home',
            ),
            NavigationDestination(
              icon: Badge(
                isLabelVisible: count > 0,
                label: Text('$count'),
                child: const Icon(Icons.shopping_cart_outlined),
              ),
              label: 'Cart',
            ),
            const NavigationDestination(
              icon: Icon(Icons.receipt_long_outlined),
              label: 'Orders',
            ),
            const NavigationDestination(
              icon: Icon(Icons.person_outline),
              label: 'Profile',
            ),
          ],
        ),
      ),
    );
  }
}

// Closes every full screen page above the app (checkout, payment status)
// and shows the Home tab at its first screen.
void backToShop(BuildContext context) {
  Navigator.of(context, rootNavigator: true).popUntil((r) => r.isFirst);
  HomeShell.goHome();
}
