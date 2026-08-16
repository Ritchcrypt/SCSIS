import 'package:flutter/material.dart';

import '../services/auth_service.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({required this.authService, super.key});

  final AuthService authService;

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _emailController = TextEditingController();

  bool _sending = false;
  String? _statusMessage;
  String? _errorMessage;

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _sendResetLink() async {
    FocusScope.of(context).unfocus();

    if (_sending || !(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    setState(() {
      _sending = true;
      _statusMessage = null;
      _errorMessage = null;
    });

    try {
      final message = await widget.authService.requestPasswordReset(
        email: _emailController.text,
      );

      if (!mounted) {
        return;
      }

      setState(() {
        _statusMessage = message;
      });
    } on AuthException catch (exception) {
      if (!mounted) {
        return;
      }

      setState(() {
        _errorMessage = exception.userMessage;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        _errorMessage =
            'Unable to connect to TabangNow right now. '
            'Please try again.';
      });
    } finally {
      if (mounted) {
        setState(() {
          _sending = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF4F7FB),
      appBar: AppBar(
        backgroundColor: const Color(0xFFF4F7FB),
        elevation: 0,
        title: const Text('Forgot password'),
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(22, 24, 22, 32),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 560),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.fromLTRB(26, 30, 26, 28),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(28),
                  border: Border.all(color: const Color(0xFFD8E0EA)),
                ),
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      const Text(
                        'Reset your password',
                        style: TextStyle(
                          color: Color(0xFF0F172A),
                          fontSize: 28,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 10),
                      const Text(
                        'Enter the email address registered '
                        'with TabangNow. If the account exists, '
                        'a password reset link will be sent to '
                        'that email.',
                        style: TextStyle(
                          color: Color(0xFF64748B),
                          fontSize: 16,
                          height: 1.45,
                        ),
                      ),
                      const SizedBox(height: 28),
                      TextFormField(
                        controller: _emailController,

                        enabled: !_sending,
                        keyboardType: TextInputType.emailAddress,
                        textInputAction: TextInputAction.done,
                        autofillHints: const <String>[AutofillHints.email],
                        onFieldSubmitted: (_) => _sendResetLink(),
                        keyboardAppearance: Brightness.light,
                        style: const TextStyle(
                          color: Color(0xFF0F172A),
                          fontSize: 16,
                          fontWeight: FontWeight.w500,
                        ),
                        cursorColor: Color(0xFF2563EB),
                        decoration: const InputDecoration(
                          filled: true,
                          fillColor: Colors.white,
                          labelText: 'Email address',
                          hintText: 'email@example.com',
                          labelStyle: TextStyle(color: Color(0xFF475569)),
                          hintStyle: TextStyle(color: Color(0xFF94A3B8)),
                          prefixIcon: Icon(
                            Icons.email_outlined,
                            color: Color(0xFF64748B),
                          ),
                          border: OutlineInputBorder(),
                          enabledBorder: OutlineInputBorder(
                            borderSide: BorderSide(color: Color(0xFFCBD5E1)),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderSide: BorderSide(
                              color: Color(0xFF2563EB),
                              width: 2,
                            ),
                          ),
                        ),
                        validator: (value) {
                          final email = value?.trim() ?? '';

                          if (email.isEmpty) {
                            return 'Enter your email address.';
                          }

                          if (!RegExp(
                            r'^[^@\s]+@[^@\s]+\.[^@\s]+$',
                          ).hasMatch(email)) {
                            return 'Enter a valid email address.';
                          }

                          return null;
                        },
                      ),
                      if (_statusMessage != null) ...<Widget>[
                        const SizedBox(height: 16),
                        Text(
                          _statusMessage!,
                          style: const TextStyle(
                            color: Color(0xFF166534),
                            fontWeight: FontWeight.w700,
                            height: 1.4,
                          ),
                        ),
                      ],
                      if (_errorMessage != null) ...<Widget>[
                        const SizedBox(height: 16),
                        Text(
                          _errorMessage!,
                          style: const TextStyle(
                            color: Color(0xFFB91C1C),
                            fontWeight: FontWeight.w700,
                            height: 1.4,
                          ),
                        ),
                      ],
                      const SizedBox(height: 24),
                      FilledButton(
                        onPressed: _sending ? null : _sendResetLink,
                        child: _sending
                            ? const SizedBox(
                                width: 22,
                                height: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.5,
                                  color: Colors.white,
                                ),
                              )
                            : const Text('Email password reset link'),
                      ),
                      const SizedBox(height: 10),
                      TextButton(
                        onPressed: _sending
                            ? null
                            : () => Navigator.of(context).pop(),
                        child: const Text('Back to log in'),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
