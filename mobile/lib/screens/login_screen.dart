import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/api_client.dart';
import '../utils/validators.dart' as v;
import '../widgets/common.dart';
import 'register_screen.dart';

// Email and password login form.
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;
  bool _hide = true;
  Map<String, String> _errors = {};

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  // Drops the server error of a field once the user edits it.
  void _clear(String key) {
    if (_errors.containsKey(key)) {
      setState(() => _errors = {..._errors}..remove(key));
    }
  }

  // Validates, sends the login request and shows server errors under the fields.
  Future<void> _submit() async {
    if (_busy || !_formKey.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _errors = {};
    });
    try {
      await context.read<AuthProvider>().login(_email.text.trim(), _password.text);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _errors = e.fieldErrors);
      // Errors without a field (wrong password) go to a snackbar.
      if (e.fieldErrors.isEmpty) showMessage(context, e.message);
    }
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Icon(Icons.local_grocery_store,
                      size: 72, color: scheme.primary),
                  const SizedBox(height: 12),
                  Text('Mini Grocery',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                          fontWeight: FontWeight.bold, color: scheme.primary)),
                  const SizedBox(height: 4),
                  const Text('Fresh groceries at your door',
                      textAlign: TextAlign.center),
                  const SizedBox(height: 32),
                  TextFormField(
                    controller: _email,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.next,
                    autofillHints: const [AutofillHints.email],
                    maxLength: 255,
                    inputFormatters: v.noSpaceFormatters,
                    autovalidateMode: AutovalidateMode.onUserInteraction,
                    validator: v.email,
                    onChanged: (_) => _clear('email'),
                    decoration: InputDecoration(
                        labelText: 'Email',
                        counterText: '',
                        prefixIcon: const Icon(Icons.email_outlined),
                        errorText: _errors['email']),
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _password,
                    obscureText: _hide,
                    textInputAction: TextInputAction.done,
                    autofillHints: const [AutofillHints.password],
                    maxLength: 255,
                    autovalidateMode: AutovalidateMode.onUserInteraction,
                    validator: (x) => v.requiredField(x, 'Password'),
                    onChanged: (_) => _clear('password'),
                    onFieldSubmitted: (_) => _submit(),
                    decoration: InputDecoration(
                        labelText: 'Password',
                        counterText: '',
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                            onPressed: () => setState(() => _hide = !_hide),
                            icon: Icon(_hide
                                ? Icons.visibility_outlined
                                : Icons.visibility_off_outlined)),
                        errorText: _errors['password']),
                  ),
                  const SizedBox(height: 24),
                  BusyButton(busy: _busy, label: 'Login', onPressed: _submit),
                  const SizedBox(height: 12),
                  TextButton(
                    onPressed: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                            builder: (_) => const RegisterScreen())),
                    child: const Text('New here? Create an account'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
