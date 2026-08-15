import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';

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
  Timer? _timer;

  bool _showSos = false;
  bool _targetSos = true;

  @override
  void initState() {
    super.initState();

    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 620),
    )..addStatusListener((status) {
        if (status != AnimationStatus.completed || !mounted) {
          return;
        }

        setState(() {
          _showSos = _targetSos;
          _controller.value = 0;
        });
      });

    _timer = Timer.periodic(widget.flipInterval, (_) => _flip());
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  void _flip() {
    if (!mounted || _controller.isAnimating) {
      return;
    }

    _targetSos = !_showSos;
    _controller.forward(from: 0);
  }

  Future<void> _openSos() async {
    if (!_showSos || _controller.isAnimating) {
      return;
    }

    await GlobalSosOverlay.open(context);
  }

  @override
  Widget build(BuildContext context) {
    final size = widget.size;

    return AnimatedBuilder(
      animation: _controller,
      builder: (context, _) {
        final progress = Curves.easeInOut.transform(_controller.value);
        final angle = progress * math.pi;
        final secondHalf = progress >= 0.5;
        final visibleSos = secondHalf ? _targetSos : _showSos;

        final face = _CoinFace(
          size: size,
          sos: visibleSos,
          enabled: visibleSos && !_controller.isAnimating,
          onTap: _openSos,
        );

        return Transform(
          alignment: Alignment.center,
          transform: Matrix4.identity()
            ..setEntry(3, 2, 0.0015)
            ..rotateY(angle),
          child: secondHalf
              ? Transform(
                  alignment: Alignment.center,
                  transform: Matrix4.identity()..rotateY(math.pi),
                  child: face,
                )
              : face,
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
    required this.onTap,
  });

  final double size;
  final bool sos;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final compact = size < 60;
    final background = sos
        ? const Color(0xFFDC2626)
        : const Color(0xFF254C99);

    return Semantics(
      button: sos,
      enabled: enabled,
      label: sos ? 'Emergency SOS' : 'TabangNow safety shield',
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
