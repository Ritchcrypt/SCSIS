import 'package:flutter/material.dart';

import '../services/auth_service.dart';
import 'home_screen.dart';
import 'login_screen.dart';

class AuthGate extends StatefulWidget {
  const AuthGate({super.key});

  @override
  State<AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<AuthGate> {
  final AuthService _authService = AuthService();

  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _user;

  @override
  void initState() {
    super.initState();
    _restoreSession();
  }

  Future<void> _restoreSession() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }

    try {
      final token = await _authService.getToken();

      if (token == null || token.isEmpty) {
        if (!mounted) {
          return;
        }

        setState(() {
          _loading = false;
          _user = null;
          _error = null;
        });

        return;
      }

      final response = await _authService.me();
      final rawUser = response['user'];

      if (rawUser is! Map) {
        throw const AuthException(
          'The authenticated account information was unavailable.',
          statusCode: 401,
        );
      }

      if (!mounted) {
        return;
      }

      setState(() {
        _user = Map<String, dynamic>.from(rawUser);
        _loading = false;
        _error = null;
      });
    } on AuthException catch (exception) {
      if (!mounted) {
        return;
      }

      if (exception.statusCode == 401 || exception.statusCode == 403) {
        await _authService.clearToken();

        if (!mounted) {
          return;
        }

        setState(() {
          _user = null;
          _loading = false;
          _error = null;
        });

        return;
      }

      setState(() {
        _user = null;
        _loading = false;
        _error = exception.message;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        _user = null;
        _loading = false;
        _error = 'Unable to verify the saved TabangNow session.';
      });
    }
  }

  Future<void> _useLoginInstead() async {
    await _authService.clearToken();

    if (!mounted) {
      return;
    }

    setState(() {
      _user = null;
      _loading = false;
      _error = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(
        body: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              CircularProgressIndicator(),
              SizedBox(height: 14),
              Text(
                'Checking secure session...',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ],
          ),
        ),
      );
    }

    if (_user != null) {
      return HomeScreen(user: _user!);
    }

    if (_error == null) {
      return const LoginScreen();
    }

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  const Icon(Icons.cloud_off_rounded, size: 52),
                  const SizedBox(height: 16),
                  const Text(
                    'Session check unavailable',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    _error!,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 18),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      onPressed: _restoreSession,
                      icon: const Icon(Icons.refresh_rounded),
                      label: const Text('Retry'),
                    ),
                  ),
                  const SizedBox(height: 10),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton(
                      onPressed: _useLoginInstead,
                      child: const Text('Log in instead'),
                    ),
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
