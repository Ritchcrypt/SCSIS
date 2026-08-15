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

class _SosFlipCoinButtonState extends State<SosFlipCoinButton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _rotation;

  final GlobalBrandingLogoController _branding =
      GlobalBrandingLogoController.instance;

  @override
  void initState() {
    super.initState();

    _branding.addListener(_onBrandingChanged);
    unawaited(_branding.ensureStarted());

    final requestedCycleMilliseconds = widget.flipInterval.inMilliseconds * 2;
    final cycleMilliseconds = requestedCycleMilliseconds < 1600
        ? 1600
        : requestedCycleMilliseconds;

    _controller = AnimationController(
      vsync: this,
      duration: Duration(milliseconds: cycleMilliseconds),
    );

    _rotation = TweenSequence<double>(<TweenSequenceItem<double>>[
      TweenSequenceItem<double>(
        tween: ConstantTween<double>(0),
        weight: 35,
      ),
      TweenSequenceItem<double>(
        tween: Tween<double>(begin: 0, end: math.pi).chain(
          CurveTween(curve: Curves.easeInOutCubic),
        ),
        weight: 15,
      ),
      TweenSequenceItem<double>(
        tween: ConstantTween<double>(math.pi),
        weight: 35,
      ),
      TweenSequenceItem<double>(
        tween: Tween<double>(begin: math.pi, end: math.pi * 2).chain(
          CurveTween(curve: Curves.easeInOutCubic),
        ),
        weight: 15,
      ),
    ]).animate(_controller);

    _controller.repeat();
  }

  @override
  void dispose() {
    _branding.removeListener(_onBrandingChanged);
    _controller.dispose();
    super.dispose();
  }

  void _onBrandingChanged() {
    if (mounted) {
      setState(() {});
    }
  }

  bool _sosFaceVisible(double angle) {
    final normalized = angle % (math.pi * 2);
    return normalized > math.pi / 2 && normalized < math.pi * 1.5;
  }

  Future<void> _openSos() async {
    if (!_sosFaceVisible(_rotation.value)) {
      return;
    }

    await GlobalSosOverlay.open(context);
  }

  @override
  Widget build(BuildContext context) {
    final size = widget.size;

    return AnimatedBuilder(
      animation: _rotation,
      builder: (context, _) {
        final angle = _rotation.value;
        final visibleSos = _sosFaceVisible(angle);

        final face = _CoinFace(
          size: size,
          sos: visibleSos,
          enabled: visibleSos,
          logoBytes: _branding.logoBytes,
          onTap: _openSos,
        );

        final frontFacing = math.cos(angle) >= 0;

        return Transform(
          alignment: Alignment.center,
          transform: Matrix4.identity()
            ..setEntry(3, 2, 0.0015)
            ..rotateY(angle),
          child: frontFacing
              ? face
              : Transform(
                  alignment: Alignment.center,
                  transform: Matrix4.identity()..rotateY(math.pi),
                  child: face,
                ),
        );
      },
    );
  }
}

class _CoinFace extends StatelessWidget {
  const _CoinFace({
    required this.size,
    required this.sos,
    required this.enabled,
    required this.logoBytes,
    required this.onTap,
  });

  final double size;
  final bool sos;
  final bool enabled;
  final Uint8List? logoBytes;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final compact = size < 60;
    final background = sos
        ? const Color(0xFFDC2626)
        : const Color(0xFF254C99);
    final customLogo = logoBytes;

    return Semantics(
      button: sos,
      enabled: enabled,
      label: sos ? 'Emergency SOS' : 'TabangNow system logo',
      hint: sos
          ? 'Tap to open the emergency confirmation'
          : 'The SOS face will rotate into view automatically',
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          customBorder: const CircleBorder(),
          onTap: enabled ? onTap : null,
          child: Ink(
            width: size,
            height: size,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: background,
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
