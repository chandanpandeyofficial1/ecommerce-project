import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/api_client.dart';
import '../utils/validators.dart' as v;
import '../widgets/common.dart';

// Sign up form for a new customer.
class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  bool _busy = false;
  bool _hide = true;
  Map<String, String> _errors = {};

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  // Drops the server error of a field once the user edits it.
  void _clear(String key) {
    if (_errors.containsKey(key)) {
      setState(() => _errors = {..._errors}..remove(key));
    }
  }

  // Validates, registers and shows server errors under each field.
  Future<void> _submit() async {
    if (_busy || !_formKey.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _errors = {};
    });
    try {
      await context.read<AuthProvider>().register(_name.text.trim(),
          _email.text.trim(), _phone.text.trim(), _password.text, _confirm.text);
      // The auth gate swaps to home, so close this screen.
      if (mounted) Navigator.pop(context);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _errors = e.fieldErrors);
      if (e.fieldErrors.isEmpty) showMessage(context, e.message);
    }
    if (mounted) setState(() => _busy = false);
  }

  // Builds one form field with its validator and server error.
  Widget _field(String label, TextEditingController c, String key,
      {required String? Function(String?) validator,
      bool obscure = false,
      TextInputType? type,
      TextInputAction action = TextInputAction.next,
      Iterable<String>? hints,
      int maxLength = 255,
      List<TextInputFormatter>? formatters}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: TextFormField(
        controller: c,
        obscureText: obscure && _hide,
        keyboardType: type,
        textInputAction: action,
        autofillHints: hints,
        maxLength: maxLength,
        inputFormatters: formatters,
        autovalidateMode: AutovalidateMode.onUserInteraction,
        validator: validator,
        onChanged: (_) => _clear(key),
        onFieldSubmitted:
            action == TextInputAction.done ? (_) => _submit() : null,
        decoration: InputDecoration(
          labelText: label,
          counterText: '',
          errorText: _errors[key],
          suffixIcon: obscure
              ? IconButton(
                  onPressed: () => setState(() => _hide = !_hide),
                  icon: Icon(_hide
                      ? Icons.visibility_outlined
                      : Icons.visibility_off_outlined))
              : null,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Create account')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Form(
          key: _formKey,
          child: Column(
            children: [
              _field('Name', _name, 'name',
                  validator: v.name,
                  type: TextInputType.name,
                  hints: const [AutofillHints.name]),
              _field('Email', _email, 'email',
                  validator: v.email,
                  type: TextInputType.emailAddress,
                  hints: const [AutofillHints.email],
                  formatters: v.noSpaceFormatters),
              _field('Phone (optional)', _phone, 'phone',
                  validator: v.phoneOptional,
                  type: TextInputType.phone,
                  hints: const [AutofillHints.telephoneNumber],
                  maxLength: 15,
                  formatters: v.phoneFormatters),
              _field('Password', _password, 'password',
                  validator: v.password,
                  obscure: true,
                  hints: const [AutofillHints.newPassword]),
              // The server reports a mismatch on the password field.
              _field('Confirm password', _confirm, 'password_confirmation',
                  validator: v.confirmPassword(() => _password.text),
                  obscure: true,
                  action: TextInputAction.done,
                  hints: const [AutofillHints.newPassword]),
              const SizedBox(height: 8),
              BusyButton(busy: _busy, label: 'Register', onPressed: _submit),
            ],
          ),
        ),
      ),
    );
  }
}
