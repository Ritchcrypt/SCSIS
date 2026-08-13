import 'dart:io';
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../core/tabangnow_theme.dart';

import '../services/auth_service.dart';
import '../services/branding_service.dart';

class SystemBrandingScreen extends StatefulWidget {
  const SystemBrandingScreen({super.key, required this.authService});

  final AuthService authService;

  @override
  State<SystemBrandingScreen> createState() => _SystemBrandingScreenState();
}

class _SystemBrandingScreenState extends State<SystemBrandingScreen> {
  late final BrandingService _service;
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _subtitleController = TextEditingController();

  bool _loading = true;
  bool _saving = false;
  bool _removeLogo = false;
  String? _error;
  Uint8List? _currentLogoBytes;
  PlatformFile? _selectedLogo;
  Uint8List? _selectedLogoBytes;

  @override
  void initState() {
    super.initState();
    _service = BrandingService(authService: widget.authService);
    _load();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _subtitleController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final response = await _service.branding();
      final rawData = response['data'];
      final data = rawData is Map
          ? Map<String, dynamic>.from(rawData)
          : <String, dynamic>{};

      Uint8List? logoBytes;
      if (data['has_logo'] == true) {
        try {
          logoBytes = await _service.logoBytes();
        } catch (_) {
          logoBytes = null;
        }
      }

      if (!mounted) {
        return;
      }

      _nameController.text = data['system_name']?.toString() ?? 'TabangNow';
      _subtitleController.text =
          data['system_subtitle']?.toString() ?? 'Dao, Capiz';

      setState(() {
        _currentLogoBytes = logoBytes;
        _loading = false;
        _error = null;
      });
    } on AuthException catch (exception) {
      if (!mounted) {
        return;
      }

      setState(() {
        _loading = false;
        _error = exception.message;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        _loading = false;
        _error = 'Unable to load system branding.';
      });
    }
  }

  Future<void> _pickLogo() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const <String>['jpg', 'jpeg', 'png', 'webp'],
      allowMultiple: false,
      withData: true,
    );

    if (result == null || result.files.isEmpty) {
      return;
    }

    final file = result.files.single;

    if (file.size > 5 * 1024 * 1024) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Logo must not exceed 5 MB.')),
      );
      return;
    }

    Uint8List? previewBytes = file.bytes;

    if ((previewBytes == null || previewBytes.isEmpty) &&
        file.path != null &&
        file.path!.trim().isNotEmpty) {
      try {
        previewBytes = await File(file.path!).readAsBytes();
      } catch (_) {
        previewBytes = null;
      }
    }

    if (previewBytes == null || previewBytes.isEmpty) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('The selected logo could not be read for preview.'),
        ),
      );
      return;
    }

    if (!mounted) {
      return;
    }

    setState(() {
      _selectedLogo = file;
      _selectedLogoBytes = previewBytes;
      _removeLogo = false;
    });
  }

  Future<void> _save() async {
    if (_saving) {
      return;
    }

    final name = _nameController.text.trim();
    final subtitle = _subtitleController.text.trim();

    if (name.isEmpty || subtitle.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('System name and subtitle are required.')),
      );
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      await _service.updateBranding(
        systemName: name,
        systemSubtitle: subtitle,
        logo: _selectedLogo,
        removeLogo: _removeLogo,
      );

      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('System branding updated successfully.')),
      );

      Navigator.of(context).pop(true);
    } on AuthException catch (exception) {
      if (!mounted) {
        return;
      }

      setState(() {
        _saving = false;
        _error = exception.message;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        _saving = false;
        _error = 'Unable to update system branding.';
      });
    }
  }

  Uint8List? get _previewBytes {
    if (_removeLogo) {
      return null;
    }

    return _selectedLogoBytes ?? _currentLogoBytes;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: TabangNowTheme.of(context).pageBackground,
      appBar: AppBar(
        title: const Text('System Branding'),
        backgroundColor: TabangNowTheme.of(context).surface,
        foregroundColor: TabangNowTheme.of(context).textMain,
        elevation: 0,
        surfaceTintColor: TabangNowTheme.of(context).surface,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _nameController.text.isEmpty
          ? _buildLoadError()
          : _buildForm(),
    );
  }

  Widget _buildLoadError() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Icon(
              Icons.error_outline_rounded,
              size: 46,
              color: Color(0xFFDC2626),
            ),
            const SizedBox(height: 12),
            Text(
              _error ?? 'Unable to load system branding.',
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 16),
            FilledButton(onPressed: _load, child: const Text('Retry')),
          ],
        ),
      ),
    );
  }

  Widget _buildForm() {
    return ListView(
      padding: const EdgeInsets.fromLTRB(18, 20, 18, 32),
      children: <Widget>[
        Text(
          'System Branding',
          style: TextStyle(
            color: TabangNowTheme.of(context).textMain,
            fontSize: 26,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 5),
        Text(
          'Update the logo, system name, and subtitle shown in the upper-left navigation brand area.',
          style: TextStyle(
            color: TabangNowTheme.of(context).textMuted,
            fontSize: 14,
            height: 1.45,
          ),
        ),
        const SizedBox(height: 20),
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: const Color(0xFF172554),
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: const Color(0xFF1E3A8A)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              const Text(
                'Current sidebar preview',
                style: TextStyle(
                  color: Color(0xFFBFDBFE),
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 14),
              Row(
                children: <Widget>[
                  ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: Container(
                      width: 48,
                      height: 48,
                      color: const Color(0xFF2563EB),
                      child: _previewBytes != null
                          ? Image.memory(
                              _previewBytes!,
                              fit: BoxFit.cover,
                              filterQuality: FilterQuality.high,
                              gaplessPlayback: true,
                              errorBuilder: (context, error, stackTrace) {
                                return const Icon(
                                  Icons.shield_rounded,
                                  color: Colors.white,
                                );
                              },
                            )
                          : const Icon(
                              Icons.shield_rounded,
                              color: Colors.white,
                            ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ValueListenableBuilder<TextEditingValue>(
                      valueListenable: _nameController,
                      builder: (context, nameValue, child) {
                        return ValueListenableBuilder<TextEditingValue>(
                          valueListenable: _subtitleController,
                          builder: (context, subtitleValue, child) {
                            final name = nameValue.text.trim().isEmpty
                                ? 'TabangNow'
                                : nameValue.text.trim();
                            final subtitle = subtitleValue.text.trim().isEmpty
                                ? 'Dao, Capiz'
                                : subtitleValue.text.trim();

                            return Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Text(
                                  name,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 18,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  subtitle,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: Color(0xFFBFDBFE),
                                    fontSize: 14,
                                  ),
                                ),
                              ],
                            );
                          },
                        );
                      },
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        _field(controller: _nameController, label: 'System Name'),
        const SizedBox(height: 16),
        _field(controller: _subtitleController, label: 'System Subtitle'),
        const SizedBox(height: 18),
        Text(
          'System Logo',
          style: TextStyle(
            color: TabangNowTheme.of(context).textSoft,
            fontSize: 14,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: _saving ? null : _pickLogo,
          icon: const Icon(Icons.image_outlined),
          label: Text(_selectedLogo?.name ?? 'Choose Logo'),
          style: OutlinedButton.styleFrom(
            minimumSize: const Size.fromHeight(50),
            alignment: Alignment.centerLeft,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          'JPG, JPEG, PNG, or WEBP. Maximum 5 MB.',
          style: TextStyle(
            color: TabangNowTheme.of(context).textMuted,
            fontSize: 12,
          ),
        ),
        if (_currentLogoBytes != null || _selectedLogo != null) ...<Widget>[
          const SizedBox(height: 8),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            value: _removeLogo,
            onChanged: _saving
                ? null
                : (value) {
                    setState(() {
                      _removeLogo = value ?? false;
                      if (_removeLogo) {
                        _selectedLogo = null;
                        _selectedLogoBytes = null;
                      }
                    });
                  },
            title: const Text(
              'Remove custom logo',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
            controlAffinity: ListTileControlAffinity.leading,
          ),
        ],
        if (_error != null) ...<Widget>[
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFFEF2F2),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFFECACA)),
            ),
            child: Text(
              _error!,
              style: const TextStyle(
                color: Color(0xFFB91C1C),
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
        const SizedBox(height: 22),
        Row(
          children: <Widget>[
            Expanded(
              child: OutlinedButton(
                onPressed: _saving
                    ? null
                    : () => Navigator.of(context).pop(false),
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size.fromHeight(50),
                ),
                child: const Text('Cancel'),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: FilledButton(
                onPressed: _saving ? null : _save,
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(50),
                  backgroundColor: const Color(0xFF2563EB),
                ),
                child: _saving
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Text('Save Changes'),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _field({
    required TextEditingController controller,
    required String label,
  }) {
    return TextField(
      controller: controller,
      textInputAction: TextInputAction.next,
      decoration: InputDecoration(
        labelText: label,
        filled: true,
        fillColor: TabangNowTheme.of(context).surface,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide(
            color: TabangNowTheme.of(context).borderStrong,
          ),
        ),
      ),
    );
  }
}
