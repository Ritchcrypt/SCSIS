import 'package:flutter/material.dart';

import '../core/app_capabilities.dart';
import '../core/app_role.dart';
import '../core/tabangnow_theme.dart';
import '../services/auth_service.dart';
import '../services/emergency_hotline_service.dart';

class EmergencyHotlinesScreen extends StatefulWidget {
  const EmergencyHotlinesScreen({
    super.key,
    required this.authService,
    required this.user,
  });

  final AuthService authService;
  final Map<String, dynamic> user;

  @override
  State<EmergencyHotlinesScreen> createState() =>
      _EmergencyHotlinesScreenState();
}

class _EmergencyHotlinesScreenState extends State<EmergencyHotlinesScreen> {
  late final EmergencyHotlineService _service;

  bool _loading = true;
  String? _error;

  List<Map<String, dynamic>> _hotlines = <Map<String, dynamic>>[];
  List<Map<String, dynamic>> _colors = <Map<String, dynamic>>[];

  AppRole get _role => AppRoleX.fromRaw(
    widget.user['role']?.toString().trim().toLowerCase() ?? '',
  );

  AppCapabilitySet get _capabilities => AppCapabilities.forRole(_role);

  bool get _canManage =>
      _capabilities.allows(AppCapability.manageEmergencyHotlines);

  @override
  void initState() {
    super.initState();

    _service = EmergencyHotlineService(authService: widget.authService);

    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final response = await _service.index();

      if (!mounted) {
        return;
      }

      final options = _map(response['options']);

      setState(() {
        _hotlines = _mapList(response['data']);
        _colors = _mapList(options['colors']);
        _loading = false;
        _error = null;
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

  Future<void> _openAdd() async {
    if (!_canManage) {
      return;
    }

    final message = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Theme.of(context).colorScheme.surface,
      builder: (sheetContext) =>
          _AddHotlineSheet(service: _service, colors: _colors),
    );

    if (message == null || !mounted) {
      return;
    }

    _show(message);
    await _load();
  }

  Future<void> _delete(Map<String, dynamic> hotline) async {
    if (!_canManage) {
      return;
    }

    final id = _int(hotline['id']);

    if (id <= 0) {
      return;
    }

    final agency = _text(hotline['agency_name'], 'this hotline');

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Remove Hotline'),
        content: Text('Remove "$agency" from Emergency Hotlines?'),
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
            child: const Text('Remove'),
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
        response['message']?.toString() ??
            'Emergency hotline removed successfully.',
      );

      await _load();
    } catch (exception) {
      if (mounted) {
        _show(exception.toString().replaceFirst('AuthException: ', ''));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 18, 16, 32),
        children: <Widget>[
          _Header(canManage: _canManage, onAdd: _openAdd),
          const SizedBox(height: 12),
          const _GuidanceCard(),
          const SizedBox(height: 16),

          if (_loading)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 100),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_error != null)
            _ErrorCard(message: _error!, onRetry: _load)
          else if (_hotlines.isEmpty)
            _EmptyCard(canManage: _canManage)
          else
            for (var index = 0; index < _hotlines.length; index++) ...<Widget>[
              _HotlineCard(
                hotline: _hotlines[index],
                canManage: _canManage,
                onDelete: () => _delete(_hotlines[index]),
              ),
              if (index != _hotlines.length - 1) const SizedBox(height: 12),
            ],

          const SizedBox(height: 10),
          Text(
            'Hotlines are ordered by configured sort order, then agency name.',
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
  const _Header({required this.canManage, required this.onAdd});

  final bool canManage;
  final VoidCallback onAdd;

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
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Text('📞', style: TextStyle(fontSize: 30)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'Emergency Hotlines',
                  style: TextStyle(
                    color: palette.textMain,
                    fontSize: 24,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  'Emergency contact numbers for immediate reference.',
                  style: TextStyle(color: palette.textMuted),
                ),
                if (canManage) ...<Widget>[
                  const SizedBox(height: 14),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      onPressed: onAdd,
                      icon: const Icon(Icons.add_rounded),
                      label: const Text('Add Hotline'),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _GuidanceCard extends StatelessWidget {
  const _GuidanceCard();

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    final background = palette.isDark
        ? const Color(0xFF422006)
        : const Color(0xFFFFFBEB);

    final textColor = palette.isDark
        ? const Color(0xFFFDE68A)
        : const Color(0xFF92400E);

    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: textColor.withValues(alpha: 0.25)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Text('⚠️', style: TextStyle(fontSize: 18)),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'Use these hotline numbers only for emergencies that require immediate response beyond what the barangay can handle alone. Please also inform the barangay through this system so the incident can be monitored and documented.',
              style: TextStyle(
                color: textColor,
                fontSize: 12,
                height: 1.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _HotlineCard extends StatelessWidget {
  const _HotlineCard({
    required this.hotline,
    required this.canManage,
    required this.onDelete,
  });

  final Map<String, dynamic> hotline;
  final bool canManage;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    final colorName = _text(hotline['color'], 'blue').toLowerCase();

    final accent = _hotlineAccent(palette, colorName);

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: palette.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: accent.withValues(alpha: 0.40)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            width: 14,
            height: 14,
            margin: const EdgeInsets.only(top: 5),
            decoration: BoxDecoration(color: accent, shape: BoxShape.circle),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  _text(hotline['agency_name'], 'Emergency Hotline'),
                  style: TextStyle(
                    color: palette.textMain,
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 13),
                SelectableText(
                  _text(hotline['hotline_number'], 'No number'),
                  style: TextStyle(
                    color: accent,
                    fontSize: 26,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 0.7,
                  ),
                ),
              ],
            ),
          ),
          if (canManage)
            IconButton(
              tooltip: 'Remove hotline',
              onPressed: onDelete,
              color: const Color(0xFFDC2626),
              icon: const Icon(Icons.delete_outline_rounded),
            ),
        ],
      ),
    );
  }
}

class _AddHotlineSheet extends StatefulWidget {
  const _AddHotlineSheet({required this.service, required this.colors});

  final EmergencyHotlineService service;
  final List<Map<String, dynamic>> colors;

  @override
  State<_AddHotlineSheet> createState() => _AddHotlineSheetState();
}

class _AddHotlineSheetState extends State<_AddHotlineSheet> {
  final _formKey = GlobalKey<FormState>();

  final _agencyController = TextEditingController();

  final _numberController = TextEditingController();

  String _color = 'blue';
  bool _saving = false;

  @override
  void initState() {
    super.initState();

    final values = widget.colors
        .map((item) => item['value']?.toString() ?? '')
        .where((value) => value.isNotEmpty)
        .toSet();

    if (!values.contains(_color) && values.isNotEmpty) {
      _color = values.first;
    }
  }

  @override
  void dispose() {
    _agencyController.dispose();
    _numberController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_saving || !_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _saving = true;
    });

    try {
      final response = await widget.service.create(
        agencyName: _agencyController.text,
        hotlineNumber: _numberController.text,
        color: _color,
      );

      if (!mounted) {
        return;
      }

      Navigator.of(context).pop(
        response['message']?.toString() ??
            'Emergency hotline added successfully.',
      );
    } catch (exception) {
      if (!mounted) {
        return;
      }

      setState(() {
        _saving = false;
      });

      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(
            content: Text(
              exception.toString().replaceFirst('AuthException: ', ''),
            ),
          ),
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    final bottom = MediaQuery.viewInsetsOf(context).bottom;

    return SafeArea(
      child: SingleChildScrollView(
        padding: EdgeInsets.fromLTRB(16, 16, 16, 18 + bottom),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                'Add Emergency Hotline',
                style: TextStyle(
                  color: palette.textMain,
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _agencyController,
                decoration: const InputDecoration(
                  labelText: 'Agency / Office Name',
                  hintText: 'Example: Rural Health Unit',
                ),
                validator: (value) {
                  if (value == null || value.trim().isEmpty) {
                    return 'Agency / Office Name is required.';
                  }

                  return null;
                },
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _numberController,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'Hotline Number',
                  hintText: 'Example: 166',
                ),
                validator: (value) {
                  final text = value?.trim() ?? '';

                  if (text.isEmpty) {
                    return 'Hotline Number is required.';
                  }

                  if (!RegExp(r'^[0-9+()\-\.\s\/]*$').hasMatch(text)) {
                    return 'Use only valid hotline number characters.';
                  }

                  return null;
                },
              ),
              const SizedBox(height: 14),
              DropdownButtonFormField<String>(
                initialValue: _color,
                decoration: const InputDecoration(labelText: 'Card Color'),
                items: widget.colors
                    .map(
                      (option) => DropdownMenuItem<String>(
                        value: option['value']?.toString(),
                        child: Text(
                          option['label']?.toString() ??
                              option['value']?.toString() ??
                              '',
                        ),
                      ),
                    )
                    .toList(growable: false),
                onChanged: _saving
                    ? null
                    : (value) {
                        if (value == null) {
                          return;
                        }

                        setState(() {
                          _color = value;
                        });
                      },
              ),
              const SizedBox(height: 20),
              Row(
                children: <Widget>[
                  Expanded(
                    child: OutlinedButton(
                      onPressed: _saving
                          ? null
                          : () => Navigator.of(context).pop(),
                      child: const Text('Cancel'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: FilledButton(
                      onPressed: _saving ? null : _save,
                      child: _saving
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Text('Save'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({required this.canManage});

  final bool canManage;

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
          const Text('📞', style: TextStyle(fontSize: 34)),
          const SizedBox(height: 12),
          Text(
            'No emergency hotlines available',
            style: TextStyle(
              color: palette.textMain,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            canManage
                ? 'Add an emergency hotline to make it available for all roles.'
                : 'Emergency hotlines added by admin or official will appear here.',
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

Color _hotlineAccent(TabangNowTheme palette, String color) {
  return switch (color) {
    'red' => palette.isDark ? const Color(0xFFFCA5A5) : const Color(0xFFDC2626),
    'orange' =>
      palette.isDark ? const Color(0xFFFDBA74) : const Color(0xFFF97316),
    'green' =>
      palette.isDark ? const Color(0xFF86EFAC) : const Color(0xFF16A34A),
    'purple' =>
      palette.isDark ? const Color(0xFFD8B4FE) : const Color(0xFF9333EA),
    'slate' =>
      palette.isDark ? const Color(0xFFCBD5E1) : const Color(0xFF475569),
    _ => palette.isDark ? const Color(0xFF93C5FD) : const Color(0xFF2563EB),
  };
}
