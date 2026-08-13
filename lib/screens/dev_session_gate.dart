import 'package:flutter/material.dart';

import '../services/auth_service.dart';
import 'home_screen.dart';
import 'login_screen.dart';

class DevSessionGate extends StatefulWidget {
  const DevSessionGate({super.key});

  @override
  State<DevSessionGate> createState() => _DevSessionGateState();
}

class _DevSessionGateState extends State<DevSessionGate> {
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

    final token = await _authService.getToken();

    if (token == null || token.isEmpty) {
      if (!mounted) {
        return;
      }

      setState(() {
        _loading = false;
        _user = null;
      });
      return;
    }

    try {
      // Dashboard is already an authenticated endpoint and
      // returns the authenticated user in data.user.
      final response = await _authService.dashboard();
      final rawData = response['data'];
      final data = rawData is Map
          ? Map<String, dynamic>.from(rawData)
          : <String, dynamic>{};
      final rawUser = data['user'];

      if (rawUser is! Map) {
        throw const AuthException(
          'Authenticated user data was unavailable.',
          statusCode: 401,
        );
      }

      if (!mounted) {
        return;
      }

      setState(() {
        _user = Map<String, dynamic>.from(rawUser);
        _loading = false;
      });
    } on AuthException catch (exception) {
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

      if (!mounted) {
        return;
      }

      setState(() {
        _loading = false;
        _error = exception.message;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        _loading = false;
        _error = 'Unable to reach the TabangNow MobileAPI.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(
        backgroundColor: Color(0xFFF8FAFC),
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (_user != null) {
      return HomeScreen(user: _user!);
    }

    if (_error != null) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8FAFC),
        body: SafeArea(
          child: Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  const Icon(
                    Icons.cloud_off_rounded,
                    size: 50,
                    color: Color(0xFF64748B),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    _error!,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: Color(0xFF334155),
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 18),
                  FilledButton.icon(
                    onPressed: _restoreSession,
                    icon: const Icon(Icons.refresh_rounded),
                    label: const Text('Retry'),
                  ),
                  const SizedBox(height: 8),
                  TextButton(
                    onPressed: () async {
                      await _authService.clearToken();

                      if (!mounted) {
                        return;
                      }

                      setState(() {
                        _error = null;
                        _user = null;
                      });
                    },
                    child: const Text('Open Login'),
                  ),
                ],
              ),
            ),
          ),
        ),
      );
    }

    return const LoginScreen();
  }
}
