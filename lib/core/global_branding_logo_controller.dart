import 'dart:async';

import 'package:flutter/foundation.dart';

import '../services/public_branding_logo_service.dart';

class GlobalBrandingLogoController extends ChangeNotifier {
  GlobalBrandingLogoController._();

  static final GlobalBrandingLogoController instance =
      GlobalBrandingLogoController._();

  static const Duration _refreshInterval = Duration(seconds: 15);

  final PublicBrandingLogoService _service = PublicBrandingLogoService();

  Timer? _timer;
  Uint8List? _logoBytes;
  bool _loaded = false;
  bool _loading = false;

  Uint8List? get logoBytes => _logoBytes;
  bool get hasCustomLogo => _logoBytes != null && _logoBytes!.isNotEmpty;
  bool get loaded => _loaded;

  Future<void> ensureStarted() async {
    _timer ??= Timer.periodic(_refreshInterval, (_) {
      unawaited(refresh());
    });

    if (!_loaded && !_loading) {
      await refresh();
    }
  }

  Future<void> refresh() async {
    if (_loading) {
      return;
    }

    _loading = true;

    try {
      final nextLogo = await _service.fetchLogoBytes();
      _loaded = true;

      if (!_sameBytes(_logoBytes, nextLogo)) {
        _logoBytes = nextLogo;
        notifyListeners();
      }
    } catch (_) {
      // Keep the last known logo if the public branding asset is temporarily
      // unavailable. Branding failures must never block login or SOS access.
    } finally {
      _loading = false;
    }
  }

  void applyLogoBytes(Uint8List? bytes) {
    final normalized = bytes == null || bytes.isEmpty
        ? null
        : Uint8List.fromList(bytes);

    _loaded = true;

    if (_sameBytes(_logoBytes, normalized)) {
      return;
    }

    _logoBytes = normalized;
    notifyListeners();
  }

  bool _sameBytes(Uint8List? left, Uint8List? right) {
    if (identical(left, right)) {
      return true;
    }

    if (left == null || right == null || left.length != right.length) {
      return false;
    }

    for (var index = 0; index < left.length; index++) {
      if (left[index] != right[index]) {
        return false;
      }
    }

    return true;
  }
}
