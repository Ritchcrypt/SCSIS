import 'dart:typed_data';

import 'package:flutter/material.dart';

import '../core/tabangnow_theme.dart';
import '../services/auth_service.dart';
import '../services/user_management_service.dart';

class CurrentAccountProfileScreen extends StatefulWidget {
  const CurrentAccountProfileScreen({
    super.key,
    required this.authService,
    required this.fallbackUser,
  });

  final AuthService authService;
  final Map<String, dynamic> fallbackUser;

  @override
  State<CurrentAccountProfileScreen> createState() =>
      _CurrentAccountProfileScreenState();
}

class _CurrentAccountProfileScreenState
    extends State<CurrentAccountProfileScreen> {
  late final UserManagementService _userService;

  bool _loading = true;
  String? _error;

  Map<String, dynamic> _user = <String, dynamic>{};

  Uint8List? _photoBytes;

  @override
  void initState() {
    super.initState();

    _userService = UserManagementService(authService: widget.authService);

    _user = Map<String, dynamic>.from(widget.fallbackUser);

    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final response = await widget.authService.me();

      final rawUser = response['user'];

      final user = rawUser is Map
          ? Map<String, dynamic>.from(rawUser)
          : Map<String, dynamic>.from(_user);

      Uint8List? photoBytes;

      final userId = _int(user['id']);

      if (userId > 0) {
        try {
          photoBytes = await _userService.profilePhotoBytes(userId);
        } catch (_) {
          photoBytes = null;
        }
      }

      if (!mounted) {
        return;
      }

      setState(() {
        _user = user;
        _photoBytes = photoBytes;
        _loading = false;
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }

      setState(() {
        _loading = false;
        _error = exception.toString().replaceFirst('AuthException: ', '');
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    return Scaffold(
      backgroundColor: palette.pageBackground,
      appBar: AppBar(title: const Text('Profile')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 18, 16, 32),
          children: <Widget>[
            if (_loading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 120),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_error != null)
              _ProfileError(message: _error!, onRetry: _load)
            else ...<Widget>[
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: palette.surface,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: palette.border),
                ),
                child: Column(
                  children: <Widget>[
                    _ProfileAvatar(
                      bytes: _photoBytes,
                      name: _text(_user['name'], 'User'),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      _text(_user['name'], 'User'),
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: palette.textMain,
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _pretty(_text(_user['role'], 'User')),
                      style: TextStyle(color: palette.textMuted),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: palette.surface,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: palette.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      'Account Information',
                      style: TextStyle(
                        color: palette.textMain,
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 14),
                    _ProfileLine(
                      label: 'Name',
                      value: _text(_user['name'], '—'),
                    ),
                    _ProfileLine(
                      label: 'Email',
                      value: _text(_user['email'], '—'),
                    ),
                    _ProfileLine(
                      label: 'Role',
                      value: _pretty(_text(_user['role'], '—')),
                    ),
                    _ProfileLine(
                      label: 'Barangay ID',
                      value: _text(_user['barangay_id'], '—'),
                      isLast: true,
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ProfileAvatar extends StatelessWidget {
  const _ProfileAvatar({required this.bytes, required this.name});

  final Uint8List? bytes;
  final String name;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    final data = bytes;

    if (data != null && data.isNotEmpty) {
      return ClipOval(
        child: Image.memory(data, width: 104, height: 104, fit: BoxFit.cover),
      );
    }

    return Container(
      width: 104,
      height: 104,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: palette.accentSoft,
        shape: BoxShape.circle,
      ),
      child: Text(
        name.isEmpty ? 'U' : name.substring(0, 1).toUpperCase(),
        style: TextStyle(
          color: palette.accentText,
          fontSize: 40,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _ProfileLine extends StatelessWidget {
  const _ProfileLine({
    required this.label,
    required this.value,
    this.isLast = false,
  });

  final String label;
  final String value;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    return Padding(
      padding: EdgeInsets.only(bottom: isLast ? 0 : 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          SizedBox(
            width: 105,
            child: Text(
              label.toUpperCase(),
              style: TextStyle(
                color: palette.textMuted,
                fontSize: 9,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          Expanded(
            child: SelectableText(
              value,
              style: TextStyle(
                color: palette.textSoft,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ProfileError extends StatelessWidget {
  const _ProfileError({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: <Widget>[
        const SizedBox(height: 80),
        const Icon(Icons.error_outline_rounded, size: 44),
        const SizedBox(height: 12),
        Text(message, textAlign: TextAlign.center),
        const SizedBox(height: 14),
        FilledButton(onPressed: onRetry, child: const Text('Try Again')),
      ],
    );
  }
}

int _int(Object? value) {
  if (value is int) {
    return value;
  }

  return int.tryParse(value?.toString() ?? '') ?? 0;
}

String _text(Object? value, String fallback) {
  final text = value?.toString().trim() ?? '';

  return text.isEmpty ? fallback : text;
}

String _pretty(String value) {
  if (value == '—') {
    return value;
  }

  return value
      .replaceAll('_', ' ')
      .split(' ')
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}
