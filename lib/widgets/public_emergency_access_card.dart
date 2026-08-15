import 'package:flutter/material.dart';

import 'global_sos_overlay.dart';

class PublicEmergencyAccessCard extends StatelessWidget {
  const PublicEmergencyAccessCard({super.key, this.compact = false});

  final bool compact;

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;

    final background = dark ? const Color(0xFF3F0D12) : const Color(0xFFFEF2F2);

    final border = dark ? const Color(0xFF7F1D1D) : const Color(0xFFFECACA);

    final titleColor = dark ? const Color(0xFFFECACA) : const Color(0xFF991B1B);

    final bodyColor = dark ? const Color(0xFFFCA5A5) : const Color(0xFF7F1D1D);

    return Semantics(
      container: true,
      label:
          'Emergency SOS. No account or login is required to request emergency assistance.',
      child: Container(
        width: double.infinity,
        padding: EdgeInsets.all(compact ? 14 : 18),
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: border),
        ),
        child: Column(
          children: <Widget>[
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Container(
                  width: compact ? 40 : 46,
                  height: compact ? 40 : 46,
                  decoration: const BoxDecoration(
                    color: Color(0xFFDC2626),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.sos_rounded,
                    color: Colors.white,
                    size: 25,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        'Emergency SOS',
                        style: TextStyle(
                          color: titleColor,
                          fontSize: compact ? 16 : 18,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        'Available to everyone — no account or login required.',
                        style: TextStyle(
                          color: bodyColor,
                          fontSize: compact ? 12.5 : 13.5,
                          height: 1.35,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: <Widget>[
                Expanded(
                  child: _EmergencyFact(
                    icon: Icons.person_off_outlined,
                    label: 'No sign-in',
                    color: titleColor,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _EmergencyFact(
                    icon: Icons.location_on_outlined,
                    label: 'GPS',
                    color: titleColor,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _EmergencyFact(
                    icon: Icons.phone_outlined,
                    label: 'Callback',
                    color: titleColor,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              height: compact ? 46 : 50,
              child: FilledButton.icon(
                style: FilledButton.styleFrom(
                  backgroundColor: const Color(0xFFDC2626),
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(13),
                  ),
                ),
                onPressed: () => GlobalSosOverlay.open(context),
                icon: const Icon(Icons.warning_amber_rounded),
                label: const Text(
                  'Open Emergency SOS',
                  style: TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EmergencyFact extends StatelessWidget {
  const _EmergencyFact({
    required this.icon,
    required this.label,
    required this.color,
  });

  final IconData icon;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: <Widget>[
        Icon(icon, size: 17, color: color),
        const SizedBox(height: 3),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: color,
            fontSize: 10.5,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}
