import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../core/tabangnow_theme.dart';
import '../services/auth_service.dart';
import '../services/barangay_map_service.dart';
import '../services/incident_service.dart';
import 'incident_detail_screen.dart';

enum _MapMode { pins, heat }

class BarangayMapScreen extends StatefulWidget {
  const BarangayMapScreen({
    super.key,
    required this.authService,
    required this.user,
  });

  final AuthService authService;
  final Map<String, dynamic> user;

  @override
  State<BarangayMapScreen> createState() => _BarangayMapScreenState();
}

class _BarangayMapScreenState extends State<BarangayMapScreen> {
  late final BarangayMapService _service;

  bool _loading = true;
  String? _error;

  List<Map<String, dynamic>> _incidents = <Map<String, dynamic>>[];

  List<Map<String, dynamic>> _legend = <Map<String, dynamic>>[];

  Map<String, dynamic> _mapCenter = <String, dynamic>{
    'latitude': 11.3945,
    'longitude': 122.6858,
    'zoom': 12,
  };

  int _recordCount = 0;
  _MapMode _mode = _MapMode.pins;

  @override
  void initState() {
    super.initState();

    _service = BarangayMapService(authService: widget.authService);

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

      setState(() {
        _incidents = _mapList(response['data']);

        _legend = _mapList(response['legend']);

        _mapCenter = _map(response['map_center']);

        _recordCount = _int(response['record_count']);

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

  LatLng get _initialCenter {
    if (_incidents.isEmpty) {
      return LatLng(
        _double(_mapCenter['latitude'], fallback: 11.3945),
        _double(_mapCenter['longitude'], fallback: 122.6858),
      );
    }

    var lat = 0.0;
    var lng = 0.0;
    var count = 0;

    for (final incident in _incidents) {
      final latitude = _doubleOrNull(incident['latitude']);

      final longitude = _doubleOrNull(incident['longitude']);

      if (latitude == null || longitude == null) {
        continue;
      }

      lat += latitude;
      lng += longitude;
      count++;
    }

    if (count == 0) {
      return const LatLng(11.3945, 122.6858);
    }

    return LatLng(lat / count, lng / count);
  }

  List<Marker> _pinMarkers() {
    return _incidents
        .map((incident) {
          final point = _pointFor(incident);

          if (point == null) {
            return null;
          }

          final color = _hexColor(_text(incident['pin_color'], '#eab308'));

          return Marker(
            point: point,
            width: 44,
            height: 44,
            child: GestureDetector(
              onTap: () => _showIncident(incident),
              child: Center(
                child: Container(
                  width: 22,
                  height: 22,
                  decoration: BoxDecoration(
                    color: color,
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white, width: 3),
                    boxShadow: const <BoxShadow>[
                      BoxShadow(
                        color: Color(0x590F172A),
                        blurRadius: 12,
                        offset: Offset(0, 5),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          );
        })
        .whereType<Marker>()
        .toList(growable: false);
  }

  List<Marker> _heatMarkers() {
    return _incidents
        .map((incident) {
          final point = _pointFor(incident);

          if (point == null) {
            return null;
          }

          final color = _hexColor(_text(incident['pin_color'], '#eab308'));

          final intensity = _double(
            incident['heat_intensity'],
            fallback: 0.55,
          ).clamp(0.1, 1.0).toDouble();

          final size = 70.0 + (intensity * 58.0);

          return Marker(
            point: point,
            width: size,
            height: size,
            child: GestureDetector(
              onTap: () => _showIncident(incident),
              child: DecoratedBox(
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: RadialGradient(
                    colors: <Color>[
                      color.withValues(alpha: 0.58 * intensity),
                      color.withValues(alpha: 0.30 * intensity),
                      color.withValues(alpha: 0),
                    ],
                    stops: const <double>[0, 0.45, 1],
                  ),
                ),
              ),
            ),
          );
        })
        .whereType<Marker>()
        .toList(growable: false);
  }

  Future<void> _showIncident(Map<String, dynamic> incident) async {
    final palette = TabangNowTheme.of(context);

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: palette.surface,
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(18, 18, 18, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  _text(incident['code'], 'Incident'),
                  style: TextStyle(
                    color: palette.textMuted,
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  _text(incident['title'], 'Untitled Incident'),
                  style: TextStyle(
                    color: palette.textMain,
                    fontSize: 19,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 14),
                _MapInfoLine(
                  label: 'Category',
                  value: _text(incident['category'], 'Not specified'),
                ),
                _MapInfoLine(
                  label: 'Barangay',
                  value: _text(incident['barangay'], 'Dao, Capiz'),
                ),
                _MapInfoLine(
                  label: 'Location',
                  value: _text(incident['location'], 'No location name'),
                ),
                _MapInfoLine(
                  label: 'Status',
                  value: _text(incident['status'], 'Pending'),
                ),
                _MapInfoLine(
                  label: 'Severity',
                  value: _text(incident['severity_label'], 'Moderate'),
                ),
                _MapInfoLine(
                  label: 'Reported',
                  value: _text(incident['reported_at'], '—'),
                ),
                const SizedBox(height: 14),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    onPressed: () {
                      final id = _int(incident['id']);

                      Navigator.of(sheetContext).pop();

                      if (id <= 0) {
                        return;
                      }

                      Navigator.of(context).push<void>(
                        MaterialPageRoute<void>(
                          builder: (_) => IncidentDetailScreen(
                            incidentService: IncidentService(
                              authService: widget.authService,
                            ),
                            incidentId: id,
                            user: widget.user,
                          ),
                        ),
                      );
                    },
                    icon: const Icon(Icons.visibility_outlined),
                    label: const Text('View Incident'),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
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
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      'Barangay Map',
                      style: TextStyle(
                        color: palette.textMain,
                        fontSize: 26,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      'Incident location pins and heatmap based on reported incidents.',
                      style: TextStyle(color: palette.textMuted),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 10,
                ),
                decoration: BoxDecoration(
                  color: palette.surface,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: palette.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      'MAPPED INCIDENTS',
                      style: TextStyle(
                        color: palette.textMuted,
                        fontSize: 9,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '$_recordCount',
                      style: TextStyle(
                        color: palette.accentText,
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: palette.surface,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: palette.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(
                            'Incident Map View',
                            style: TextStyle(
                              color: palette.textMain,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            'Move, zoom, and tap pins to inspect incident records.',
                            style: TextStyle(
                              color: palette.textMuted,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 10),
                    SegmentedButton<_MapMode>(
                      showSelectedIcon: false,
                      segments: const <ButtonSegment<_MapMode>>[
                        ButtonSegment<_MapMode>(
                          value: _MapMode.pins,
                          label: Text('Pins'),
                          icon: Icon(Icons.location_pin),
                        ),
                        ButtonSegment<_MapMode>(
                          value: _MapMode.heat,
                          label: Text('Heatmap'),
                          icon: Icon(Icons.blur_on_rounded),
                        ),
                      ],
                      selected: <_MapMode>{_mode},
                      onSelectionChanged: (selection) {
                        if (selection.isEmpty) {
                          return;
                        }

                        setState(() {
                          _mode = selection.first;
                        });
                      },
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                _Legend(legend: _legend),
                const SizedBox(height: 14),
                if (_loading)
                  const SizedBox(
                    height: 430,
                    child: Center(child: CircularProgressIndicator()),
                  )
                else if (_error != null)
                  SizedBox(
                    height: 430,
                    child: Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: <Widget>[
                            Text(_error!, textAlign: TextAlign.center),
                            const SizedBox(height: 12),
                            FilledButton(
                              onPressed: _load,
                              child: const Text('Try Again'),
                            ),
                          ],
                        ),
                      ),
                    ),
                  )
                else
                  ClipRRect(
                    borderRadius: BorderRadius.circular(16),
                    child: SizedBox(
                      height: 520,
                      child: FlutterMap(
                        options: MapOptions(
                          initialCenter: _initialCenter,
                          initialZoom: _double(
                            _mapCenter['zoom'],
                            fallback: 12,
                          ),
                        ),
                        children: <Widget>[
                          TileLayer(
                            urlTemplate:
                                'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                            userAgentPackageName:
                                'com.example.tabangnow_flutter',
                          ),
                          if (_mode == _MapMode.heat)
                            MarkerLayer(markers: _heatMarkers()),
                          if (_mode == _MapMode.pins)
                            MarkerLayer(markers: _pinMarkers()),
                          const RichAttributionWidget(
                            attributions: <SourceAttribution>[
                              TextSourceAttribution(
                                'OpenStreetMap contributors',
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                if (!_loading &&
                    _error == null &&
                    _incidents.isEmpty) ...<Widget>[
                  const SizedBox(height: 12),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: palette.surfaceMuted,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Text(
                      'No mapped incidents yet.',
                      textAlign: TextAlign.center,
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

class _Legend extends StatelessWidget {
  const _Legend({required this.legend});

  final List<Map<String, dynamic>> legend;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    final effective = legend.isNotEmpty
        ? legend
        : const <Map<String, dynamic>>[
            <String, dynamic>{'label': 'Low', 'color': '#22c55e'},
            <String, dynamic>{'label': 'Moderate', 'color': '#eab308'},
            <String, dynamic>{'label': 'High', 'color': '#f97316'},
            <String, dynamic>{'label': 'Critical', 'color': '#ef4444'},
          ];

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: palette.surfaceMuted,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: palette.border),
      ),
      child: Wrap(
        spacing: 14,
        runSpacing: 8,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: <Widget>[
          Text(
            'MAP LEGEND',
            style: TextStyle(
              color: palette.textMuted,
              fontSize: 10,
              fontWeight: FontWeight.w900,
            ),
          ),
          ...effective.map(
            (item) => Row(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Container(
                  width: 12,
                  height: 12,
                  decoration: BoxDecoration(
                    color: _hexColor(_text(item['color'], '#eab308')),
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 6),
                Text(
                  _text(item['label'], 'Severity'),
                  style: TextStyle(
                    color: palette.textSoft,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
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

class _MapInfoLine extends StatelessWidget {
  const _MapInfoLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          SizedBox(
            width: 82,
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
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

LatLng? _pointFor(Map<String, dynamic> incident) {
  final latitude = _doubleOrNull(incident['latitude']);

  final longitude = _doubleOrNull(incident['longitude']);

  if (latitude == null ||
      longitude == null ||
      latitude < -90 ||
      latitude > 90 ||
      longitude < -180 ||
      longitude > 180) {
    return null;
  }

  return LatLng(latitude, longitude);
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

double _double(Object? value, {required double fallback}) {
  if (value is num) {
    return value.toDouble();
  }

  return double.tryParse(value?.toString() ?? '') ?? fallback;
}

double? _doubleOrNull(Object? value) {
  if (value is num) {
    return value.toDouble();
  }

  return double.tryParse(value?.toString() ?? '');
}

String _text(Object? value, String fallback) {
  final text = value?.toString().trim() ?? '';

  return text.isEmpty ? fallback : text;
}

Color _hexColor(String value) {
  final cleaned = value.replaceAll('#', '').trim();

  final six = cleaned.length == 6 ? cleaned : 'eab308';

  final parsed = int.tryParse(six, radix: 16) ?? 0xEAB308;

  return Color(0xFF000000 | parsed);
}
