import 'package:flutter/material.dart';

import '../services/auth_service.dart';
import 'home_screen.dart';

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
    _restoreOrCreateDevelopmentSession();
  }

  Future<void> _restoreOrCreateDevelopmentSession() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }

    final token = await _authService.getToken();

    if (token != null && token.isNotEmpty) {
      try {
        final response = await _authService.dashboard();
        final user = _userFromDashboard(response);

        if (!mounted) {
          return;
        }

        setState(() {
          _user = user;
          _loading = false;
        });

        return;
      } on AuthException catch (exception) {
        if (exception.statusCode != 401 && exception.statusCode != 403) {
          if (!mounted) {
            return;
          }

          setState(() {
            _loading = false;
            _error = exception.message;
          });

          return;
        }

        await _authService.clearToken();
      } catch (_) {
        if (!mounted) {
          return;
        }

        setState(() {
          _loading = false;
          _error = 'Unable to reach the TabangNow MobileAPI.';
        });

        return;
      }
    }

    await _createLocalDevelopmentSession();
  }

  Future<void> _createLocalDevelopmentSession() async {
    try {
      final response = await _authService.devSession();
      final rawUser = response['user'];

      if (rawUser is! Map) {
        throw const AuthException(
          'Development Admin user data was unavailable.',
          statusCode: 503,
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
        _error = 'Unable to create the local development session.';
      });
    }
  }

  Map<String, dynamic> _userFromDashboard(Map<String, dynamic> response) {
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

    return Map<String, dynamic>.from(rawUser);
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
                'Opening development session...',
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

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                const Icon(Icons.developer_mode_rounded, size: 52),
                const SizedBox(height: 16),
                const Text(
                  'Development session unavailable',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 8),
                Text(
                  _error ??
                      'The local development session could not be created.',
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 18),
                FilledButton.icon(
                  onPressed: _restoreOrCreateDevelopmentSession,
                  icon: const Icon(Icons.refresh_rounded),
                  label: const Text('Retry Development Session'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
