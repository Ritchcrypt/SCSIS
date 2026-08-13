import 'dart:convert';

import 'package:flutter/material.dart';

import '../core/tabangnow_theme.dart';
import '../services/activity_log_service.dart';

class ActivityLogDetailScreen extends StatefulWidget {
  const ActivityLogDetailScreen({
    super.key,
    required this.service,
    required this.activityLogId,
  });

  final ActivityLogService service;
  final int activityLogId;

  @override
  State<ActivityLogDetailScreen> createState() =>
      _ActivityLogDetailScreenState();
}

class _ActivityLogDetailScreenState extends State<ActivityLogDetailScreen> {
  bool _loading = true;
  String? _error;

  Map<String, dynamic> _log = <String, dynamic>{};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final response = await widget.service.show(widget.activityLogId);

      if (!mounted) {
        return;
      }

      setState(() {
        _log = _map(response['data']);
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
      appBar: AppBar(title: const Text('Activity Log Details')),
      body: RefreshIndicator(onRefresh: _load, child: _buildBody()),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: const <Widget>[
          SizedBox(height: 220),
          Center(child: CircularProgressIndicator()),
        ],
      );
    }

    if (_error != null) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(24),
        children: <Widget>[
          const SizedBox(height: 90),
          const Icon(Icons.error_outline_rounded, size: 48),
          const SizedBox(height: 12),
          Text(_error!, textAlign: TextAlign.center),
          const SizedBox(height: 16),
          FilledButton(onPressed: _load, child: const Text('Try Again')),
        ],
      );
    }

    final palette = TabangNowTheme.of(context);

    final ipContext = _map(_log['ip_context']);

    final metadata = _log['metadata'];

    final metadataJson = const JsonEncoder.withIndent(
      '  ',
    ).convert(metadata ?? <String, dynamic>{});

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 32),
      children: <Widget>[
        Text(
          'READ-ONLY AUDIT RECORD',
          style: TextStyle(
            color: palette.accentText,
            fontSize: 10,
            fontWeight: FontWeight.w900,
            letterSpacing: 1.5,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          _text(
            _log['event_label'],
            _pretty(_text(_log['event'], 'Activity Log')),
          ),
          style: TextStyle(
            color: palette.textMain,
            fontSize: 25,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          _text(_log['description'], 'No description.'),
          style: TextStyle(color: palette.textMuted, height: 1.5),
        ),
        const SizedBox(height: 16),

        if (_text(ipContext['type'], '') == 'loopback') ...<Widget>[
          _LoopbackNotice(
            ipAddress: _text(_log['ip_address'], '127.0.0.1'),
            note: _text(
              ipContext['note'],
              'Loopback is expected during local development.',
            ),
          ),
          const SizedBox(height: 14),
        ],

        _Surface(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              const _SectionTitle(title: 'Event information'),
              const SizedBox(height: 16),
              _InfoLine(label: 'Log ID', value: '#${_int(_log['id'])}'),
              _InfoLine(
                label: 'Recorded',
                value: _formatDateTime(_log['created_at']),
              ),
              _InfoLine(
                label: 'Event',
                value: _text(_log['event'], '—'),
                monospace: true,
              ),
              _InfoLine(
                label: 'Category',
                value: _text(
                  _log['category_label'],
                  _pretty(_text(_log['category'], '—')),
                ),
                isLast: true,
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),

        _Surface(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              const _SectionTitle(title: 'Actor and target'),
              const SizedBox(height: 16),
              _IdentityCard(
                title: 'Actor',
                name: _text(_log['actor_name'], 'System / Unknown'),
                id: _nullableInt(_log['actor_id']),
                role: _text(_log['actor_role'], '—'),
              ),
              const SizedBox(height: 12),
              _IdentityCard(
                title: 'Target user',
                name: _text(_log['target_name'], 'No user target'),
                id: _nullableInt(_log['target_user_id']),
                role: _text(_log['target_role'], '—'),
              ),
              const SizedBox(height: 14),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(13),
                decoration: BoxDecoration(
                  color: palette.surfaceMuted,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  'Actor and target names are stored as snapshots, so this audit record remains readable after an account is deleted.',
                  style: TextStyle(
                    color: palette.textMuted,
                    fontSize: 11,
                    height: 1.45,
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),

        _Surface(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              const _SectionTitle(title: 'Request information'),
              const SizedBox(height: 16),
              _InfoLine(
                label: 'Route',
                value: _text(_log['route_name'], '—'),
                monospace: true,
              ),
              _InfoLine(
                label: 'Method',
                value: _text(_log['request_method'], '—'),
                monospace: true,
              ),
              _InfoLine(
                label: 'IP address',
                value: _text(_log['ip_address'], '—'),
                monospace: true,
              ),
              if (ipContext.isNotEmpty)
                _InfoLine(
                  label: 'IP context',
                  value: _text(ipContext['label'], '—'),
                ),
              _InfoLine(
                label: 'User agent',
                value: _text(_log['user_agent'], '—'),
                isLast: true,
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),

        _Surface(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  const Expanded(child: _SectionTitle(title: 'Metadata')),
                  Text(
                    'Sensitive keys are redacted',
                    style: TextStyle(
                      color: palette.textMuted,
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Container(
                width: double.infinity,
                constraints: const BoxConstraints(maxHeight: 520),
                padding: const EdgeInsets.all(15),
                decoration: BoxDecoration(
                  color: const Color(0xFF020617),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: SingleChildScrollView(
                  child: SelectableText(
                    metadataJson,
                    style: const TextStyle(
                      color: Color(0xFFE2E8F0),
                      fontFamily: 'monospace',
                      fontSize: 11,
                      height: 1.55,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _LoopbackNotice extends StatelessWidget {
  const _LoopbackNotice({required this.ipAddress, required this.note});

  final String ipAddress;
  final String note;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: palette.isDark
            ? const Color(0xFF172554)
            : const Color(0xFFEFF6FF),
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: const Color(0xFF93C5FD)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Icon(Icons.lan_outlined, color: Color(0xFF2563EB)),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  '$ipAddress is a real loopback address',
                  style: TextStyle(
                    color: palette.textMain,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  note,
                  style: TextStyle(
                    color: palette.textSoft,
                    fontSize: 11,
                    height: 1.45,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _IdentityCard extends StatelessWidget {
  const _IdentityCard({
    required this.title,
    required this.name,
    required this.id,
    required this.role,
  });

  final String title;
  final String name;
  final int? id;
  final String role;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: palette.surfaceMuted,
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: palette.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            title.toUpperCase(),
            style: TextStyle(
              color: palette.textMuted,
              fontSize: 9,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            name,
            style: TextStyle(
              color: palette.textMain,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            'ID: ${id ?? '—'} · Role: ${role == '—' ? '—' : _pretty(role)}',
            style: TextStyle(color: palette.textMuted, fontSize: 11),
          ),
        ],
      ),
    );
  }
}

class _Surface extends StatelessWidget {
  const _Surface({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: palette.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: palette.border),
      ),
      child: child,
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Text(
      title,
      style: TextStyle(
        color: TabangNowTheme.of(context).textMain,
        fontSize: 17,
        fontWeight: FontWeight.w900,
      ),
    );
  }
}

class _InfoLine extends StatelessWidget {
  const _InfoLine({
    required this.label,
    required this.value,
    this.monospace = false,
    this.isLast = false,
  });

  final String label;
  final String value;
  final bool monospace;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    return Padding(
      padding: EdgeInsets.only(bottom: isLast ? 0 : 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            label.toUpperCase(),
            style: TextStyle(
              color: palette.textMuted,
              fontSize: 9,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          SelectableText(
            value,
            style: TextStyle(
              color: palette.textSoft,
              fontSize: 12,
              height: 1.45,
              fontFamily: monospace ? 'monospace' : null,
              fontWeight: monospace ? FontWeight.w600 : FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

Map<String, dynamic> _map(Object? value) {
  if (value is Map<String, dynamic>) {
    return value;
  }

  if (value is Map) {
    return Map<String, dynamic>.from(value);
  }

  return <String, dynamic>{};
}

int _int(Object? value) {
  if (value is int) {
    return value;
  }

  return int.tryParse(value?.toString() ?? '') ?? 0;
}

int? _nullableInt(Object? value) {
  if (value == null) {
    return null;
  }

  if (value is int) {
    return value;
  }

  return int.tryParse(value.toString());
}

String _text(Object? value, String fallback) {
  final text = value?.toString().trim() ?? '';

  return text.isEmpty ? fallback : text;
}

String _pretty(String value) {
  return value
      .replaceAll('.', ' ')
      .replaceAll('_', ' ')
      .split(' ')
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}

String _formatDateTime(Object? raw) {
  final text = raw?.toString().trim() ?? '';

  if (text.isEmpty) {
    return '—';
  }

  final parsed = DateTime.tryParse(text);

  if (parsed == null) {
    return text;
  }

  final value = parsed.toLocal();

  const months = <String>[
    '',
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ];

  final hour = value.hour % 12 == 0 ? 12 : value.hour % 12;

  final minute = value.minute.toString().padLeft(2, '0');

  final second = value.second.toString().padLeft(2, '0');

  final suffix = value.hour >= 12 ? 'PM' : 'AM';

  return '${months[value.month]} ${value.day}, ${value.year} '
      '$hour:$minute:$second $suffix';
}
