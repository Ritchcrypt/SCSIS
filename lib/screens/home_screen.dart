import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';

import '../core/tabangnow_theme.dart';
import '../widgets/global_theme_button.dart';
import '../widgets/global_notification_bell.dart';
import '../services/notification_center_service.dart';

import '../core/app_capabilities.dart';
import '../core/app_module.dart';
import '../core/app_role.dart';
import '../core/module_registry.dart';

import '../services/auth_service.dart';
import '../services/branding_service.dart';
import '../services/incident_service.dart';
import 'incident_detail_screen.dart';
import 'incidents_screen.dart' as incident_ui;
import 'tanod_alerts_screen.dart';
import 'tanod_roster_screen.dart';
import 'login_screen.dart';
import 'system_branding_screen.dart';

const String _tabangNowWebsiteLogoBase64 =
    'iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAABmJLR0QA/wD/AP+gvaeTAAAHNklEQVRYheWXa2xUxxmGn9mrd9eG9eK1MXYIxkAoqFFpKTQyhqSiiLRpLVIIIeIihERRpJY/URNVhDZtKqSoaWlRo1apWigtEqpwYiVRMEUQIOaSpDSUNuZiwGvwsgZ7b97b2XNmpj/WXrzeXKDqv4x0dI50ZuZ7v3fe9zvfgc/7EHc/VYvmDVe+rKTVhlatDqHrXQ7ZqLUkn5fXTcuKCNRxLa2OUPvis/9HAFrM2HBtlQ31s0AVs7xujVISJRWGaYGWOB1gFwqBIpXNMxQ3LkplbutrX3YAhP6fAczY0NcsMPfV+sUCt1NzK5YnnTVBS7RWI3cJWoJWaC3xuSHod5IzTG5GU6dtln4q9MZ3rt0zgOnrryzyOEV7Q40tGB40SOdMGBe0FIQCdeed122jvsZFXyQZNbJqVf/bjx+5awDN63sfqfSozpoq4QxFckhVmiVaMvv+Cr67pAatNAfeCdN9LTkGWGGuQDGt3sPtaDofTxvLIm8/dewzATywrrfJ5rTeqw/Ya3pvZkuy9PsEyxf6WfP1IFOn+BjKaLSGaq8gPJDi9WP9HP0gwuW+eMm6pnov4cFU1MzJhTc61/V8CgAtZq7vOd002bHgajiLVBKnXdP6RR8rFlWzZJ6fhCHImBqPUxDwAhoSOXBHI1RcuYCIhMnVNdCVrWbrny6Ry+WxoZjeWElPX+xUuHNjy1hh2kupX7e6rlpsHUyYRYWvWDSRHVua8FRWYEhBwAMBr8DrGkEvwJe8jf75T1CnTmKfXIfat5fpOk7j0hYOnY2gtCSXM5g00XWfmNx1PnX1re7RmI6x2du59FO3wzZG6QWhDeegrlLgdoLWoAGl4YN/XUEDC7MD6GQSd1sb7tWrwW4nf/Ag8xYsLeoinZXUVbsQgheBA6NRbaMP09dd/EqgilkDMQO0VVyotCoJOnr9/d1zrHz6JVY9/RKZWBz7zJmF4IBn82ZstbU4TaPENQPRYWr8ztnBxS/PKwOgldXmrYBM1ixTs2Yk8xEg+998l00/fAVb1XLsEx/jUtrC7Ooi9cwzACTXrkWGQqQ8E9BKFuypJKlUFo/LhrCptjIAKLVIyZGgIwtQEpQqvB5h4Fd/fJNnd+zDNWkVTt8cHJ5Z/OJ0HcnVa7G6u0msWIFOJHA9+yMOXJbj6saInZVsLdOAXcgGy2JMMVFFDSgNllQ8t2MvHYfP4axZh80ZKGI/c83PxuyDfH9lgGnVbpof/AKRqgYGTr6D1lZJ/bCkhd1GQxkDToeqz5vWGLQFF2QNCyEgFk/Ttmw+f9m1rST46Lgc8VIxp4WQfwpXxUQUgkzWKGMgn8/jcOgigDsu0Aq0KCu1sWEDhw28E6pomT+X7l6DTU/YuNqX49GHKtHAwVMppk+twOerYN6cuaTyBafHk5kSNsfsrcoYMPJW2OUom8jF3jheB2SMOyJs7xzi/MU08+f6+OpcH+cvpmnvHCqKNZcHpx0uhwZK2NRa4nII8oYZLgMgldVvE+M/NhZDiSxXw8OYUhedEEtY5IxiEuQMRSxhFYjUIDX03hgiFk+VJWQTGkvmiwCKR6CVPCGwP1KmWK14/ViI5d+ay3thSWvAzrZNtSDgo54MAFvX1KA0BKsdvBWRzPTZeOPw+YKTdKmoBQqtrGNlAISWHalMfrvPpUllSxf+rbOHLz08k93X4UqNnY2LJ1JpF2Pqg5ekpfnzDUVXVLJlcp7XDv2zTE+VHgepTBalZUfRfaMPie49kYqmC0/W17hq4sncGAYkubyJM5tl+/KpHL6t2HNd0pfT3MhqPhrWHBpS/D4kcQnBc80O9u85xL8v9pU1LQ11E7g5EOuOnnl5exkAeAHfA09GPC7xhJIS0zRLjuHCtUGag142zw9yv9NGDgjnNHETgi7BN6sdrGywc/jIOf6w/3hZ/+DzOLDbNPHU8Pdy/WcuFJkvdbMWjd9uPzmjwfe1nusJlLKK9BUEpHh+SwuPPTybwTR4nQXnZgzwe6DzxH/48c4OpGWWHKFNaJrvC3ApFOmKntnZSsFMpS4YVYJl5teEbiYGp9V7xmxSKFBSWrzw26P8Zu9J/O5ChVQKqlyKXXuP8vwv28cFH2lIGqsJ9d+OSktuGBv8YxgojMmP7lsywSMOBf0uV29/HKlKy6nWkhlTA/xgQysozc7dR+jpvVnaH2qJEIqmhgADt+L54VR2aezsrhPjY31iUzrlG7tb3G5ea6zzBMO3kqQyY8qqGl/dyjtkn8fBlGAVof5b0axhrIz/45WjHxfnU9vyumWvNtmV9dfaSRUPeVyCgaEUqXS2JMvxIHweB7UBH7mcQfhWrEuZxtr4h7/r/aQYd/VjUvvIrx93CF6c5HfN9lbY0aqgByNfOG+XU2ATAIpUOsNQNNltKmtb/P1d7Z+1+z38mkFw8Y55AtWGlK02u57idIhGrSWmkb9hSiuMlseVVh3x93d+eC/7fr7HfwG1yvkF9BfLIQAAAABJRU5ErkJggg==';

final _tabangNowWebsiteLogoBytes = base64Decode(_tabangNowWebsiteLogoBase64);

enum _HomeModule {
  dashboard,
  incidents,
  tanodAlerts,
  tanodRoster,
  announcements,
  hotlines,
}

class _NavItem {
  const _NavItem({
    required this.label,
    required this.icon,
    required this.glyph,
    this.module,
    this.pending = false,
  });

  final String label;
  final IconData icon;
  final String glyph;
  final _HomeModule? module;
  final bool pending;
}

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, required this.user});

  final Map<String, dynamic> user;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  static const Color _navy = Color(0xFF172554);
  Color get _activeBlue => Theme.of(context).colorScheme.primary;
  Color get _contentBackground => Theme.of(context).scaffoldBackgroundColor;

  final GlobalKey<ScaffoldState> _scaffoldKey = GlobalKey<ScaffoldState>();
  final AuthService _authService = AuthService();
  late final BrandingService _brandingService;

  String _systemName = 'TabangNow';
  String _systemSubtitle = 'Dao, Capiz';
  Uint8List? _systemLogoBytes;

  _HomeModule _selectedModule = _HomeModule.dashboard;

  bool _dashboardLoading = true;
  bool _announcementsLoading = true;
  bool _hotlinesLoading = true;
  bool _loggingOut = false;

  String? _dashboardError;
  String? _announcementsError;
  String? _hotlinesError;

  Map<String, dynamic> _dashboard = <String, dynamic>{};
  List<Map<String, dynamic>> _announcements = <Map<String, dynamic>>[];
  List<Map<String, dynamic>> _hotlines = <Map<String, dynamic>>[];

  final Key _incidentsKey = UniqueKey();
  Key _tanodAlertsKey = UniqueKey();

  String get _role =>
      (widget.user['role']?.toString() ?? '').trim().toLowerCase();

  AppRole get _appRole => AppRoleX.fromRaw(_role);

  AppCapabilitySet get _capabilities => AppCapabilities.forRole(_appRole);
  String get _userName => (widget.user['name']?.toString() ?? 'User').trim();

  @override
  void initState() {
    super.initState();
    _brandingService = BrandingService(authService: _authService);
    _loadInitialData();
  }

  Future<void> _loadInitialData() async {
    await Future.wait(<Future<void>>[
      _loadBranding(),
      _loadDashboard(),
      _loadAnnouncements(),
      _loadHotlines(),
    ]);
  }

  Future<void> _loadBranding() async {
    try {
      final response = await _brandingService.branding();
      final rawData = response['data'];
      final data = rawData is Map
          ? Map<String, dynamic>.from(rawData)
          : <String, dynamic>{};

      Uint8List? logoBytes;
      if (data['has_logo'] == true) {
        try {
          logoBytes = await _brandingService.logoBytes();
        } catch (_) {
          logoBytes = null;
        }
      }

      if (!mounted) {
        return;
      }

      setState(() {
        final name = data['system_name']?.toString().trim() ?? '';
        final subtitle = data['system_subtitle']?.toString().trim() ?? '';

        _systemName = name.isEmpty ? 'TabangNow' : name;
        _systemSubtitle = subtitle.isEmpty ? 'Dao, Capiz' : subtitle;
        _systemLogoBytes = logoBytes;
      });
    } catch (_) {
      // Keep the established TabangNow fallback branding.
    }
  }

  Future<void> _openSystemBranding() async {
    if (!_capabilities.allows(AppCapability.manageSystemBranding)) {
      return;
    }

    Navigator.of(context).pop();

    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => SystemBrandingScreen(authService: _authService),
      ),
    );

    if (changed == true) {
      await _loadBranding();
    }
  }

  Future<void> _loadDashboard() async {
    if (mounted) {
      setState(() {
        _dashboardLoading = true;
        _dashboardError = null;
      });
    }

    try {
      final response = await _authService.dashboard();
      final rawData = response['data'];

      if (!mounted) {
        return;
      }

      setState(() {
        _dashboard = rawData is Map
            ? Map<String, dynamic>.from(rawData)
            : <String, dynamic>{};
        _dashboardLoading = false;
      });
    } on AuthException catch (exception) {
      if (!mounted) {
        return;
      }

      setState(() {
        _dashboardLoading = false;
        _dashboardError = exception.message;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        _dashboardLoading = false;
        _dashboardError = 'Unable to load dashboard.';
      });
    }
  }

  Future<void> _loadAnnouncements() async {
    if (mounted) {
      setState(() {
        _announcementsLoading = true;
        _announcementsError = null;
      });
    }

    try {
      final response = await _authService.announcements();

      if (!mounted) {
        return;
      }

      setState(() {
        _announcements = _mapList(response['data']);
        _announcementsLoading = false;
      });
    } on AuthException catch (exception) {
      if (!mounted) {
        return;
      }

      setState(() {
        _announcementsLoading = false;
        _announcementsError = exception.message;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        _announcementsLoading = false;
        _announcementsError = 'Unable to load announcements.';
      });
    }
  }

  Future<void> _loadHotlines() async {
    if (mounted) {
      setState(() {
        _hotlinesLoading = true;
        _hotlinesError = null;
      });
    }

    try {
      final response = await _authService.emergencyHotlines();

      if (!mounted) {
        return;
      }

      setState(() {
        _hotlines = _mapList(response['data']);
        _hotlinesLoading = false;
      });
    } on AuthException catch (exception) {
      if (!mounted) {
        return;
      }

      setState(() {
        _hotlinesLoading = false;
        _hotlinesError = exception.message;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        _hotlinesLoading = false;
        _hotlinesError = 'Unable to load emergency hotlines.';
      });
    }
  }

  Future<void> _logout() async {
    if (_loggingOut) {
      return;
    }

    setState(() {
      _loggingOut = true;
    });

    try {
      await _authService.logout();
    } catch (_) {
      await _authService.clearToken();
    }

    if (!mounted) {
      return;
    }

    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute<void>(builder: (_) => const LoginScreen()),
      (route) => false,
    );
  }

  void _selectModule(_HomeModule module) {
    final moduleId = _moduleIdForHomeModule(module);

    if (!ModuleRegistry.canAccess(_appRole, moduleId)) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            content: Text(
              'This module is not available for this account role.',
            ),
          ),
        );

      return;
    }

    setState(() {
      _selectedModule = module;
    });

    final scaffoldState = _scaffoldKey.currentState;

    if (scaffoldState != null && scaffoldState.isDrawerOpen) {
      Navigator.of(context).pop();
    }
  }

  Future<void> _openTanodAlertRelated(String target, int? sourceId) async {
    if (target == 'incident' && sourceId != null) {
      final incidentService = IncidentService(authService: _authService);

      await Navigator.of(context).push<bool>(
        MaterialPageRoute<bool>(
          builder: (_) => IncidentDetailScreen(
            incidentService: incidentService,
            incidentId: sourceId,
            user: widget.user,
          ),
        ),
      );

      if (mounted) {
        setState(() {
          _tanodAlertsKey = UniqueKey();
        });
      }

      return;
    }

    if (target == 'announcements') {
      _selectModule(_HomeModule.announcements);
    }
  }

  void _showPendingModule(String label) {
    Navigator.of(context).pop();

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          '$label is part of the website-parity work and is not connected yet.',
        ),
      ),
    );
  }

  Future<void> _showAccountMenu() async {
    final shouldLogout = await showModalBottomSheet<bool>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                ListTile(
                  leading: CircleAvatar(
                    backgroundColor: const Color(0xFF2563EB),
                    child: Text(
                      _initials(_userName),
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  title: Text(
                    _userName,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  subtitle: Text(_roleLabel(_role)),
                ),
                const Divider(),
                ListTile(
                  leading: const Icon(
                    Icons.logout_rounded,
                    color: Color(0xFFB91C1C),
                  ),
                  title: const Text(
                    'Logout',
                    style: TextStyle(
                      color: Color(0xFFB91C1C),
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  onTap: () => Navigator.of(sheetContext).pop(true),
                ),
              ],
            ),
          ),
        );
      },
    );

    if (shouldLogout == true && mounted) {
      await _logout();
    }
  }

  Future<void> _openGlobalNotification(NotificationOpenTarget target) async {
    switch (target.module) {
      case 'dashboard':
        _selectModule(_HomeModule.dashboard);
        return;

      case 'incidents':
        if (target.sourceId != null &&
            ModuleRegistry.canAccess(_appRole, AppModuleId.incidents)) {
          final service = IncidentService(authService: _authService);

          await Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) => IncidentDetailScreen(
                incidentService: service,
                incidentId: target.sourceId!,
                user: widget.user,
              ),
            ),
          );

          return;
        }

        _selectModule(_HomeModule.incidents);
        return;

      case 'tanodAlerts':
        if (ModuleRegistry.canAccess(_appRole, AppModuleId.tanodAlerts)) {
          _selectModule(_HomeModule.tanodAlerts);
          return;
        }

        break;

      case 'announcements':
        _selectModule(_HomeModule.announcements);
        return;

      case 'tanodTasks':
        _showPendingModule('Tanod Tasks');
        return;

      case 'residentComplaints':
        _showPendingModule(
          _appRole == AppRole.resident
              ? 'Complaints Form'
              : 'Resident Complaints',
        );
        return;

      case 'userManagement':
        _showPendingModule('User Management');
        return;

      default:
        break;
    }

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        const SnackBar(
          content: Text(
            'That notification target is not available for this account.',
          ),
        ),
      );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      key: _scaffoldKey,
      backgroundColor: _contentBackground,
      drawer: _buildDrawer(),
      appBar: AppBar(
        toolbarHeight: 64,
        backgroundColor: theme.colorScheme.surface,
        surfaceTintColor: theme.colorScheme.surface,
        foregroundColor: theme.colorScheme.onSurface,
        elevation: 0,
        scrolledUnderElevation: 0,
        shadowColor: Colors.black12,
        shape: Border(bottom: BorderSide(color: theme.dividerColor)),
        leadingWidth: 72,
        leading: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
          child: DecoratedBox(
            decoration: BoxDecoration(
              color: theme.colorScheme.surface,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: theme.dividerColor),
              boxShadow: const <BoxShadow>[
                BoxShadow(
                  color: Color(0x0A0F172A),
                  blurRadius: 4,
                  offset: Offset(0, 1),
                ),
              ],
            ),
            child: IconButton(
              tooltip: 'Open menu',
              padding: EdgeInsets.zero,
              onPressed: () => _scaffoldKey.currentState?.openDrawer(),
              icon: Icon(
                Icons.menu_rounded,
                size: 24,
                color: theme.colorScheme.onSurface,
              ),
            ),
          ),
        ),
        title: null,
        titleSpacing: 0,
        actions: <Widget>[
          GlobalThemeButton(user: widget.user, authService: _authService),
          const SizedBox(width: 8),
          GlobalNotificationBell(
            authService: _authService,
            onOpen: _openGlobalNotification,
          ),
          const SizedBox(width: 16),
        ],
      ),
      body: SafeArea(top: false, child: _buildSelectedModule()),
    );
  }

  Widget _buildSelectedModule() {
    switch (_selectedModule) {
      case _HomeModule.dashboard:
        return _buildDashboard();
      case _HomeModule.incidents:
        return KeyedSubtree(
          key: _incidentsKey,
          child: incident_ui.IncidentsScreen(
            authService: _authService,
            user: widget.user,
          ),
        );
      case _HomeModule.tanodAlerts:
        return KeyedSubtree(
          key: _tanodAlertsKey,
          child: TanodAlertsScreen(
            authService: _authService,
            user: widget.user,
            onOpenRelated: _openTanodAlertRelated,
          ),
        );
      case _HomeModule.tanodRoster:
        return TanodRosterScreen(authService: _authService, user: widget.user);
      case _HomeModule.announcements:
        return _buildAnnouncements();
      case _HomeModule.hotlines:
        return _buildHotlines();
    }
  }

  Widget _buildBrandHeader() {
    final logoBytes = _systemLogoBytes ?? _tabangNowWebsiteLogoBytes;

    final content = Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: Color(0xFF1E3A8A), width: 1)),
      ),
      child: Row(
        children: <Widget>[
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: Container(
              width: 44,
              height: 44,
              color: _activeBlue,
              child: Image.memory(
                logoBytes,
                width: 44,
                height: 44,
                fit: BoxFit.cover,
                filterQuality: FilterQuality.high,
                gaplessPlayback: true,
                errorBuilder: (context, error, stackTrace) {
                  return const Center(
                    child: Icon(
                      Icons.shield_rounded,
                      color: Colors.white,
                      size: 24,
                    ),
                  );
                },
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Text(
                  _systemName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    height: 1.2,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  _systemSubtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Color(0xFFBFDBFE),
                    fontSize: 14,
                    height: 1.2,
                    fontWeight: FontWeight.w400,
                  ),
                ),
              ],
            ),
          ),
          if (_capabilities.allows(
            AppCapability.manageSystemBranding,
          )) ...<Widget>[
            const SizedBox(width: 8),
            const Icon(
              Icons.chevron_right_rounded,
              color: Color(0xFFBFDBFE),
              size: 20,
            ),
          ],
        ],
      ),
    );

    if (!_capabilities.allows(AppCapability.manageSystemBranding)) {
      return content;
    }

    return Material(
      color: Colors.transparent,
      child: InkWell(onTap: _openSystemBranding, child: content),
    );
  }

  Widget _buildDrawer() {
    final items = _navigationItemsForRole();

    return Drawer(
      width: 288,
      backgroundColor: _navy,
      shape: const RoundedRectangleBorder(),
      child: SafeArea(
        child: Column(
          children: <Widget>[
            _buildBrandHeader(),
            Expanded(
              child: ListView.separated(
                padding: const EdgeInsets.fromLTRB(16, 20, 16, 20),
                itemCount: items.length,
                separatorBuilder: (context, index) => const SizedBox(height: 4),
                itemBuilder: (context, index) {
                  final item = items[index];
                  final isActive =
                      item.module != null && item.module == _selectedModule;

                  return Material(
                    color: isActive ? _activeBlue : Colors.transparent,
                    borderRadius: BorderRadius.circular(12),
                    child: InkWell(
                      borderRadius: BorderRadius.circular(12),
                      onTap: item.module != null
                          ? () => _selectModule(item.module!)
                          : () => _showPendingModule(item.label),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 12,
                        ),
                        child: Row(
                          children: <Widget>[
                            SizedBox(
                              width: 20,
                              child: Center(
                                child: Text(
                                  item.glyph,
                                  textAlign: TextAlign.center,
                                  style: const TextStyle(
                                    fontSize: 15,
                                    height: 1,
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Text(
                                item.label,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  color: isActive
                                      ? Colors.white
                                      : const Color(0xFFDBEAFE),
                                  fontSize: 14,
                                  fontWeight: isActive
                                      ? FontWeight.w700
                                      : FontWeight.w500,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
            const Divider(height: 1, color: Color(0xFF1E3A8A)),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Material(
                color: Colors.transparent,
                borderRadius: BorderRadius.circular(16),
                child: InkWell(
                  borderRadius: BorderRadius.circular(16),
                  onTap: _loggingOut ? null : _showAccountMenu,
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 8,
                    ),
                    child: Row(
                      children: <Widget>[
                        CircleAvatar(
                          radius: 20,
                          backgroundColor: const Color(0xFF2563EB),
                          child: Text(
                            _initials(_userName),
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Text(
                                _userName,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 14,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                _roleLabel(_role),
                                style: const TextStyle(
                                  color: Color(0xFFBFDBFE),
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const Icon(
                          Icons.more_horiz_rounded,
                          color: Color(0xFFBFDBFE),
                          size: 20,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  _HomeModule? _homeModuleFor(AppModuleId moduleId) => switch (moduleId) {
    AppModuleId.dashboard => _HomeModule.dashboard,
    AppModuleId.incidents => _HomeModule.incidents,
    AppModuleId.tanodAlerts => _HomeModule.tanodAlerts,
    AppModuleId.tanodRoster => _HomeModule.tanodRoster,
    AppModuleId.announcements => _HomeModule.announcements,
    AppModuleId.emergencyHotlines => _HomeModule.hotlines,
    _ => null,
  };

  AppModuleId _moduleIdForHomeModule(_HomeModule module) => switch (module) {
    _HomeModule.dashboard => AppModuleId.dashboard,
    _HomeModule.incidents => AppModuleId.incidents,
    _HomeModule.tanodAlerts => AppModuleId.tanodAlerts,
    _HomeModule.tanodRoster => AppModuleId.tanodRoster,
    _HomeModule.announcements => AppModuleId.announcements,
    _HomeModule.hotlines => AppModuleId.emergencyHotlines,
  };
  List<_NavItem> _navigationItemsForRole() {
    final definitions = ModuleRegistry.forRole(_appRole);

    return definitions
        .map((definition) {
          final homeModule = _homeModuleFor(definition.id);

          final connected = definition.mobileImplemented && homeModule != null;

          return _NavItem(
            label: definition.labelFor(_appRole),
            icon: definition.icon,
            glyph: definition.websiteGlyphFor(_appRole),
            module: connected ? homeModule : null,
            pending: !connected,
          );
        })
        .toList(growable: false);
  }

  Widget _buildDashboard() {
    if (_dashboardLoading) {
      return _loadingPage('Loading dashboard...');
    }

    if (_dashboardError != null) {
      return _errorPage(message: _dashboardError!, onRetry: _loadDashboard);
    }

    final summary = _mapObject(_dashboard['summary']);
    final recent = _mapList(
      _dashboard['recent_incidents'] ?? _dashboard['recent_reports'],
    );
    final weatherFeed = _mapObject(_dashboard['weather_feed']);
    final metrics = _dashboardSummaryCards(summary);

    final isAdminOrOfficial =
        _role == 'admin' || _role == 'official' || _role == 'dao';
    final showRecentActivity = _role != 'resident';

    return RefreshIndicator(
      onRefresh: _loadDashboard,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 20, 16, 32),
        children: <Widget>[
          if (isAdminOrOfficial)
            _DashboardTitleBlock(
              title: _role == 'admin'
                  ? 'Admin Dashboard'
                  : 'Official Dashboard',
              subtitle: 'Dao, Capiz — Community Safety Overview',
            )
          else if (_role == 'tanod')
            const _DashboardHeroCard(
              eyebrow: 'TANOD DASHBOARD',
              title: 'Tanod Operations Overview',
              subtitle:
                  'Monitor your assigned incidents, task response status, and barangay field conditions in one place.',
            )
          else
            const _DashboardHeroCard(
              eyebrow: 'RESIDENT DASHBOARD',
              title: 'My Incident Report Overview',
              subtitle:
                  'Track the status of your submitted incident reports and monitor local weather or disaster advisories.',
            ),
          const SizedBox(height: 20),
          LayoutBuilder(
            builder: (context, constraints) {
              final twoColumns = constraints.maxWidth >= 340;
              final cardWidth = twoColumns
                  ? (constraints.maxWidth - 12) / 2
                  : constraints.maxWidth;

              return Wrap(
                spacing: 12,
                runSpacing: 12,
                children: metrics
                    .map(
                      (metric) => SizedBox(
                        width: cardWidth,
                        child: _SummaryCard(metric: metric),
                      ),
                    )
                    .toList(),
              );
            },
          ),
          const SizedBox(height: 22),
          _WeatherDisasterCard(feed: weatherFeed),
          if (showRecentActivity) ...<Widget>[
            const SizedBox(height: 22),
            _RecentActivityPanel(
              incidents: recent,
              showCategory: _role == 'tanod',
              onOpenIncidents: () => _selectModule(_HomeModule.incidents),
            ),
          ],
        ],
      ),
    );
  }

  List<_DashboardMetric> _dashboardSummaryCards(Map<String, dynamic> summary) {
    String value(String key) => summary[key]?.toString() ?? '0';

    switch (_role) {
      case 'admin':
        return <_DashboardMetric>[
          _DashboardMetric(
            label: 'Total Incidents',
            value: value('total_incidents'),
            icon: Icons.description_rounded,
            accent: const Color(0xFF2563EB),
            background: TabangNowTheme.of(context).surface,
            border: const Color(0xFF93C5FD),
          ),
          _DashboardMetric(
            label: 'Active Cases',
            value: value('active_cases'),
            icon: Icons.schedule_rounded,
            accent: const Color(0xFFD97706),
            background: TabangNowTheme.of(context).surface,
            border: const Color(0xFFFCD34D),
          ),
          _DashboardMetric(
            label: 'Critical',
            value: value('critical_incidents'),
            icon: Icons.warning_amber_rounded,
            accent: const Color(0xFFDC2626),
            background: TabangNowTheme.of(context).surface,
            border: const Color(0xFFFCA5A5),
          ),
          _DashboardMetric(
            label: 'Resolved',
            value: value('resolved_cases'),
            icon: Icons.check_circle_rounded,
            accent: const Color(0xFF059669),
            background: TabangNowTheme.of(context).surface,
            border: const Color(0xFF6EE7B7),
          ),
          _DashboardMetric(
            label: 'Tanod On Duty',
            value: value('tanod_on_duty'),
            icon: Icons.groups_rounded,
            accent: const Color(0xFF4338CA),
            background: TabangNowTheme.of(context).surface,
            border: const Color(0xFFC4B5FD),
          ),
        ];

      case 'official':
      case 'dao':
        final activeCases =
            summary['active_cases'] ??
            (_asInt(summary['pending_incidents']) +
                _asInt(summary['active_incidents']));
        final resolvedCases =
            summary['resolved_cases'] ?? summary['resolved_incidents'] ?? 0;

        return <_DashboardMetric>[
          _DashboardMetric(
            label: 'Total Incidents',
            value: value('total_incidents'),
            icon: Icons.description_rounded,
            accent: const Color(0xFF2563EB),
            background: TabangNowTheme.of(context).surface,
            border: const Color(0xFF93C5FD),
          ),
          _DashboardMetric(
            label: 'Active Cases',
            value: activeCases.toString(),
            icon: Icons.schedule_rounded,
            accent: const Color(0xFFD97706),
            background: TabangNowTheme.of(context).surface,
            border: const Color(0xFFFCD34D),
          ),
          _DashboardMetric(
            label: 'Critical',
            value: value('critical_incidents'),
            icon: Icons.warning_amber_rounded,
            accent: const Color(0xFFDC2626),
            background: TabangNowTheme.of(context).surface,
            border: const Color(0xFFFCA5A5),
          ),
          _DashboardMetric(
            label: 'Resolved',
            value: resolvedCases.toString(),
            icon: Icons.check_circle_rounded,
            accent: const Color(0xFF059669),
            background: TabangNowTheme.of(context).surface,
            border: const Color(0xFF6EE7B7),
          ),
          _DashboardMetric(
            label: 'Tanod On Duty',
            value: value('tanod_on_duty'),
            icon: Icons.groups_rounded,
            accent: const Color(0xFF4338CA),
            background: TabangNowTheme.of(context).surface,
            border: const Color(0xFFC4B5FD),
          ),
        ];

      case 'tanod':
        return <_DashboardMetric>[
          _DashboardMetric(
            label: 'Assigned Incidents',
            value: value('assigned_incidents'),
            subtitle: 'Incidents currently assigned to you',
            icon: Icons.description_rounded,
            accent: const Color(0xFF1D4ED8),
            background: const Color(0xFFEFF6FF),
            border: const Color(0xFFBFDBFE),
          ),
          _DashboardMetric(
            label: 'Pending Tasks',
            value: value('open_tasks'),
            subtitle: 'Tasks waiting for your response',
            icon: Icons.schedule_rounded,
            accent: const Color(0xFFA16207),
            background: const Color(0xFFFEFCE8),
            border: const Color(0xFFFEF08A),
          ),
          _DashboardMetric(
            label: 'Accepted Tasks',
            value: value('accepted_tasks'),
            subtitle: 'Tasks you accepted for action',
            icon: Icons.check_circle_rounded,
            accent: const Color(0xFF047857),
            background: const Color(0xFFECFDF5),
            border: const Color(0xFFA7F3D0),
          ),
          _DashboardMetric(
            label: 'Declined Tasks',
            value: value('declined_tasks'),
            subtitle: 'Tasks you declined or rejected',
            icon: Icons.cancel_rounded,
            accent: const Color(0xFFB91C1C),
            background: const Color(0xFFFEF2F2),
            border: const Color(0xFFFECACA),
          ),
        ];

      case 'resident':
      default:
        return <_DashboardMetric>[
          _DashboardMetric(
            label: 'My Reports',
            value: value('my_reports'),
            subtitle: 'Incident reports you submitted',
            icon: Icons.description_rounded,
            accent: const Color(0xFF1D4ED8),
            background: const Color(0xFFEFF6FF),
            border: const Color(0xFFBFDBFE),
          ),
          _DashboardMetric(
            label: 'Pending Reports',
            value: value('pending_reports'),
            subtitle: 'Reports waiting for review',
            icon: Icons.schedule_rounded,
            accent: const Color(0xFFA16207),
            background: const Color(0xFFFEFCE8),
            border: const Color(0xFFFEF08A),
          ),
          _DashboardMetric(
            label: 'Active Reports',
            value: value('active_reports'),
            subtitle: 'Reports currently being handled',
            icon: Icons.pending_actions_rounded,
            accent: const Color(0xFFC2410C),
            background: const Color(0xFFFFF7ED),
            border: const Color(0xFFFED7AA),
          ),
          _DashboardMetric(
            label: 'Resolved Reports',
            value: value('resolved_reports'),
            subtitle: 'Reports already resolved or closed',
            icon: Icons.check_circle_rounded,
            accent: const Color(0xFF047857),
            background: const Color(0xFFECFDF5),
            border: const Color(0xFFA7F3D0),
          ),
        ];
    }
  }

  Widget _buildAnnouncements() {
    if (_announcementsLoading) {
      return _loadingPage('Loading announcements...');
    }

    if (_announcementsError != null) {
      return _errorPage(
        message: _announcementsError!,
        onRetry: _loadAnnouncements,
      );
    }

    return RefreshIndicator(
      onRefresh: _loadAnnouncements,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(18, 22, 18, 28),
        children: <Widget>[
          const _SectionTitle(
            title: 'Announcements',
            subtitle: 'Barangay notices and community advisories',
          ),
          const SizedBox(height: 16),
          if (_announcements.isEmpty)
            const _EmptyState(
              icon: Icons.campaign_outlined,
              title: 'No announcements',
              message: 'There are no announcements available right now.',
            )
          else
            ..._announcements.map(
              (announcement) => _AnnouncementCard(announcement: announcement),
            ),
        ],
      ),
    );
  }

  Widget _buildHotlines() {
    if (_hotlinesLoading) {
      return _loadingPage('Loading emergency hotlines...');
    }

    if (_hotlinesError != null) {
      return _errorPage(message: _hotlinesError!, onRetry: _loadHotlines);
    }

    return RefreshIndicator(
      onRefresh: _loadHotlines,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(18, 22, 18, 28),
        children: <Widget>[
          const _SectionTitle(
            title: 'Emergency Hotlines',
            subtitle: 'Official emergency and response contacts',
          ),
          const SizedBox(height: 16),
          if (_hotlines.isEmpty)
            const _EmptyState(
              icon: Icons.phone_outlined,
              title: 'No hotlines available',
              message: 'No active emergency hotline records were found.',
            )
          else
            ..._hotlines.map((hotline) => _HotlineCard(hotline: hotline)),
        ],
      ),
    );
  }

  Widget _loadingPage(String message) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const CircularProgressIndicator(),
            const SizedBox(height: 14),
            Text(
              message,
              style: TextStyle(color: TabangNowTheme.of(context).textMuted),
            ),
          ],
        ),
      ),
    );
  }

  Widget _errorPage({
    required String message,
    required Future<void> Function() onRetry,
  }) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(24),
      children: <Widget>[
        const SizedBox(height: 100),
        const Icon(
          Icons.error_outline_rounded,
          size: 50,
          color: Color(0xFFDC2626),
        ),
        const SizedBox(height: 14),
        Text(
          message,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: TabangNowTheme.of(context).textSoft,
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(height: 18),
        Center(
          child: FilledButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Try Again'),
          ),
        ),
      ],
    );
  }

  static Map<String, dynamic> _mapObject(dynamic raw) {
    if (raw is Map) {
      return Map<String, dynamic>.from(raw);
    }

    return <String, dynamic>{};
  }

  static int _asInt(dynamic value) {
    if (value is int) {
      return value;
    }

    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  static List<Map<String, dynamic>> _mapList(dynamic raw) {
    if (raw is! List) {
      return <Map<String, dynamic>>[];
    }

    return raw.whereType<Map>().map(Map<String, dynamic>.from).toList();
  }

  static String _roleLabel(String role) {
    switch (role) {
      case 'admin':
        return 'Admin';
      case 'official':
      case 'dao':
        return 'Official';
      case 'tanod':
        return 'Tanod';
      case 'resident':
        return 'Resident';
      default:
        return role.isEmpty ? 'User' : role;
    }
  }

  static String _initials(String name) {
    final parts = name
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .toList();

    if (parts.isEmpty) {
      return 'U';
    }

    if (parts.length == 1) {
      return parts.first.substring(0, 1).toUpperCase();
    }

    return '${parts.first.substring(0, 1)}${parts.last.substring(0, 1)}'
        .toUpperCase();
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          title,
          style: TextStyle(
            color: TabangNowTheme.of(context).textMain,
            fontSize: 22,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          subtitle,
          style: TextStyle(
            color: TabangNowTheme.of(context).textMuted,
            fontSize: 13,
          ),
        ),
      ],
    );
  }
}

class _DashboardMetric {
  const _DashboardMetric({
    required this.label,
    required this.value,
    required this.icon,
    required this.accent,
    required this.background,
    required this.border,
    this.subtitle,
  });

  final String label;
  final String value;
  final String? subtitle;
  final IconData icon;
  final Color accent;
  final Color background;
  final Color border;
}

class _DashboardTitleBlock extends StatelessWidget {
  const _DashboardTitleBlock({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          title,
          style: TextStyle(
            color: TabangNowTheme.of(context).textMain,
            fontSize: 29,
            fontWeight: FontWeight.w800,
            letterSpacing: -0.5,
          ),
        ),
        const SizedBox(height: 5),
        Text(
          subtitle,
          style: TextStyle(
            color: TabangNowTheme.of(context).textSoft,
            fontSize: 15,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }
}

class _DashboardHeroCard extends StatelessWidget {
  const _DashboardHeroCard({
    required this.eyebrow,
    required this.title,
    required this.subtitle,
  });

  final String eyebrow;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: TabangNowTheme.of(context).surface,
        border: Border.all(color: TabangNowTheme.of(context).border),
        borderRadius: BorderRadius.circular(18),
        boxShadow: const <BoxShadow>[
          BoxShadow(
            color: Color(0x08000000),
            blurRadius: 12,
            offset: Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            eyebrow,
            style: const TextStyle(
              color: Color(0xFF1D4ED8),
              fontSize: 12,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.9,
            ),
          ),
          const SizedBox(height: 7),
          Text(
            title,
            style: TextStyle(
              color: TabangNowTheme.of(context).textMain,
              fontSize: 23,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.2,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            subtitle,
            style: TextStyle(
              color: TabangNowTheme.of(context).textSoft,
              height: 1.5,
              fontSize: 13.5,
            ),
          ),
        ],
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.metric});

  final _DashboardMetric metric;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 156),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: metric.background,
        border: Border.all(color: metric.border),
        borderRadius: BorderRadius.circular(18),
        boxShadow: const <BoxShadow>[
          BoxShadow(
            color: Color(0x08000000),
            blurRadius: 10,
            offset: Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: metric.accent.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(metric.icon, color: metric.accent, size: 23),
          ),
          const SizedBox(height: 12),
          Text(
            metric.value,
            style: TextStyle(
              color: TabangNowTheme.of(context).textMain,
              fontSize: 30,
              fontWeight: FontWeight.w900,
              height: 1,
            ),
          ),
          const SizedBox(height: 7),
          Text(
            metric.label,
            style: TextStyle(
              color: metric.accent,
              fontSize: 13,
              fontWeight: FontWeight.w800,
            ),
          ),
          if ((metric.subtitle ?? '').isNotEmpty) ...<Widget>[
            const SizedBox(height: 6),
            Text(
              metric.subtitle!,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: metric.accent.withValues(alpha: 0.85),
                fontSize: 10.5,
                height: 1.3,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _WeatherDisasterCard extends StatelessWidget {
  const _WeatherDisasterCard({required this.feed});

  final Map<String, dynamic> feed;

  static Map<String, dynamic> _map(dynamic raw) {
    if (raw is Map) {
      return Map<String, dynamic>.from(raw);
    }
    return <String, dynamic>{};
  }

  static List<Map<String, dynamic>> _list(dynamic raw) {
    if (raw is! List) {
      return <Map<String, dynamic>>[];
    }
    return raw.whereType<Map>().map(Map<String, dynamic>.from).toList();
  }

  static String _number(dynamic raw, {String suffix = '', int decimals = 0}) {
    final value = double.tryParse(raw?.toString() ?? '');
    if (value == null) {
      return '—';
    }
    return '${value.toStringAsFixed(decimals)}$suffix';
  }

  @override
  Widget build(BuildContext context) {
    final weather = _map(feed['weather']);
    final advisories = _list(feed['advisories']);

    final risk = (weather['risk_level']?.toString() ?? 'normal')
        .trim()
        .toLowerCase();

    final riskLabel = switch (risk) {
      'warning' => 'WARNING',
      'watch' => 'WATCH',
      'notice' => 'NOTICE',
      _ => 'NORMAL',
    };

    final riskColor = switch (risk) {
      'warning' => const Color(0xFFB91C1C),
      'watch' => const Color(0xFFC2410C),
      'notice' => const Color(0xFFA16207),
      _ => const Color(0xFF047857),
    };

    final riskBackground = switch (risk) {
      'warning' => const Color(0xFFFEF2F2),
      'watch' => const Color(0xFFFFF7ED),
      'notice' => const Color(0xFFFEFCE8),
      _ => const Color(0xFFECFDF5),
    };

    final condition = weather['condition']?.toString().trim();
    final source = weather['source']?.toString().trim();
    final advisory = weather['advisory']?.toString().trim();
    final statusMessage = weather['status_message']?.toString().trim();
    final updatedAt = weather['updated_at']?.toString().trim();

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: TabangNowTheme.of(context).surface,
        border: Border.all(color: TabangNowTheme.of(context).border),
        borderRadius: BorderRadius.circular(18),
        boxShadow: const <BoxShadow>[
          BoxShadow(
            color: Color(0x08000000),
            blurRadius: 12,
            offset: Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(15),
                ),
                alignment: Alignment.center,
                child: Text(
                  weather['icon']?.toString() ?? '🌤️',
                  style: const TextStyle(fontSize: 24),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      'Weather & Disaster Feed',
                      style: TextStyle(
                        color: TabangNowTheme.of(context).textMain,
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${weather['location']?.toString() ?? 'Dao, Capiz'} only',
                      style: TextStyle(
                        color: TabangNowTheme.of(context).textMuted,
                        fontSize: 12.5,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 6,
                ),
                decoration: BoxDecoration(
                  color: riskBackground,
                  border: Border.all(color: riskColor.withValues(alpha: 0.35)),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  riskLabel,
                  style: TextStyle(
                    color: riskColor,
                    fontSize: 10,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          LayoutBuilder(
            builder: (context, constraints) {
              final width = (constraints.maxWidth - 10) / 2;
              final tiles = <Widget>[
                _WeatherMetricTile(
                  label: 'Temperature',
                  value: _number(
                    weather['temperature'],
                    suffix: '°C',
                    decimals: 1,
                  ),
                  note:
                      'Feels like ${_number(weather['feels_like'], suffix: '°C', decimals: 1)}',
                ),
                _WeatherMetricTile(
                  label: 'Condition',
                  value: condition?.isNotEmpty == true
                      ? condition!
                      : 'Unavailable',
                  note: source?.isNotEmpty == true
                      ? 'Source: $source'
                      : 'Weather feed',
                  compactValue: true,
                ),
                _WeatherMetricTile(
                  label: 'Humidity',
                  value: _number(weather['humidity'], suffix: '%'),
                  note: 'Relative humidity',
                ),
                _WeatherMetricTile(
                  label: 'Wind Speed',
                  value: _number(
                    weather['wind_speed'],
                    suffix: ' km/h',
                    decimals: 1,
                  ),
                  note: '10-meter wind',
                ),
                _WeatherMetricTile(
                  label: 'Rain Chance',
                  value: _number(weather['rain_chance'], suffix: '%'),
                  note: 'Current forecast hour',
                ),
              ];

              return Wrap(
                spacing: 10,
                runSpacing: 10,
                children: tiles
                    .map((tile) => SizedBox(width: width, child: tile))
                    .toList(),
              );
            },
          ),
          const SizedBox(height: 16),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: riskBackground,
              border: Border.all(color: riskColor.withValues(alpha: 0.30)),
              borderRadius: BorderRadius.circular(15),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'Automatic Weather Advisory',
                  style: TextStyle(
                    color: riskColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  advisory?.isNotEmpty == true
                      ? advisory!
                      : 'Weather advisory is currently unavailable.',
                  style: TextStyle(
                    color: riskColor,
                    fontSize: 12.5,
                    height: 1.45,
                  ),
                ),
                if ((statusMessage ?? '').isNotEmpty ||
                    (updatedAt ?? '').isNotEmpty) ...<Widget>[
                  const SizedBox(height: 7),
                  Text(
                    [
                      if ((statusMessage ?? '').isNotEmpty) statusMessage!,
                      if ((updatedAt ?? '').isNotEmpty) 'Updated $updatedAt.',
                    ].join(' '),
                    style: TextStyle(
                      color: riskColor.withValues(alpha: 0.82),
                      fontSize: 10.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 18),
          const Divider(height: 1),
          const SizedBox(height: 16),
          Text(
            'LOCAL DISASTER ADVISORIES FROM ANNOUNCEMENTS',
            style: TextStyle(
              color: TabangNowTheme.of(context).textSoft,
              fontSize: 11,
              fontWeight: FontWeight.w900,
              letterSpacing: 0.5,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            'Weather, disaster, calamity, emergency, flood, typhoon, or evacuation announcements appear here.',
            style: TextStyle(
              color: TabangNowTheme.of(context).textMuted,
              fontSize: 11.5,
              height: 1.4,
            ),
          ),
          const SizedBox(height: 12),
          if (advisories.isEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 22),
              decoration: BoxDecoration(
                color: TabangNowTheme.of(context).surfaceMuted,
                border: Border.all(
                  color: TabangNowTheme.of(context).borderStrong,
                  style: BorderStyle.solid,
                ),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Column(
                children: <Widget>[
                  Text(
                    'No active weather or disaster announcement posted.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: TabangNowTheme.of(context).textSoft,
                      fontWeight: FontWeight.w800,
                      fontSize: 12,
                    ),
                  ),
                  SizedBox(height: 5),
                  Text(
                    'Admin or official can post a disaster-related announcement in the Announcements module.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: TabangNowTheme.of(context).textMuted,
                      fontSize: 10.5,
                      height: 1.35,
                    ),
                  ),
                ],
              ),
            )
          else
            ...advisories.map((item) => _DisasterAdvisoryTile(advisory: item)),
        ],
      ),
    );
  }
}

class _WeatherMetricTile extends StatelessWidget {
  const _WeatherMetricTile({
    required this.label,
    required this.value,
    required this.note,
    this.compactValue = false,
  });

  final String label;
  final String value;
  final String note;
  final bool compactValue;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 112),
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: TabangNowTheme.of(context).surfaceMuted,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            label.toUpperCase(),
            style: TextStyle(
              color: TabangNowTheme.of(context).textFaint,
              fontSize: 9.5,
              fontWeight: FontWeight.w900,
              letterSpacing: 0.4,
            ),
          ),
          const SizedBox(height: 9),
          Text(
            value,
            maxLines: compactValue ? 2 : 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: TabangNowTheme.of(context).textMain,
              fontSize: compactValue ? 13.5 : 20,
              fontWeight: FontWeight.w900,
              height: compactValue ? 1.2 : 1,
            ),
          ),
          const SizedBox(height: 7),
          Text(
            note,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: TabangNowTheme.of(context).textMuted,
              fontSize: 9.5,
              height: 1.3,
            ),
          ),
        ],
      ),
    );
  }
}

class _DisasterAdvisoryTile extends StatelessWidget {
  const _DisasterAdvisoryTile({required this.advisory});

  final Map<String, dynamic> advisory;

  @override
  Widget build(BuildContext context) {
    final severity = (advisory['severity']?.toString() ?? 'info')
        .trim()
        .toLowerCase();
    final accent = switch (severity) {
      'danger' => const Color(0xFFB91C1C),
      'warning' => const Color(0xFFC2410C),
      'watch' => const Color(0xFFA16207),
      _ => const Color(0xFF1D4ED8),
    };
    final background = switch (severity) {
      'danger' => const Color(0xFFFEF2F2),
      'warning' => const Color(0xFFFFF7ED),
      'watch' => const Color(0xFFFEFCE8),
      _ => const Color(0xFFEFF6FF),
    };

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 9),
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: background,
        border: Border.all(color: accent.withValues(alpha: 0.25)),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            advisory['title']?.toString() ?? 'Weather / Disaster Advisory',
            style: TextStyle(
              color: accent,
              fontSize: 12.5,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            advisory['message']?.toString() ??
                'Please check the Announcements module for details.',
            style: TextStyle(color: accent, fontSize: 11.5, height: 1.4),
          ),
        ],
      ),
    );
  }
}

class _RecentActivityPanel extends StatelessWidget {
  const _RecentActivityPanel({
    required this.incidents,
    required this.showCategory,
    required this.onOpenIncidents,
  });

  final List<Map<String, dynamic>> incidents;
  final bool showCategory;
  final VoidCallback onOpenIncidents;

  static DateTime? _date(dynamic raw) {
    if (raw == null) {
      return null;
    }
    return DateTime.tryParse(raw.toString())?.toLocal();
  }

  static String _relative(dynamic raw) {
    final date = _date(raw);
    if (date == null) {
      return 'Unknown time';
    }

    final difference = DateTime.now().difference(date);
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
      return '$hours hour${hours == 1 ? '' : 's'} ago';
    }
    if (difference.inDays < 30) {
      final days = difference.inDays;
      return '$days day${days == 1 ? '' : 's'} ago';
    }
    final months = (difference.inDays / 30).floor();
    return '$months month${months == 1 ? '' : 's'} ago';
  }

  static Color _priorityColor(String priority) {
    switch (priority) {
      case 'critical':
        return const Color(0xFFDC2626);
      case 'high':
        return const Color(0xFFEA580C);
      case 'moderate':
      case 'medium':
        return const Color(0xFFD97706);
      default:
        return const Color(0xFF16A34A);
    }
  }

  static Color _statusColor(String status) {
    final normalized = status.toLowerCase().replaceAll(' ', '_');
    switch (normalized) {
      case 'escalated':
        return const Color(0xFFDC2626);
      case 'dispatched':
      case 'responding':
        return const Color(0xFFEA580C);
      case 'resolved':
      case 'completed':
      case 'closed':
        return const Color(0xFF16A34A);
      default:
        return const Color(0xFF2563EB);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: TabangNowTheme.of(context).surface,
        border: Border.all(color: const Color(0xFFFDBA74)),
        borderRadius: BorderRadius.circular(18),
        boxShadow: const <BoxShadow>[
          BoxShadow(
            color: Color(0x08000000),
            blurRadius: 12,
            offset: Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  'Recent Incident Activity',
                  style: TextStyle(
                    color: TabangNowTheme.of(context).textMain,
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              TextButton(
                onPressed: onOpenIncidents,
                child: const Text('Open Incidents'),
              ),
            ],
          ),
          Text(
            'Latest records',
            style: TextStyle(
              color: TabangNowTheme.of(context).textFaint,
              fontSize: 11.5,
            ),
          ),
          const SizedBox(height: 12),
          if (incidents.isEmpty)
            Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: Text(
                'No recent incidents found.',
                style: TextStyle(color: TabangNowTheme.of(context).textMuted),
              ),
            )
          else
            ...incidents.take(8).map((incident) {
              final title =
                  incident['title']?.toString() ??
                  incident['incident_title']?.toString() ??
                  'Untitled Incident';
              final category =
                  incident['category']?.toString() ?? 'Uncategorized';
              final status = incident['status']?.toString() ?? 'Pending';
              final priority = (incident['priority']?.toString() ?? 'low')
                  .trim()
                  .toLowerCase();
              final reporter =
                  incident['reporter_name']?.toString() ?? 'Unknown';
              final assigned = incident['assigned_tanod_name']
                  ?.toString()
                  .trim();
              final reportedRaw =
                  incident['reported_at'] ??
                  incident['incident_datetime'] ??
                  incident['created_at'];

              final priorityColor = _priorityColor(priority);
              final statusColor = _statusColor(status);

              return InkWell(
                onTap: onOpenIncidents,
                borderRadius: BorderRadius.circular(12),
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 13),
                  decoration: BoxDecoration(
                    border: Border(
                      bottom: BorderSide(
                        color: TabangNowTheme.of(context).surfaceSoft,
                      ),
                    ),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Container(
                        width: 10,
                        height: 10,
                        margin: const EdgeInsets.only(top: 6),
                        decoration: BoxDecoration(
                          color: priorityColor,
                          shape: BoxShape.circle,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Expanded(
                                  child: Text(
                                    title,
                                    style: TextStyle(
                                      color: TabangNowTheme.of(
                                        context,
                                      ).textMain,
                                      fontSize: 13.5,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 8,
                                    vertical: 4,
                                  ),
                                  decoration: BoxDecoration(
                                    color: priorityColor.withValues(
                                      alpha: 0.10,
                                    ),
                                    borderRadius: BorderRadius.circular(999),
                                  ),
                                  child: Text(
                                    priority.isEmpty
                                        ? 'Low'
                                        : '${priority[0].toUpperCase()}${priority.substring(1)}',
                                    style: TextStyle(
                                      color: priorityColor,
                                      fontSize: 9.5,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 5),
                            Wrap(
                              spacing: 5,
                              runSpacing: 3,
                              children: <Widget>[
                                if (showCategory)
                                  Text(
                                    category,
                                    style: TextStyle(
                                      color: TabangNowTheme.of(
                                        context,
                                      ).textMuted,
                                      fontSize: 10.5,
                                    ),
                                  ),
                                Text(
                                  status,
                                  style: TextStyle(
                                    color: statusColor,
                                    fontSize: 10.5,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                Text(
                                  '• ${_relative(reportedRaw)}',
                                  style: TextStyle(
                                    color: TabangNowTheme.of(context).textMuted,
                                    fontSize: 10.5,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 7),
                            Text(
                              [
                                'Reporter: $reporter',
                                if (assigned != null && assigned.isNotEmpty)
                                  'Assigned: $assigned',
                              ].join(' • '),
                              style: TextStyle(
                                color: TabangNowTheme.of(context).textFaint,
                                fontSize: 9.5,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              );
            }),
        ],
      ),
    );
  }
}

class _AnnouncementCard extends StatelessWidget {
  const _AnnouncementCard({required this.announcement});

  final Map<String, dynamic> announcement;

  @override
  Widget build(BuildContext context) {
    final title = announcement['title']?.toString() ?? 'Announcement';
    final content = announcement['content']?.toString() ?? '';
    final category =
        announcement['category_label']?.toString() ??
        announcement['category']?.toString() ??
        '';
    final priority =
        announcement['priority_label']?.toString() ??
        announcement['priority']?.toString() ??
        '';

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: TabangNowTheme.of(context).surface,
        border: Border.all(color: TabangNowTheme.of(context).border),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Padding(
        padding: const EdgeInsets.all(17),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: const Color(0xFFDBEAFE),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.campaign_rounded,
                    color: Color(0xFF1E40AF),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    title,
                    style: TextStyle(
                      color: TabangNowTheme.of(context).textMain,
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
            if (category.isNotEmpty || priority.isNotEmpty) ...<Widget>[
              const SizedBox(height: 12),
              Wrap(
                spacing: 7,
                runSpacing: 7,
                children: <Widget>[
                  if (category.isNotEmpty) _SmallBadge(text: category),
                  if (priority.isNotEmpty) _SmallBadge(text: priority),
                ],
              ),
            ],
            if (content.isNotEmpty) ...<Widget>[
              const SizedBox(height: 12),
              Text(
                content,
                style: TextStyle(
                  color: TabangNowTheme.of(context).textSoft,
                  height: 1.45,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _HotlineCard extends StatelessWidget {
  const _HotlineCard({required this.hotline});

  final Map<String, dynamic> hotline;

  @override
  Widget build(BuildContext context) {
    final agency = hotline['agency_name']?.toString() ?? 'Emergency Hotline';
    final number = hotline['hotline_number']?.toString() ?? 'No number';

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: TabangNowTheme.of(context).surface,
        border: Border.all(color: TabangNowTheme.of(context).border),
        borderRadius: BorderRadius.circular(16),
      ),
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 10,
        ),
        leading: Container(
          width: 44,
          height: 44,
          decoration: BoxDecoration(
            color: const Color(0xFFFEE2E2),
            borderRadius: BorderRadius.circular(13),
          ),
          child: const Icon(
            Icons.phone_in_talk_rounded,
            color: Color(0xFFB91C1C),
          ),
        ),
        title: Text(
          agency,
          style: TextStyle(
            color: TabangNowTheme.of(context).textMain,
            fontWeight: FontWeight.w800,
          ),
        ),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 5),
          child: Text(
            number,
            style: const TextStyle(
              color: Color(0xFF1D4ED8),
              fontSize: 15,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ),
    );
  }
}

class _SmallBadge extends StatelessWidget {
  const _SmallBadge({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: const Color(0xFFEFF6FF),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: const Color(0xFFBFDBFE)),
      ),
      child: Text(
        text,
        style: const TextStyle(
          color: Color(0xFF1D4ED8),
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(28),
      decoration: BoxDecoration(
        color: TabangNowTheme.of(context).surface,
        border: Border.all(color: TabangNowTheme.of(context).border),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: <Widget>[
          Icon(icon, size: 42, color: TabangNowTheme.of(context).textMuted),
          const SizedBox(height: 12),
          Text(
            title,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: TabangNowTheme.of(context).textMain,
              fontSize: 16,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            message,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: TabangNowTheme.of(context).textMuted,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }
}
