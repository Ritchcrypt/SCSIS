import 'package:flutter/material.dart';

import '../core/tabangnow_theme.dart';

import '../services/auth_service.dart';
import '../services/tanod_alert_service.dart';

typedef TanodAlertOpenRelated =
    Future<void> Function(String target, int? sourceId);

class TanodAlertsScreen extends StatefulWidget {
  const TanodAlertsScreen({
    super.key,
    required this.authService,
    required this.user,
    required this.onOpenRelated,
  });

  final AuthService authService;
  final Map<String, dynamic> user;
  final TanodAlertOpenRelated onOpenRelated;

  @override
  State<TanodAlertsScreen> createState() => _TanodAlertsScreenState();
}

class _TanodAlertsScreenState extends State<TanodAlertsScreen> {
  late final TanodAlertService _service;

  bool _loading = true;
  bool _loadingMore = false;
  bool _bulkBusy = false;
  String? _error;

  List<Map<String, dynamic>> _alerts = <Map<String, dynamic>>[];
  Map<String, dynamic> _stats = <String, dynamic>{};
  List<Map<String, dynamic>> _filterOptions = <Map<String, dynamic>>[];

  final Set<int> _busyAlertIds = <int>{};

  String _selectedType = 'all';
  int _currentPage = 1;
  int _lastPage = 1;

  String get _role =>
      widget.user['role']?.toString().trim().toLowerCase() ?? '';

  bool get _allowed => _role == 'admin' || _role == 'tanod';

  @override
  void initState() {
    super.initState();

    _service = TanodAlertService(authService: widget.authService);

    if (_allowed) {
      _load();
    } else {
      _loading = false;
      _error = 'Tanod Alerts are available only to Admin and Tanod accounts.';
    }
  }

  Future<void> _load({bool append = false}) async {
    if (!_allowed) {
      return;
    }

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

    final requestPage = append ? _currentPage + 1 : 1;

    try {
      final response = await _service.listAlerts(
        type: _selectedType,
        page: requestPage,
      );

      if (!mounted) {
        return;
      }

      final incoming = _mapList(response['data']);
      final stats = _map(response['stats']);
      final filters = _mapList(response['filter_options']);
      final pagination = _map(response['pagination']);

      setState(() {
        _alerts = append
            ? <Map<String, dynamic>>[..._alerts, ...incoming]
            : incoming;

        _stats = stats;

        if (filters.isNotEmpty) {
          _filterOptions = filters;
        }

        _currentPage = _toInt(pagination['current_page']) ?? requestPage;
        _lastPage = _toInt(pagination['last_page']) ?? 1;

        _loading = false;
        _loadingMore = false;
        _error = null;
      });
    } on AuthException catch (exception) {
      if (!mounted) {
        return;
      }

      setState(() {
        _loading = false;
        _loadingMore = false;
        _error = exception.message;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        _loading = false;
        _loadingMore = false;
        _error = 'Unable to load Tanod Alerts.';
      });
    }
  }

  Future<void> _changeType(String? value) async {
    final next = value?.trim().toLowerCase() ?? 'all';

    if (next == _selectedType) {
      return;
    }

    setState(() {
      _selectedType = next;
    });

    await _load();
  }

  Future<void> _markRead(Map<String, dynamic> alert) async {
    final id = _toInt(alert['id']);

    if (id == null || alert['is_read'] == true || _busyAlertIds.contains(id)) {
      return;
    }

    _setAlertBusy(id, true);

    try {
      await _service.markRead(id);
      await _load();
    } on AuthException catch (exception) {
      _show(exception.message);
    } catch (_) {
      _show('Unable to mark the alert as read.');
    } finally {
      _setAlertBusy(id, false);
    }
  }

  Future<void> _acknowledge(Map<String, dynamic> alert) async {
    final id = _toInt(alert['id']);

    if (id == null ||
        alert['can_acknowledge'] != true ||
        _busyAlertIds.contains(id)) {
      return;
    }

    _setAlertBusy(id, true);

    try {
      final response = await _service.acknowledge(id);

      if (!mounted) {
        return;
      }

      _show(response['message']?.toString() ?? 'Alert acknowledged.');

      await _load();
    } on AuthException catch (exception) {
      _show(exception.message);
    } catch (_) {
      _show('Unable to acknowledge the alert.');
    } finally {
      _setAlertBusy(id, false);
    }
  }

  Future<void> _deleteAlert(Map<String, dynamic> alert) async {
    final id = _toInt(alert['id']);

    if (id == null || _busyAlertIds.contains(id)) {
      return;
    }

    final title = alert['title']?.toString().trim();
    final label = title != null && title.isNotEmpty ? title : 'this alert';

    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (dialogContext) => AlertDialog(
            title: const Text('Delete Alert'),
            content: Text(
              'Delete "$label"? This removes it from your Tanod Alerts and notification records.',
            ),
            actions: <Widget>[
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(false),
                child: const Text('Cancel'),
              ),
              FilledButton(
                onPressed: () => Navigator.of(dialogContext).pop(true),
                style: FilledButton.styleFrom(
                  backgroundColor: const Color(0xFFDC2626),
                ),
                child: const Text('Delete'),
              ),
            ],
          ),
        ) ??
        false;

    if (!confirmed) {
      return;
    }

    _setAlertBusy(id, true);

    try {
      final response = await _service.deleteAlert(id);

      if (!mounted) {
        return;
      }

      _show(response['message']?.toString() ?? 'Alert deleted.');

      await _load();
    } on AuthException catch (exception) {
      _show(exception.message);
    } catch (_) {
      _show('Unable to delete the alert.');
    } finally {
      _setAlertBusy(id, false);
    }
  }

  Future<void> _markAllRead() async {
    if (_bulkBusy || _asInt(_stats['unread']) <= 0) {
      return;
    }

    setState(() {
      _bulkBusy = true;
    });

    try {
      final response = await _service.markAllRead();

      if (!mounted) {
        return;
      }

      _show(response['message']?.toString() ?? 'All alerts marked as read.');

      await _load();
    } on AuthException catch (exception) {
      _show(exception.message);
    } catch (_) {
      _show('Unable to mark all alerts as read.');
    } finally {
      if (mounted) {
        setState(() {
          _bulkBusy = false;
        });
      }
    }
  }

  Future<void> _clearAll() async {
    if (_bulkBusy || _asInt(_stats['total']) <= 0) {
      return;
    }

    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (dialogContext) => AlertDialog(
            title: const Text('Clear All Alerts'),
            content: const Text(
              'Delete every alert currently owned by this account? This action cannot be undone.',
            ),
            actions: <Widget>[
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(false),
                child: const Text('Cancel'),
              ),
              FilledButton(
                onPressed: () => Navigator.of(dialogContext).pop(true),
                style: FilledButton.styleFrom(
                  backgroundColor: const Color(0xFFDC2626),
                ),
                child: const Text('Clear All'),
              ),
            ],
          ),
        ) ??
        false;

    if (!confirmed) {
      return;
    }

    setState(() {
      _bulkBusy = true;
    });

    try {
      final response = await _service.clearAll();

      if (!mounted) {
        return;
      }

      _show(response['message']?.toString() ?? 'All alerts cleared.');

      await _load();
    } on AuthException catch (exception) {
      _show(exception.message);
    } catch (_) {
      _show('Unable to clear alerts.');
    } finally {
      if (mounted) {
        setState(() {
          _bulkBusy = false;
        });
      }
    }
  }

  Future<void> _openRelated(Map<String, dynamic> alert) async {
    final target = alert['related_target']?.toString().trim() ?? '';

    if (target.isEmpty) {
      return;
    }

    if (alert['is_read'] != true) {
      await _markRead(alert);
    }

    if (!mounted) {
      return;
    }

    await widget.onOpenRelated(target, _toInt(alert['source_id']));
  }

  void _setAlertBusy(int id, bool busy) {
    if (!mounted) {
      return;
    }

    setState(() {
      if (busy) {
        _busyAlertIds.add(id);
      } else {
        _busyAlertIds.remove(id);
      }
    });
  }

  void _show(String message) {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null && _alerts.isEmpty) {
      return _errorBody();
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 20, 16, 32),
        children: <Widget>[
          Text(
            'Tanod Alerts',
            style: TextStyle(
              color: TabangNowTheme.of(context).textMain,
              fontSize: 28,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            'Monitor operational alerts, read status, and acknowledgement activity.',
            style: TextStyle(
              color: TabangNowTheme.of(context).textMuted,
              fontSize: 14,
              height: 1.45,
            ),
          ),
          const SizedBox(height: 18),
          _summaryCards(),
          const SizedBox(height: 18),
          _toolbar(),
          if (_error != null) ...<Widget>[
            const SizedBox(height: 12),
            _inlineError(_error!),
          ],
          const SizedBox(height: 16),
          if (_alerts.isEmpty) _emptyState() else ..._alerts.map(_alertCard),
          if (_currentPage < _lastPage) ...<Widget>[
            const SizedBox(height: 4),
            OutlinedButton.icon(
              onPressed: _loadingMore ? null : () => _load(append: true),
              icon: _loadingMore
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.expand_more_rounded),
              label: Text(_loadingMore ? 'Loading...' : 'Load More Alerts'),
            ),
          ],
        ],
      ),
    );
  }

  Widget _summaryCards() {
    final cards = <_AlertMetric>[
      _AlertMetric(
        label: 'Total Alerts',
        value: _asInt(_stats['total']),
        icon: Icons.notifications_active_rounded,
        accent: const Color(0xFF2563EB),
        border: const Color(0xFF93C5FD),
      ),
      _AlertMetric(
        label: 'Unread Alerts',
        value: _asInt(_stats['unread']),
        icon: Icons.mark_email_unread_rounded,
        accent: const Color(0xFFD97706),
        border: const Color(0xFFFCD34D),
      ),
      _AlertMetric(
        label: 'Acknowledged',
        value: _asInt(_stats['acknowledged']),
        icon: Icons.task_alt_rounded,
        accent: const Color(0xFF059669),
        border: const Color(0xFF6EE7B7),
      ),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        final twoColumns = constraints.maxWidth >= 340;
        final width = twoColumns
            ? (constraints.maxWidth - 10) / 2
            : constraints.maxWidth;

        return Wrap(
          spacing: 10,
          runSpacing: 10,
          children: cards
              .map(
                (metric) => SizedBox(
                  width: width,
                  child: _AlertMetricCard(metric: metric),
                ),
              )
              .toList(),
        );
      },
    );
  }

  Widget _toolbar() {
    final options = _filterOptions.isNotEmpty
        ? _filterOptions
        : <Map<String, dynamic>>[
            <String, dynamic>{'value': 'all', 'label': 'All types'},
            <String, dynamic>{'value': 'dispatch', 'label': 'Dispatch'},
            <String, dynamic>{'value': 'escalation', 'label': 'Escalation'},
            <String, dynamic>{'value': 'emergency', 'label': 'Emergency'},
            <String, dynamic>{'value': 'calamity', 'label': 'Calamity'},
            <String, dynamic>{'value': 'resolved', 'label': 'Resolved'},
          ];

    final values = options
        .map((item) => item['value']?.toString() ?? '')
        .where((value) => value.isNotEmpty)
        .toSet();

    final dropdownValue = values.contains(_selectedType)
        ? _selectedType
        : 'all';

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: TabangNowTheme.of(context).surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: TabangNowTheme.of(context).border),
        boxShadow: const <BoxShadow>[
          BoxShadow(
            color: Color(0x0A0F172A),
            blurRadius: 10,
            offset: Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        children: <Widget>[
          DropdownButtonFormField<String>(
            initialValue: dropdownValue,
            isExpanded: true,
            decoration: InputDecoration(
              labelText: 'Alert Type',
              prefixIcon: const Icon(Icons.filter_alt_outlined),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            items: options
                .map(
                  (item) => DropdownMenuItem<String>(
                    value: item['value']?.toString(),
                    child: Text(item['label']?.toString() ?? ''),
                  ),
                )
                .toList(),
            onChanged: _bulkBusy ? null : _changeType,
          ),
          const SizedBox(height: 12),
          Row(
            children: <Widget>[
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _bulkBusy || _asInt(_stats['unread']) == 0
                      ? null
                      : _markAllRead,
                  icon: const Icon(Icons.done_all_rounded),
                  label: const Text('Mark All Read'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _bulkBusy || _asInt(_stats['total']) == 0
                      ? null
                      : _clearAll,
                  icon: const Icon(Icons.delete_sweep_outlined),
                  label: const Text('Clear All'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: const Color(0xFFB91C1C),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _alertCard(Map<String, dynamic> alert) {
    final id = _toInt(alert['id']);
    final busy = id != null && _busyAlertIds.contains(id);
    final unread = alert['is_read'] != true;
    final type = alert['type']?.toString() ?? '';
    final typeLabel = alert['type_label']?.toString() ?? _titleCase(type);

    final title = alert['title']?.toString().trim();
    final message = alert['message']?.toString().trim();
    final acknowledgedBy = _map(alert['acknowledged_by']);
    final acknowledgedAt = alert['acknowledged_at']?.toString();

    final target = alert['related_target']?.toString().trim() ?? '';

    final colors = _typeColors(context, type);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: unread
            ? TabangNowTheme.of(context).surfaceMuted
            : TabangNowTheme.of(context).surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: unread
              ? const Color(0xFF93C5FD)
              : TabangNowTheme.of(context).border,
          width: unread ? 1.4 : 1,
        ),
        boxShadow: const <BoxShadow>[
          BoxShadow(
            color: Color(0x0A0F172A),
            blurRadius: 10,
            offset: Offset(0, 3),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: colors.background,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(
                    _typeIcon(type),
                    color: colors.foreground,
                    size: 22,
                  ),
                ),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Wrap(
                        spacing: 7,
                        runSpacing: 5,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        children: <Widget>[
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 9,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: colors.background,
                              borderRadius: BorderRadius.circular(999),
                            ),
                            child: Text(
                              typeLabel,
                              style: TextStyle(
                                color: colors.foreground,
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          if (unread)
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 8,
                                vertical: 4,
                              ),
                              decoration: BoxDecoration(
                                color: const Color(0xFFDBEAFE),
                                borderRadius: BorderRadius.circular(999),
                              ),
                              child: const Text(
                                'UNREAD',
                                style: TextStyle(
                                  color: Color(0xFF1D4ED8),
                                  fontSize: 10,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 7),
                      Text(
                        title != null && title.isNotEmpty
                            ? title
                            : 'Tanod Alert',
                        style: TextStyle(
                          color: TabangNowTheme.of(context).textMain,
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'Delete alert',
                  onPressed: busy ? null : () => _deleteAlert(alert),
                  icon: const Icon(Icons.delete_outline_rounded),
                  color: const Color(0xFFB91C1C),
                ),
              ],
            ),
            if (message != null && message.isNotEmpty) ...<Widget>[
              const SizedBox(height: 11),
              Text(
                message,
                style: TextStyle(
                  color: TabangNowTheme.of(context).textSoft,
                  fontSize: 14,
                  height: 1.45,
                ),
              ),
            ],
            const SizedBox(height: 12),
            Wrap(
              spacing: 12,
              runSpacing: 6,
              children: <Widget>[
                _meta(
                  Icons.schedule_rounded,
                  _relativeTime(alert['created_at']?.toString()),
                ),
                _meta(
                  unread
                      ? Icons.mark_email_unread_outlined
                      : Icons.drafts_outlined,
                  unread ? 'Unread' : 'Read',
                ),
              ],
            ),
            const SizedBox(height: 10),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(11),
              decoration: BoxDecoration(
                color: acknowledgedAt != null
                    ? const Color(0xFFECFDF5)
                    : TabangNowTheme.of(context).surfaceMuted,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: acknowledgedAt != null
                      ? const Color(0xFFA7F3D0)
                      : TabangNowTheme.of(context).border,
                ),
              ),
              child: Row(
                children: <Widget>[
                  Icon(
                    acknowledgedAt != null
                        ? Icons.check_circle_rounded
                        : Icons.radio_button_unchecked_rounded,
                    size: 18,
                    color: acknowledgedAt != null
                        ? const Color(0xFF059669)
                        : TabangNowTheme.of(context).textMuted,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      acknowledgedAt != null
                          ? 'Acknowledged by ${acknowledgedBy['name'] ?? 'User'} · ${_formatDateTime(acknowledgedAt)}'
                          : 'Not acknowledged yet',
                      style: TextStyle(
                        color: acknowledgedAt != null
                            ? const Color(0xFF047857)
                            : TabangNowTheme.of(context).textMuted,
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: <Widget>[
                if (target.isNotEmpty)
                  OutlinedButton.icon(
                    onPressed: busy ? null : () => _openRelated(alert),
                    icon: Icon(
                      target == 'incident'
                          ? Icons.visibility_outlined
                          : Icons.campaign_outlined,
                      size: 18,
                    ),
                    label: Text(
                      target == 'incident'
                          ? 'Open Incident'
                          : 'Open Announcements',
                    ),
                  ),
                if (unread)
                  OutlinedButton.icon(
                    onPressed: busy ? null : () => _markRead(alert),
                    icon: const Icon(Icons.mark_email_read_outlined, size: 18),
                    label: const Text('Mark Read'),
                  ),
                if (alert['can_acknowledge'] == true)
                  FilledButton.icon(
                    onPressed: busy ? null : () => _acknowledge(alert),
                    icon: busy
                        ? const SizedBox(
                            width: 17,
                            height: 17,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(Icons.done_rounded, size: 18),
                    label: const Text('Acknowledge'),
                    style: FilledButton.styleFrom(
                      backgroundColor: const Color(0xFF2563EB),
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _meta(IconData icon, String text) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Icon(icon, size: 15, color: TabangNowTheme.of(context).textFaint),
        const SizedBox(width: 5),
        Text(
          text,
          style: TextStyle(
            color: TabangNowTheme.of(context).textMuted,
            fontSize: 12,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }

  Widget _emptyState() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 42),
      decoration: BoxDecoration(
        color: TabangNowTheme.of(context).surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: TabangNowTheme.of(context).border),
      ),
      child: Column(
        children: <Widget>[
          Icon(
            Icons.notifications_none_rounded,
            size: 48,
            color: TabangNowTheme.of(context).textFaint,
          ),
          SizedBox(height: 12),
          Text(
            'No alerts found',
            style: TextStyle(
              color: TabangNowTheme.of(context).textMain,
              fontSize: 17,
              fontWeight: FontWeight.w800,
            ),
          ),
          SizedBox(height: 5),
          Text(
            'Operational alerts sent to this account will appear here.',
            textAlign: TextAlign.center,
            style: TextStyle(color: TabangNowTheme.of(context).textMuted),
          ),
        ],
      ),
    );
  }

  Widget _errorBody() {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(24),
      children: <Widget>[
        const SizedBox(height: 120),
        const Icon(
          Icons.error_outline_rounded,
          size: 48,
          color: Color(0xFFDC2626),
        ),
        const SizedBox(height: 12),
        Text(_error!, textAlign: TextAlign.center),
        const SizedBox(height: 14),
        Center(
          child: FilledButton.icon(
            onPressed: _allowed ? _load : null,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Retry'),
          ),
        ),
      ],
    );
  }

  Widget _inlineError(String message) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFEF2F2),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFFECACA)),
      ),
      child: Text(
        message,
        style: const TextStyle(
          color: Color(0xFFB91C1C),
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  IconData _typeIcon(String type) {
    switch (type) {
      case 'dispatch':
        return Icons.local_police_outlined;
      case 'escalation':
        return Icons.trending_up_rounded;
      case 'emergency':
        return Icons.emergency_rounded;
      case 'calamity':
        return Icons.warning_amber_rounded;
      case 'resolved':
        return Icons.task_alt_rounded;
      default:
        return Icons.notifications_active_rounded;
    }
  }

  _AlertTypeColors _typeColors(BuildContext context, String type) {
    switch (type) {
      case 'dispatch':
        return const _AlertTypeColors(Color(0xFFEFF6FF), Color(0xFF1D4ED8));
      case 'escalation':
        return const _AlertTypeColors(Color(0xFFFFF7ED), Color(0xFFC2410C));
      case 'emergency':
        return const _AlertTypeColors(Color(0xFFFEF2F2), Color(0xFFB91C1C));
      case 'calamity':
        return const _AlertTypeColors(Color(0xFFF5F3FF), Color(0xFF6D28D9));
      case 'resolved':
        return const _AlertTypeColors(Color(0xFFECFDF5), Color(0xFF047857));
      default:
        return _AlertTypeColors(
          TabangNowTheme.of(context).surfaceSoft,
          TabangNowTheme.of(context).textSoft,
        );
    }
  }
}

class _AlertMetric {
  const _AlertMetric({
    required this.label,
    required this.value,
    required this.icon,
    required this.accent,
    required this.border,
  });

  final String label;
  final int value;
  final IconData icon;
  final Color accent;
  final Color border;
}

class _AlertMetricCard extends StatelessWidget {
  const _AlertMetricCard({required this.metric});

  final _AlertMetric metric;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 132),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: TabangNowTheme.of(context).surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: metric.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: metric.accent.withValues(alpha: 0.09),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Icon(metric.icon, color: metric.accent, size: 21),
          ),
          const SizedBox(height: 13),
          Text(
            metric.value.toString(),
            style: TextStyle(
              color: TabangNowTheme.of(context).textMain,
              fontSize: 28,
              height: 1,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            metric.label,
            style: TextStyle(
              color: TabangNowTheme.of(context).textMuted,
              fontSize: 13,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _AlertTypeColors {
  const _AlertTypeColors(this.background, this.foreground);

  final Color background;
  final Color foreground;
}

Map<String, dynamic> _map(dynamic value) {
  if (value is Map<String, dynamic>) {
    return value;
  }

  if (value is Map) {
    return Map<String, dynamic>.from(value);
  }

  return <String, dynamic>{};
}

List<Map<String, dynamic>> _mapList(dynamic value) {
  if (value is! List) {
    return <Map<String, dynamic>>[];
  }

  return value
      .whereType<Map>()
      .map((item) => Map<String, dynamic>.from(item))
      .toList();
}

int? _toInt(dynamic value) {
  if (value is int) {
    return value;
  }

  return int.tryParse(value?.toString() ?? '');
}

int _asInt(dynamic value) => _toInt(value) ?? 0;

String _titleCase(String value) {
  return value
      .replaceAll('_', ' ')
      .split(RegExp(r'\s+'))
      .where((part) => part.isNotEmpty)
      .map(
        (part) => '${part[0].toUpperCase()}${part.substring(1).toLowerCase()}',
      )
      .join(' ');
}

String _relativeTime(String? raw) {
  final parsed = DateTime.tryParse(raw ?? '');

  if (parsed == null) {
    return 'Unknown time';
  }

  final difference = DateTime.now().difference(parsed.toLocal());

  if (difference.inSeconds < 60) {
    return 'Just now';
  }

  if (difference.inMinutes < 60) {
    return '${difference.inMinutes}m ago';
  }

  if (difference.inHours < 24) {
    return '${difference.inHours}h ago';
  }

  if (difference.inDays < 7) {
    return '${difference.inDays}d ago';
  }

  return _formatDateTime(raw);
}

String _formatDateTime(String? raw) {
  final parsed = DateTime.tryParse(raw ?? '');

  if (parsed == null) {
    return 'Unknown time';
  }

  final local = parsed.toLocal();
  final month = const <String>[
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
  ][local.month - 1];

  final hour12 = local.hour % 12 == 0 ? 12 : local.hour % 12;
  final minute = local.minute.toString().padLeft(2, '0');
  final meridiem = local.hour >= 12 ? 'PM' : 'AM';

  return '$month ${local.day}, ${local.year} '
      '$hour12:$minute $meridiem';
}
