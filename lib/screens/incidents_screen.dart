import 'package:flutter/material.dart';

import '../core/tabangnow_theme.dart';

import '../services/auth_service.dart';
import '../services/incident_service.dart';
import 'incident_detail_screen.dart';
import 'report_incident_screen.dart';

class IncidentsScreen extends StatefulWidget {
  const IncidentsScreen({
    super.key,
    required this.authService,
    required this.user,
  });

  final AuthService authService;
  final Map<String, dynamic> user;

  @override
  State<IncidentsScreen> createState() => _IncidentsScreenState();
}

class _IncidentsScreenState extends State<IncidentsScreen> {
  late final IncidentService _incidentService;
  final TextEditingController _searchController = TextEditingController();

  bool _loading = true;
  bool _loadingMore = false;
  bool _canCreate = false;
  bool _canDelete = false;
  String? _error;

  List<Map<String, dynamic>> _incidents = <Map<String, dynamic>>[];
  List<Map<String, dynamic>> _statuses = <Map<String, dynamic>>[];
  List<Map<String, dynamic>> _severities = <Map<String, dynamic>>[];

  int _total = 0;
  int _currentPage = 1;
  int _lastPage = 1;
  int? _statusId;
  String? _priority;

  @override
  void initState() {
    super.initState();
    _incidentService = IncidentService(authService: widget.authService);
    _loadIncidents();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadIncidents({bool append = false}) async {
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
        _currentPage = 1;
      });
    }

    final requestPage = append ? _currentPage + 1 : 1;

    try {
      final response = await _incidentService.listIncidents(
        search: _searchController.text,
        statusId: _statusId,
        priority: _priority,
        page: requestPage,
      );

      final incoming = _mapList(response['data']);
      final pagination = _map(response['pagination']);
      final filters = _map(response['filter_options']);

      if (!mounted) {
        return;
      }

      setState(() {
        _incidents = append
            ? <Map<String, dynamic>>[..._incidents, ...incoming]
            : incoming;

        _total = _toInt(pagination['total']) ?? _incidents.length;
        _currentPage = _toInt(pagination['current_page']) ?? requestPage;
        _lastPage = _toInt(pagination['last_page']) ?? 1;

        _canCreate = response['can_create'] == true;
        _canDelete = response['can_delete'] == true;

        if (filters.isNotEmpty) {
          _statuses = _mapList(filters['statuses']);
          _severities = _mapList(filters['severity_options']);
        }

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
        _error = 'Unable to load incidents.';
      });
    }
  }

  Future<void> _openReportIncident() async {
    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => ReportIncidentScreen(
          incidentService: _incidentService,
          user: widget.user,
        ),
      ),
    );

    if (created == true) {
      await _loadIncidents();
    }
  }

  Future<void> _openIncident(Map<String, dynamic> incident) async {
    final incidentId = _toInt(incident['id']);

    if (incidentId == null) {
      return;
    }

    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => IncidentDetailScreen(
          incidentService: _incidentService,
          incidentId: incidentId,
          user: widget.user,
        ),
      ),
    );

    if (changed == true || mounted) {
      await _loadIncidents();
    }
  }

  Future<void> _deleteIncident(Map<String, dynamic> incident) async {
    final incidentId = _toInt(incident['id']);

    if (incidentId == null || !_canDelete) {
      return;
    }

    final title = incident['title']?.toString().trim() ?? 'this incident';

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delete Incident'),
        content: Text(
          'Delete "$title"? This also removes related incident evidence, '
          'messages, status history, escalations, case links, and incident '
          'notifications. This action cannot be undone.',
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () {
              Navigator.of(dialogContext).pop(false);
            },
            child: const Text('Cancel'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: const Color(0xFFB91C1C),
            ),
            onPressed: () {
              Navigator.of(dialogContext).pop(true);
            },
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirmed != true) {
      return;
    }

    try {
      await _incidentService.deleteIncident(incidentId: incidentId);

      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Incident deleted successfully.')),
      );

      await _loadIncidents();
    } on AuthException catch (exception) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(exception.message)));
    } catch (_) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Unable to delete the incident.')),
      );
    }
  }

  void _resetFilters() {
    setState(() {
      _searchController.clear();
      _statusId = null;
      _priority = null;
    });

    _loadIncidents();
  }

  @override
  Widget build(BuildContext context) {
    final role = widget.user['role']?.toString().trim().toLowerCase() ?? '';

    final pageTitle = switch (role) {
      'tanod' => 'Assigned Incidents',
      'resident' => 'My Incident Reports',
      _ => 'Incidents',
    };

    return RefreshIndicator(
      onRefresh: _loadIncidents,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 18, 16, 32),
        children: <Widget>[
          _PageHeader(
            title: pageTitle,
            subtitle: role == 'tanod'
                ? 'Review incidents currently assigned to you.'
                : role == 'resident'
                ? 'Review and monitor incident reports you submitted.'
                : 'Review, filter, and monitor reported community '
                      'safety incidents in Dao, Capiz.',
            canCreate: _canCreate,
            onCreate: _openReportIncident,
          ),
          const SizedBox(height: 16),
          _buildFilters(),
          const SizedBox(height: 16),
          _SectionHeader(
            title: 'Incident Reports',
            subtitle:
                'Latest incident reports based on your access level. $_total record${_total == 1 ? '' : 's'}.',
          ),
          const SizedBox(height: 12),
          if (_loading)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 70),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_error != null)
            _ErrorCard(message: _error!, onRetry: _loadIncidents)
          else if (_incidents.isEmpty)
            const _EmptyCard()
          else ...<Widget>[
            ..._incidents.map(
              (incident) => _IncidentReportCard(
                incident: incident,
                canDelete: _canDelete,
                onView: () => _openIncident(incident),
                onDelete: () => _deleteIncident(incident),
              ),
            ),
            if (_currentPage < _lastPage) ...<Widget>[
              const SizedBox(height: 4),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: _loadingMore
                      ? null
                      : () => _loadIncidents(append: true),
                  icon: _loadingMore
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.expand_more_rounded),
                  label: Text(_loadingMore ? 'Loading...' : 'Load More'),
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }

  Widget _buildFilters() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _panelDecoration(context),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            'Filter Incidents',
            style: TextStyle(
              color: TabangNowTheme.of(context).textMain,
              fontSize: 16,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _searchController,
            textInputAction: TextInputAction.search,
            onSubmitted: (_) => _loadIncidents(),
            decoration: const InputDecoration(
              labelText: 'Search',
              hintText: 'Search title, description, barangay...',
              prefixIcon: Icon(Icons.search_rounded),
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<int?>(
            initialValue: _statusId,
            decoration: const InputDecoration(
              labelText: 'Status',
              border: OutlineInputBorder(),
            ),
            items: <DropdownMenuItem<int?>>[
              const DropdownMenuItem<int?>(
                value: null,
                child: Text('All Statuses'),
              ),
              ..._statuses.map((status) {
                final id = _toInt(status['id']);

                if (id == null) {
                  return null;
                }

                return DropdownMenuItem<int?>(
                  value: id,
                  child: Text(status['name']?.toString() ?? 'Unnamed Status'),
                );
              }).whereType<DropdownMenuItem<int?>>(),
            ],
            onChanged: (value) {
              setState(() {
                _statusId = value;
              });
            },
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String?>(
            initialValue: _priority,
            decoration: const InputDecoration(
              labelText: 'Severity',
              border: OutlineInputBorder(),
            ),
            items: <DropdownMenuItem<String?>>[
              const DropdownMenuItem<String?>(
                value: null,
                child: Text('All Severity'),
              ),
              ..._severities.map((severity) {
                final value = severity['value']?.toString().trim();

                if (value == null || value.isEmpty) {
                  return null;
                }

                return DropdownMenuItem<String?>(
                  value: value,
                  child: Text(severity['label']?.toString() ?? value),
                );
              }).whereType<DropdownMenuItem<String?>>(),
            ],
            onChanged: (value) {
              setState(() {
                _priority = value;
              });
            },
          ),
          const SizedBox(height: 14),
          Row(
            children: <Widget>[
              Expanded(
                child: FilledButton(
                  onPressed: _loadIncidents,
                  child: const Text('Filter'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton(
                  onPressed: _resetFilters,
                  child: const Text('Reset'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  static Map<String, dynamic> _map(Object? value) {
    if (value is Map<String, dynamic>) {
      return value;
    }

    if (value is Map) {
      return Map<String, dynamic>.from(value);
    }

    return <String, dynamic>{};
  }

  static List<Map<String, dynamic>> _mapList(Object? value) {
    if (value is! List) {
      return <Map<String, dynamic>>[];
    }

    return value.whereType<Map>().map(Map<String, dynamic>.from).toList();
  }

  static int? _toInt(Object? value) {
    if (value is int) {
      return value;
    }

    return int.tryParse(value?.toString() ?? '');
  }
}

class _PageHeader extends StatelessWidget {
  const _PageHeader({
    required this.title,
    required this.subtitle,
    required this.canCreate,
    required this.onCreate,
  });

  final String title;
  final String subtitle;
  final bool canCreate;
  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: _panelDecoration(context),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            title,
            style: TextStyle(
              color: TabangNowTheme.of(context).textMain,
              fontSize: 25,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 7),
          Text(
            subtitle,
            style: TextStyle(
              color: TabangNowTheme.of(context).textMuted,
              fontSize: 13,
              height: 1.45,
            ),
          ),
          if (canCreate) ...<Widget>[
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: onCreate,
                icon: const Icon(Icons.add_rounded),
                label: const Text('Report Incident'),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _panelDecoration(context),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            title,
            style: TextStyle(
              color: TabangNowTheme.of(context).textMain,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            subtitle,
            style: TextStyle(
              color: TabangNowTheme.of(context).textMuted,
              fontSize: 12,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }
}

class _IncidentReportCard extends StatelessWidget {
  const _IncidentReportCard({
    required this.incident,
    required this.canDelete,
    required this.onView,
    required this.onDelete,
  });

  final Map<String, dynamic> incident;
  final bool canDelete;
  final VoidCallback onView;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final id = incident['id']?.toString() ?? '—';
    final code = incident['incident_code']?.toString().trim() ?? '';
    final title = incident['title']?.toString() ?? 'Untitled Incident';
    final description =
        incident['description']?.toString() ?? 'No description provided.';
    final priority = incident['priority']?.toString() ?? 'low';
    final severity =
        incident['severity_label']?.toString() ?? _labelFromKey(priority);
    final barangay = incident['barangay']?.toString() ?? '—';
    final exactLocation = incident['location_address']?.toString().trim() ?? '';
    final status = incident['status']?.toString().trim().isNotEmpty == true
        ? incident['status'].toString()
        : 'Pending';

    final reportedRaw =
        incident['reported_at'] ??
        incident['incident_datetime'] ??
        incident['created_at'];

    final reported = _parseDate(reportedRaw);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: _panelDecoration(context),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  '#$id${code.isEmpty ? '' : ' • $code'}',
                  style: TextStyle(
                    color: TabangNowTheme.of(context).textSoft,
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              _StatusBadge(status: status),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            title,
            style: TextStyle(
              color: TabangNowTheme.of(context).textMain,
              fontSize: 17,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            description,
            maxLines: 3,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: TabangNowTheme.of(context).textMuted,
              fontSize: 13,
              height: 1.45,
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: <Widget>[
              _SeverityBadge(priority: priority, label: severity),
            ],
          ),
          const SizedBox(height: 14),
          _MetaRow(
            icon: Icons.location_on_outlined,
            label: 'Location',
            value: exactLocation.isEmpty
                ? barangay
                : '$barangay\n$exactLocation',
          ),
          const SizedBox(height: 10),
          _MetaRow(
            icon: Icons.schedule_rounded,
            label: 'Reported',
            value: reported == null
                ? '—'
                : '${_formatDate(reported)}\n'
                      '${_formatTime(reported)} • '
                      '${_relative(reported)}',
          ),
          const SizedBox(height: 16),
          Row(
            children: <Widget>[
              Expanded(
                child: FilledButton.icon(
                  onPressed: onView,
                  icon: const Icon(Icons.visibility_outlined),
                  label: const Text('View'),
                ),
              ),
              if (canDelete) ...<Widget>[
                const SizedBox(width: 10),
                IconButton.filledTonal(
                  onPressed: onDelete,
                  tooltip: 'Delete incident',
                  style: IconButton.styleFrom(
                    foregroundColor: const Color(0xFFB91C1C),
                    backgroundColor: const Color(0xFFFEF2F2),
                  ),
                  icon: const Icon(Icons.delete_outline_rounded),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

class _MetaRow extends StatelessWidget {
  const _MetaRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Icon(icon, size: 19, color: TabangNowTheme.of(context).textMuted),
        const SizedBox(width: 9),
        SizedBox(
          width: 68,
          child: Text(
            label,
            style: TextStyle(
              color: TabangNowTheme.of(context).textMuted,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: TextStyle(
              color: TabangNowTheme.of(context).textSoft,
              fontSize: 12,
              fontWeight: FontWeight.w600,
              height: 1.4,
            ),
          ),
        ),
      ],
    );
  }
}

class _SeverityBadge extends StatelessWidget {
  const _SeverityBadge({required this.priority, required this.label});

  final String priority;
  final String label;

  @override
  Widget build(BuildContext context) {
    final normalized = priority.trim().toLowerCase();

    final (background, foreground, border) = switch (normalized) {
      'critical' || 'emergency' => (
        const Color(0xFFFEE2E2),
        const Color(0xFFB91C1C),
        const Color(0xFFFECACA),
      ),
      'high' => (
        const Color(0xFFFFEDD5),
        const Color(0xFFC2410C),
        const Color(0xFFFED7AA),
      ),
      'moderate' || 'medium' => (
        const Color(0xFFFEF9C3),
        const Color(0xFFA16207),
        const Color(0xFFFEF08A),
      ),
      'low' => (
        const Color(0xFFDCFCE7),
        const Color(0xFF15803D),
        const Color(0xFFBBF7D0),
      ),
      _ => (
        TabangNowTheme.of(context).surfaceSoft,
        TabangNowTheme.of(context).textSoft,
        TabangNowTheme.of(context).border,
      ),
    };

    return _Badge(
      text: label,
      background: background,
      foreground: foreground,
      border: border,
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final normalized = status.trim().toLowerCase();

    final (background, foreground, border) = switch (normalized) {
      'resolved' || 'completed' || 'closed' => (
        const Color(0xFFDCFCE7),
        const Color(0xFF15803D),
        const Color(0xFFBBF7D0),
      ),
      'responding' ||
      'dispatched' ||
      'in progress' ||
      'in_progress' ||
      'verified' ||
      'validated' => (
        const Color(0xFFDBEAFE),
        const Color(0xFF1D4ED8),
        const Color(0xFFBFDBFE),
      ),
      'pending' || 'reported' => (
        const Color(0xFFFEF9C3),
        const Color(0xFFA16207),
        const Color(0xFFFEF08A),
      ),
      'cancelled' || 'canceled' || 'rejected' || 'invalid' => (
        const Color(0xFFFEE2E2),
        const Color(0xFFB91C1C),
        const Color(0xFFFECACA),
      ),
      _ => (
        TabangNowTheme.of(context).surfaceSoft,
        TabangNowTheme.of(context).textSoft,
        TabangNowTheme.of(context).border,
      ),
    };

    return _Badge(
      text: _labelFromKey(status),
      background: background,
      foreground: foreground,
      border: border,
    );
  }
}

class _Badge extends StatelessWidget {
  const _Badge({
    required this.text,
    required this.background,
    required this.foreground,
    required this.border,
  });

  final String text;
  final Color background;
  final Color foreground;
  final Color border;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: background,
        border: Border.all(color: border),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: foreground,
          fontSize: 11,
          fontWeight: FontWeight.w800,
        ),
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
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: _panelDecoration(context),
      child: Column(
        children: <Widget>[
          const Icon(
            Icons.error_outline_rounded,
            size: 44,
            color: Color(0xFFB91C1C),
          ),
          const SizedBox(height: 10),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Try Again'),
          ),
        ],
      ),
    );
  }
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(28),
      decoration: _panelDecoration(context),
      child: Column(
        children: <Widget>[
          Icon(
            Icons.description_outlined,
            size: 46,
            color: TabangNowTheme.of(context).textMuted,
          ),
          SizedBox(height: 12),
          Text(
            'No incident records were found.',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontWeight: FontWeight.w700,
              color: TabangNowTheme.of(context).textSoft,
            ),
          ),
        ],
      ),
    );
  }
}

BoxDecoration _panelDecoration(BuildContext context) {
  return BoxDecoration(
    color: TabangNowTheme.of(context).surface,
    border: Border.all(color: TabangNowTheme.of(context).border),
    borderRadius: BorderRadius.circular(16),
    boxShadow: const <BoxShadow>[
      BoxShadow(color: Color(0x08000000), blurRadius: 8, offset: Offset(0, 2)),
    ],
  );
}

String _labelFromKey(String value) {
  final trimmed = value.trim();

  if (trimmed.isEmpty) {
    return 'Unknown';
  }

  return trimmed
      .replaceAll('_', ' ')
      .split(RegExp(r'\s+'))
      .map(
        (part) => part.isEmpty
            ? ''
            : '${part[0].toUpperCase()}${part.substring(1).toLowerCase()}',
      )
      .join(' ');
}

DateTime? _parseDate(Object? value) {
  final raw = value?.toString().trim();

  if (raw == null || raw.isEmpty) {
    return null;
  }

  return DateTime.tryParse(raw)?.toLocal();
}

String _formatDate(DateTime value) {
  const months = <String>[
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

  return '${months[value.month - 1]} '
      '${value.day.toString().padLeft(2, '0')}, '
      '${value.year}';
}

String _formatTime(DateTime value) {
  final hour12 = value.hour % 12 == 0 ? 12 : value.hour % 12;
  final minute = value.minute.toString().padLeft(2, '0');
  final suffix = value.hour >= 12 ? 'PM' : 'AM';

  return '${hour12.toString().padLeft(2, '0')}:$minute $suffix';
}

String _relative(DateTime value) {
  final difference = DateTime.now().difference(value);

  if (difference.isNegative) {
    return 'just now';
  }

  if (difference.inMinutes < 1) {
    return 'just now';
  }

  if (difference.inHours < 1) {
    final minutes = difference.inMinutes;
    return '$minutes min${minutes == 1 ? '' : 's'} ago';
  }

  if (difference.inDays < 1) {
    final hours = difference.inHours;
    return '$hours hr${hours == 1 ? '' : 's'} ago';
  }

  final days = difference.inDays;
  return '$days day${days == 1 ? '' : 's'} ago';
}
