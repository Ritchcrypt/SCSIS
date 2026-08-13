import 'package:flutter/material.dart';

import '../core/app_capabilities.dart';
import '../core/app_role.dart';
import '../core/tabangnow_theme.dart';
import '../services/auth_service.dart';
import '../services/case_management_service.dart';
import 'case_form_screen.dart';

class CaseManagementScreen extends StatefulWidget {
  const CaseManagementScreen({
    super.key,
    required this.authService,
    required this.user,
  });

  final AuthService authService;
  final Map<String, dynamic> user;

  @override
  State<CaseManagementScreen> createState() => _CaseManagementScreenState();
}

class _CaseManagementScreenState extends State<CaseManagementScreen> {
  late final CaseManagementService _service;

  final TextEditingController _searchController = TextEditingController();

  bool _loading = true;
  bool _loadingMore = false;
  String? _error;
  String _activeSearch = '';

  List<Map<String, dynamic>> _cases = <Map<String, dynamic>>[];
  List<Map<String, dynamic>> _caseTypes = <Map<String, dynamic>>[];
  List<Map<String, dynamic>> _caseStatuses = <Map<String, dynamic>>[];
  List<Map<String, dynamic>> _incidents = <Map<String, dynamic>>[];

  int _currentPage = 1;
  int _lastPage = 1;

  AppRole get _role => AppRoleX.fromRaw(
    widget.user['role']?.toString().trim().toLowerCase() ?? '',
  );

  bool get _allowed =>
      AppCapabilities.forRole(_role).allows(AppCapability.viewCaseManagement);

  @override
  void initState() {
    super.initState();

    _service = CaseManagementService(authService: widget.authService);

    if (_allowed) {
      _load();
    } else {
      _loading = false;
      _error = 'Case Management is available only to Administrator accounts.';
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _load({bool append = false, String? search}) async {
    if (!_allowed) {
      return;
    }

    final query = search ?? _activeSearch;

    if (append) {
      if (_loadingMore || _currentPage >= _lastPage) {
        return;
      }

      setState(() {
        _loadingMore = true;
      });
    } else {
      setState(() {
        _loading = true;
        _error = null;
      });
    }

    final page = append ? _currentPage + 1 : 1;

    try {
      final response = await _service.index(search: query, page: page);

      if (!mounted) {
        return;
      }

      final incoming = _mapList(response['data']);
      final options = _map(response['options']);
      final pagination = _map(response['pagination']);

      setState(() {
        _activeSearch = query;

        _cases = append
            ? <Map<String, dynamic>>[..._cases, ...incoming]
            : incoming;

        _caseTypes = _mapList(options['case_types']);
        _caseStatuses = _mapList(options['case_statuses']);
        _incidents = _mapList(options['incidents']);

        _currentPage = _int(pagination['current_page'], fallback: page);

        _lastPage = _int(pagination['last_page'], fallback: 1);

        _loading = false;
        _loadingMore = false;
        _error = null;
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }

      setState(() {
        _loading = false;
        _loadingMore = false;
        _error = exception.toString().replaceFirst('AuthException: ', '');
      });
    }
  }

  Future<void> _applySearch() async {
    await _load(search: _searchController.text.trim());
  }

  Future<void> _clearSearch() async {
    _searchController.clear();
    await _load(search: '');
  }

  Future<void> _openForm({Map<String, dynamic>? record}) async {
    final message = await Navigator.of(context).push<String>(
      MaterialPageRoute<String>(
        builder: (_) => CaseFormScreen(
          service: _service,
          caseTypes: _caseTypes,
          caseStatuses: _caseStatuses,
          incidents: _incidents,
          caseRecord: record,
        ),
      ),
    );

    if (message == null || !mounted) {
      return;
    }

    _show(message);

    await _load(search: _activeSearch);
  }

  Future<void> _delete(Map<String, dynamic> record) async {
    final id = _int(record['id']);

    if (id <= 0) {
      return;
    }

    final caseNumber = _text(record['case_number'], '—');

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delete Case'),
        content: Text('Delete Case No. $caseNumber? This cannot be undone.'),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: const Color(0xFFB91C1C),
              foregroundColor: Colors.white,
            ),
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirmed != true) {
      return;
    }

    try {
      final response = await _service.delete(id);

      if (!mounted) {
        return;
      }

      _show(
        response['message']?.toString() ?? 'Case record deleted successfully.',
      );

      await _load(search: _activeSearch);
    } catch (exception) {
      if (!mounted) {
        return;
      }

      _show(exception.toString().replaceFirst('AuthException: ', ''));
    }
  }

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    if (!_allowed) {
      return _AccessDenied(
        message: _error ?? 'Case Management is unavailable.',
      );
    }

    return RefreshIndicator(
      onRefresh: () => _load(search: _activeSearch),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 18, 16, 32),
        children: <Widget>[
          _Header(onCreate: () => _openForm()),
          const SizedBox(height: 14),
          _SearchPanel(
            controller: _searchController,
            onSearch: _applySearch,
            onClear: _clearSearch,
            activeSearch: _activeSearch,
          ),
          const SizedBox(height: 14),
          if (_loading)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 90),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_error != null)
            _ErrorCard(
              message: _error!,
              onRetry: () => _load(search: _activeSearch),
            )
          else if (_cases.isEmpty)
            _EmptyCard(searching: _activeSearch.isNotEmpty)
          else
            for (var index = 0; index < _cases.length; index++) ...<Widget>[
              _CaseCard(
                record: _cases[index],
                onEdit: () => _openForm(record: _cases[index]),
                onDelete: () => _delete(_cases[index]),
              ),
              if (index != _cases.length - 1) const SizedBox(height: 12),
            ],
          if (!_loading &&
              _error == null &&
              _currentPage < _lastPage) ...<Widget>[
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: _loadingMore
                  ? null
                  : () => _load(append: true, search: _activeSearch),
              icon: _loadingMore
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.expand_more_rounded),
              label: Text(_loadingMore ? 'Loading...' : 'Load More'),
            ),
          ],
          const SizedBox(height: 10),
          Text(
            'Cases are ordered by numeric Case No., matching the website.',
            textAlign: TextAlign.center,
            style: TextStyle(color: palette.textMuted, fontSize: 11),
          ),
        ],
      ),
    );
  }

  void _show(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.onCreate});

  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: palette.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: palette.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            'Case Management',
            style: TextStyle(
              color: palette.textMain,
              fontSize: 26,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Barangay blotter and case files',
            style: TextStyle(color: palette.textMuted),
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: onCreate,
              icon: const Icon(Icons.add_rounded),
              label: const Text('New Case'),
            ),
          ),
        ],
      ),
    );
  }
}

class _SearchPanel extends StatelessWidget {
  const _SearchPanel({
    required this.controller,
    required this.onSearch,
    required this.onClear,
    required this.activeSearch,
  });

  final TextEditingController controller;
  final Future<void> Function() onSearch;
  final Future<void> Function() onClear;
  final String activeSearch;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: palette.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: palette.border),
      ),
      child: Row(
        children: <Widget>[
          Expanded(
            child: TextField(
              controller: controller,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => onSearch(),
              decoration: const InputDecoration(
                hintText: 'Search cases...',
                prefixIcon: Icon(Icons.search_rounded),
              ),
            ),
          ),
          const SizedBox(width: 8),
          IconButton(
            tooltip: 'Search',
            onPressed: onSearch,
            icon: const Icon(Icons.search_rounded),
          ),
          if (activeSearch.isNotEmpty)
            IconButton(
              tooltip: 'Clear search',
              onPressed: onClear,
              icon: const Icon(Icons.close_rounded),
            ),
        ],
      ),
    );
  }
}

class _CaseCard extends StatelessWidget {
  const _CaseCard({
    required this.record,
    required this.onEdit,
    required this.onDelete,
  });

  final Map<String, dynamic> record;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    return Container(
      padding: const EdgeInsets.all(17),
      decoration: BoxDecoration(
        color: palette.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: palette.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      'CASE NO.',
                      style: TextStyle(
                        color: palette.textMuted,
                        fontSize: 10,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 0.6,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      _text(record['case_number'], '—'),
                      style: TextStyle(
                        color: palette.textMain,
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                        fontFamily: 'monospace',
                      ),
                    ),
                  ],
                ),
              ),
              _CaseStatusBadge(
                status: _text(record['status'], 'open'),
                label: _text(record['display_status'], 'Open'),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            _text(record['subject_name'], 'Unnamed subject'),
            style: TextStyle(
              color: palette.textMain,
              fontSize: 17,
              fontWeight: FontWeight.w900,
            ),
          ),
          if (_text(record['contact'], '').isNotEmpty) ...<Widget>[
            const SizedBox(height: 3),
            Text(
              _text(record['contact'], ''),
              style: TextStyle(color: palette.textMuted, fontSize: 12),
            ),
          ],
          const SizedBox(height: 13),
          _InfoLine(label: 'Type', value: _text(record['display_type'], '—')),
          _InfoLine(
            label: 'Incident',
            value: _text(
              record['display_incident_title'],
              'No linked incident',
            ),
          ),
          _InfoLine(
            label: 'Hearing',
            value: _formatDate(record['hearing_date']),
          ),
          _InfoLine(
            label: 'Handled By',
            value: _text(record['handled_by'], '—'),
          ),
          const SizedBox(height: 11),
          Divider(color: palette.border, height: 1),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: <Widget>[
              IconButton(
                tooltip: 'Edit case',
                onPressed: onEdit,
                icon: const Icon(Icons.edit_rounded),
              ),
              IconButton(
                tooltip: 'Delete case',
                color: const Color(0xFFDC2626),
                onPressed: onDelete,
                icon: const Icon(Icons.delete_outline_rounded),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _InfoLine extends StatelessWidget {
  const _InfoLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: 7),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          SizedBox(
            width: 86,
            child: Text(
              label,
              style: TextStyle(
                color: palette.textMuted,
                fontSize: 11,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: TextStyle(
                color: palette.textSoft,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CaseStatusBadge extends StatelessWidget {
  const _CaseStatusBadge({required this.status, required this.label});

  final String status;
  final String label;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    final normalized = status.toLowerCase();

    final colors = switch (normalized) {
      'open' => (
        palette.isDark ? const Color(0xFF1E3A8A) : const Color(0xFFDBEAFE),
        palette.isDark ? const Color(0xFFBFDBFE) : const Color(0xFF1D4ED8),
      ),
      'under_investigation' => (
        palette.isDark ? const Color(0xFF581C87) : const Color(0xFFF3E8FF),
        palette.isDark ? const Color(0xFFE9D5FF) : const Color(0xFF7E22CE),
      ),
      'mediation' => (
        palette.isDark ? const Color(0xFF713F12) : const Color(0xFFFEF9C3),
        palette.isDark ? const Color(0xFFFEF08A) : const Color(0xFFA16207),
      ),
      'resolved' => (
        palette.isDark ? const Color(0xFF064E3B) : const Color(0xFFD1FAE5),
        palette.isDark ? const Color(0xFFA7F3D0) : const Color(0xFF047857),
      ),
      _ => (palette.surfaceSoft, palette.textSoft),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: colors.$1,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: colors.$2,
          fontSize: 10,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({required this.searching});

  final bool searching;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 44),
      decoration: BoxDecoration(
        color: palette.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: palette.border),
      ),
      child: Column(
        children: <Widget>[
          const Text('📘', style: TextStyle(fontSize: 34)),
          const SizedBox(height: 12),
          Text(
            'No case records found.',
            style: TextStyle(
              color: palette.textMain,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            searching
                ? 'Try another search term.'
                : 'Create your first barangay case file.',
            textAlign: TextAlign.center,
            style: TextStyle(color: palette.textMuted),
          ),
        ],
      ),
    );
  }
}

class _ErrorCard extends StatelessWidget {
  const _ErrorCard({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: palette.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: palette.border),
      ),
      child: Column(
        children: <Widget>[
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          OutlinedButton(onPressed: onRetry, child: const Text('Try Again')),
        ],
      ),
    );
  }
}

class _AccessDenied extends StatelessWidget {
  const _AccessDenied({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    return ListView(
      padding: const EdgeInsets.all(24),
      children: <Widget>[
        const SizedBox(height: 100),
        Icon(Icons.lock_outline_rounded, size: 46, color: palette.textMuted),
        const SizedBox(height: 14),
        Text(
          message,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: palette.textSoft,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
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

List<Map<String, dynamic>> _mapList(Object? value) {
  if (value is! List) {
    return <Map<String, dynamic>>[];
  }

  return value
      .whereType<Map>()
      .map((item) => Map<String, dynamic>.from(item))
      .toList(growable: false);
}

int _int(Object? value, {int fallback = 0}) {
  if (value is int) {
    return value;
  }

  return int.tryParse(value?.toString() ?? '') ?? fallback;
}

String _text(Object? value, String fallback) {
  final text = value?.toString().trim() ?? '';

  return text.isEmpty ? fallback : text;
}

String _formatDate(Object? raw) {
  final text = raw?.toString().trim() ?? '';

  if (text.isEmpty) {
    return '—';
  }

  final parsed = DateTime.tryParse(text);

  if (parsed == null) {
    return text;
  }

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

  return '${months[parsed.month]} ${parsed.day}, ${parsed.year}';
}
