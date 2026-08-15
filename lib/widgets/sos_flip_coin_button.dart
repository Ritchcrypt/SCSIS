import 'dart:async';
import 'dart:math' as math;
import 'dart:typed_data';

import 'package:flutter/material.dart';

import '../core/global_branding_logo_controller.dart';
import 'global_sos_overlay.dart';

class SosFlipCoinButton extends StatefulWidget {
  const SosFlipCoinButton({
    super.key,
    this.size = 96,
    this.flipInterval = const Duration(seconds: 2),
  });

  final double size;
  final Duration flipInterval;

  @override
  State<SosFlipCoinButton> createState() => _SosFlipCoinButtonState();
}

class _SosFlipCoinButtonState extends State<SosFlipCoinButton> {
  final GlobalBrandingLogoController _branding =
      GlobalBrandingLogoController.instance;

  Timer? _timer;
  bool _showSos = false;

  @override
  void initState() {
    super.initState();

    _branding.addListener(_onBrandingChanged);
    unawaited(_branding.ensureStarted());

    _startFlipTimer();
  }

  @override
  void didUpdateWidget(covariant SosFlipCoinButton oldWidget) {
    super.didUpdateWidget(oldWidget);

    if (oldWidget.flipInterval != widget.flipInterval) {
      _startFlipTimer();
    }
  }

  void _startFlipTimer() {
    _timer?.cancel();
    _timer = Timer.periodic(widget.flipInterval, (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        _showSos = !_showSos;
      });
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _branding.removeListener(_onBrandingChanged);
    super.dispose();
  }

  void _onBrandingChanged() {
    if (mounted) {
      setState(() {});
    }
  }

  Future<void> _openSos() async {
    await GlobalSosOverlay.open(context);
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox.square(
      dimension: widget.size,
      child: AnimatedSwitcher(
        duration: const Duration(milliseconds: 620),
        switchInCurve: Curves.easeInOutCubic,
        switchOutCurve: Curves.easeInOutCubic,
        transitionBuilder: (child, animation) {
          return AnimatedBuilder(
            animation: animation,
            child: child,
            builder: (context, animatedChild) {
              final angle = (1 - animation.value) * math.pi;

              return Transform(
                alignment: Alignment.center,
                transform: Matrix4.identity()
                  ..setEntry(3, 2, 0.0015)
                  ..rotateY(angle),
                child: animatedChild,
              );
            },
          );
        },
        child: _CoinFace(
          key: ValueKey<bool>(_showSos),
          size: widget.size,
          sos: _showSos,
          logoBytes: _branding.logoBytes,
          onTap: _openSos,
        ),
      ),
    );
  }
}

class _CoinFace extends StatelessWidget {
  const _CoinFace({
    super.key,
    required this.size,
    required this.sos,
    required this.logoBytes,
    required this.onTap,
  });

  final double size;
  final bool sos;
  final Uint8List? logoBytes;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final compact = size < 60;
    final customLogo = logoBytes;

    return Semantics(
      button: true,
      enabled: true,
      label: 'Emergency SOS',
      hint:
          'Tap to open the emergency confirmation. The coin flips automatically.',
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          customBorder: const CircleBorder(),
          onTap: onTap,
          child: Ink(
            width: size,
            height: size,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: sos ? const Color(0xFFDC2626) : const Color(0xFF254C99),
              border: Border.all(
                color: Colors.white.withValues(alpha: 0.20),
                width: compact ? 1 : 2,
              ),
              boxShadow: <BoxShadow>[
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.16),
                  blurRadius: compact ? 8 : 18,
                  offset: Offset(0, compact ? 3 : 8),
                ),
              ],
            ),
            child: Center(
              child: sos
                  ? Text(
                      'SOS',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: compact ? size * 0.28 : size * 0.25,
                        fontWeight: FontWeight.w900,
                        letterSpacing: compact ? 0 : 1.2,
                      ),
                    )
                  : customLogo != null && customLogo.isNotEmpty
                  ? Padding(
                      padding: EdgeInsets.all(compact ? 5 : 10),
                      child: ClipOval(
                        child: Image.memory(
                          customLogo,
                          width: size,
                          height: size,
                          fit: BoxFit.contain,
                          filterQuality: FilterQuality.high,
                          gaplessPlayback: true,
                          errorBuilder: (context, error, stackTrace) {
                            return Icon(
                              Icons.health_and_safety_rounded,
                              color: Colors.white,
                              size: compact ? size * 0.58 : size * 0.52,
                            );
                          },
                        ),
                      ),
                    )
                  : Icon(
                      Icons.health_and_safety_rounded,
                      color: Colors.white,
                      size: compact ? size * 0.58 : size * 0.52,
                    ),
            ),
          ),
        ),
      ),
    );
  }
}
