import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../services/auth_service.dart';
import '../services/mobile_sos_service.dart';

class GlobalSosOverlay extends StatefulWidget {
  const GlobalSosOverlay({super.key, required this.child});

  final Widget child;

  static _GlobalSosOverlayState? _hostState;

  static Future<void> open(BuildContext context) async {
    final host = _hostState;

    if (host != null && host.mounted) {
      await host._beginSosFlow(context);
      return;
    }

    final ancestor =
        context.findAncestorStateOfType<_GlobalSosOverlayState>();

    if (ancestor != null && ancestor.mounted) {
      await ancestor._beginSosFlow(context);
      return;
    }

    if (context.mounted) {
      ScaffoldMessenger.maybeOf(context)
        ?..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            content: Text(
              'Emergency SOS is still initializing. Please try again.',
            ),
          ),
        );
    }
  }

  @override
  State<GlobalSosOverlay> createState() => _GlobalSosOverlayState();
}

class _GlobalSosOverlayState extends State<GlobalSosOverlay> {
  bool _flowOpen = false;

  @override
  void initState() {
    super.initState();
    GlobalSosOverlay._hostState = this;
  }

  @override
  void dispose() {
    if (identical(GlobalSosOverlay._hostState, this)) {
      GlobalSosOverlay._hostState = null;
    }

    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return widget.child;
  }

  Future<void> _beginSosFlow(BuildContext launchContext) async {
    if (_flowOpen) {
      return;
    }

    setState(() {
      _flowOpen = true;
    });

    HapticFeedback.mediumImpact();

    try {
      if (!launchContext.mounted) {
        return;
      }

      final confirmed = await showDialog<bool>(
        context: launchContext,
        barrierDismissible: true,
        builder: (dialogContext) {
          return AlertDialog(
            icon: const Icon(
              Icons.warning_amber_rounded,
              color: Color(0xFFDC2626),
              size: 36,
            ),
            title: const Text('Confirm Emergency SOS'),
            content: const Text(
              'This will prepare a distress signal for TabangNow Admin and Officials. '
              'Continue only if you need emergency assistance. Accidental or false alerts can delay responders.',
            ),
            actions: <Widget>[
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(false),
                child: const Text('Cancel'),
              ),
              FilledButton(
                style: FilledButton.styleFrom(
                  backgroundColor: const Color(0xFFDC2626),
                  foregroundColor: Colors.white,
                ),
                onPressed: () => Navigator.of(dialogContext).pop(true),
                child: const Text('Yes, I need help'),
              ),
            ],
          );
        },
      );

      if (confirmed != true || !mounted || !launchContext.mounted) {
        return;
      }

      final result = await showModalBottomSheet<MobileSosSendResult>(
        context: launchContext,
        isScrollControlled: true,
        useSafeArea: true,
        backgroundColor: Theme.of(launchContext).colorScheme.surface,
        builder: (_) => _MobileSosForm(
          authService: AuthService(),
        ),
      );

      if (result == null || !mounted || !launchContext.mounted) {
        return;
      }

      await showDialog<void>(
        context: launchContext,
        barrierDismissible: false,
        builder: (dialogContext) {
          return AlertDialog(
            icon: const Icon(
              Icons.check_circle_rounded,
              color: Color(0xFF059669),
              size: 40,
            ),
            title: const Text('Distress Signal Sent'),
            content: Text(
              '${result.alertCode} was sent successfully. Keep your phone available in case responders need to contact you.',
            ),
            actions: <Widget>[
              FilledButton(
                onPressed: () => Navigator.of(dialogContext).pop(),
                child: const Text('OK'),
              ),
            ],
          );
        },
      );
    } finally {
      if (mounted) {
        setState(() {
          _flowOpen = false;
        });
      }
    }
  }
}

class _MobileSosForm extends StatefulWidget {
  const _MobileSosForm({required this.authService});

  final AuthService authService;

  @override
  State<_MobileSosForm> createState() => _MobileSosFormState();
}

class _MobileSosFormState extends State<_MobileSosForm> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _detailsController = TextEditingController();
  final TextEditingController _mobileController = TextEditingController();

  late final MobileSosService _service;

  MobileSosLocation? _location;
  String? _locationError;
  bool _locating = true;
  bool _sending = false;

  @override
  void initState() {
    super.initState();
    _service = MobileSosService(authService: widget.authService);
    _initialize();
  }

  @override
  void dispose() {
    _detailsController.dispose();
    _mobileController.dispose();
    super.dispose();
  }

  Future<void> _initialize() async {
    final prefill = await _service.prefillContactNumber();

    if (mounted && prefill != null && _mobileController.text.trim().isEmpty) {
      _mobileController.text = prefill;
    }

    await _acquireLocation();
  }

  Future<void> _acquireLocation() async {
    if (!mounted) {
      return;
    }

    setState(() {
      _locating = true;
      _locationError = null;
    });

    try {
      final location = await _service.acquireLocation();

      if (!mounted) {
        return;
      }

      setState(() {
        _location = location;
        _locating = false;
        _locationError = null;
      });
    } on MobileSosException catch (exception) {
      if (!mounted) {
        return;
      }

      setState(() {
        _location = null;
        _locating = false;
        _locationError = exception.message;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        _location = null;
        _locating = false;
        _locationError = 'Unable to determine your location. Retry before sending.';
      });
    }
  }

  Future<void> _send() async {
    if (_sending) {
      return;
    }

    final valid = _formKey.currentState?.validate() ?? false;

    if (!valid) {
      return;
    }

    final location = _location;

    if (location == null) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            content: Text(
              'A current or last-known location is required before sending.',
            ),
          ),
        );
      return;
    }

    setState(() {
      _sending = true;
    });

    try {
      final result = await _service.send(
        emergencyDetails: _detailsController.text,
        contactNumber: _mobileController.text,
        location: location,
      );

      if (!mounted) {
        return;
      }

      Navigator.of(context).pop(result);
    } on MobileSosException catch (exception) {
      if (!mounted) {
        return;
      }

      setState(() {
        _sending = false;
      });

      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(exception.message)));
    } catch (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        _sending = false;
      });

      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            content: Text('Unable to send the distress signal. Please retry.'),
          ),
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final media = MediaQuery.of(context);

    return Padding(
      padding: EdgeInsets.only(bottom: media.viewInsets.bottom),
      child: DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.92,
        minChildSize: 0.72,
        maxChildSize: 0.98,
        builder: (context, scrollController) {
          return Form(
            key: _formKey,
            child: ListView(
              controller: scrollController,
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
              children: <Widget>[
                Center(
                  child: Container(
                    width: 42,
                    height: 5,
                    decoration: BoxDecoration(
                      color: theme.dividerColor,
                      borderRadius: BorderRadius.circular(99),
                    ),
                  ),
                ),
                const SizedBox(height: 20),
                const Row(
                  children: <Widget>[
                    Icon(
                      Icons.sos_rounded,
                      color: Color(0xFFDC2626),
                      size: 30,
                    ),
                    SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Emergency Distress Signal',
                        style: TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  'Describe the emergency, confirm a callback number, and wait while TabangNow obtains your current GPS or last-known device location.',
                  style: TextStyle(
                    color: theme.colorScheme.onSurfaceVariant,
                    height: 1.45,
                  ),
                ),
                const SizedBox(height: 22),
                TextFormField(
                  controller: _detailsController,
                  minLines: 4,
                  maxLines: 7,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    labelText: 'What is the emergency?',
                    hintText: 'Describe what happened and what help is needed...',
                    alignLabelWithHint: true,
                  ),
                  validator: (value) {
                    final text = value?.trim() ?? '';

                    if (text.isEmpty) {
                      return 'Describe the emergency.';
                    }

                    if (text.length < 3) {
                      return 'Enter at least 3 characters.';
                    }

                    if (text.length > 1000) {
                      return 'Emergency details must not exceed 1000 characters.';
                    }

                    return null;
                  },
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _mobileController,
                  keyboardType: TextInputType.phone,
                  autofillHints: const <String>[AutofillHints.telephoneNumber],
                  decoration: const InputDecoration(
                    labelText: 'Mobile Number',
                    hintText: '09XXXXXXXXX',
                    prefixIcon: Icon(Icons.phone_rounded),
                  ),
                  validator: (value) {
                    var number = (value ?? '').trim().replaceAll(
                      RegExp(r'[\s\-\(\)]'),
                      '',
                    );

                    if (number.startsWith('+63')) {
                      number = '0${number.substring(3)}';
                    }

                    if (!RegExp(r'^09\d{9}$').hasMatch(number)) {
                      return 'Enter a valid Philippine mobile number.';
                    }

                    return null;
                  },
                ),
                const SizedBox(height: 18),
                _LocationStatusCard(
                  location: _location,
                  locating: _locating,
                  error: _locationError,
                  onRetry: _locating ? null : _acquireLocation,
                  onLocationSettings: _service.openLocationSettings,
                  onAppSettings: _service.openAppSettings,
                ),
                const SizedBox(height: 22),
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFF7ED),
                    border: Border.all(color: const Color(0xFFFED7AA)),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Icon(
                        Icons.info_outline_rounded,
                        color: Color(0xFFC2410C),
                      ),
                      SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          'You already confirmed the SOS before opening this form. Press Send only when the emergency details and callback number are correct.',
                          style: TextStyle(height: 1.4),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 18),
                FilledButton.icon(
                  style: FilledButton.styleFrom(
                    backgroundColor: const Color(0xFFDC2626),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                  ),
                  onPressed: _sending || _locating ? null : _send,
                  icon: _sending
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.send_rounded),
                  label: Text(
                    _sending ? 'Sending Distress Signal...' : 'Send Distress Signal',
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
                const SizedBox(height: 10),
                TextButton(
                  onPressed: _sending ? null : () => Navigator.of(context).pop(),
                  child: const Text('Cancel'),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _LocationStatusCard extends StatelessWidget {
  const _LocationStatusCard({
    required this.location,
    required this.locating,
    required this.error,
    required this.onRetry,
    required this.onLocationSettings,
    required this.onAppSettings,
  });

  final MobileSosLocation? location;
  final bool locating;
  final String? error;
  final Future<void> Function()? onRetry;
  final Future<void> Function() onLocationSettings;
  final Future<void> Function() onAppSettings;

  @override
  Widget build(BuildContext context) {
    final locationValue = location;

    if (locating) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: const Color(0xFFEFF6FF),
          border: Border.all(color: const Color(0xFFBFDBFE)),
          borderRadius: BorderRadius.circular(14),
        ),
        child: const Row(
          children: <Widget>[
            SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(strokeWidth: 2.5),
            ),
            SizedBox(width: 12),
            Expanded(
              child: Text(
                'Getting your current GPS location...',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ],
        ),
      );
    }

    if (locationValue != null) {
      final accuracy = locationValue.accuracyMeters;
      final accuracyText = accuracy == null
          ? 'Accuracy not reported'
          : '±${accuracy.toStringAsFixed(1)} m';

      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: const Color(0xFFECFDF5),
          border: Border.all(color: const Color(0xFFA7F3D0)),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                const Icon(
                  Icons.location_on_rounded,
                  color: Color(0xFF047857),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    locationValue.isLastKnown
                        ? 'Last-known location ready'
                        : 'Current GPS location ready',
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              '${locationValue.latitude.toStringAsFixed(6)}, ${locationValue.longitude.toStringAsFixed(6)} • $accuracyText',
              style: const TextStyle(fontSize: 12, height: 1.4),
            ),
            const SizedBox(height: 10),
            TextButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Refresh location'),
            ),
          ],
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFFEF2F2),
        border: Border.all(color: const Color(0xFFFECACA)),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Row(
            children: <Widget>[
              Icon(Icons.location_off_rounded, color: Color(0xFFB91C1C)),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Location required',
                  style: TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            error ?? 'Unable to determine location.',
            style: const TextStyle(height: 1.4),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: <Widget>[
              FilledButton.tonalIcon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Retry'),
              ),
              TextButton(
                onPressed: onLocationSettings,
                child: const Text('Location settings'),
              ),
              TextButton(
                onPressed: onAppSettings,
                child: const Text('App permissions'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
