import 'dart:io';
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../core/tabangnow_theme.dart';
import '../services/user_management_service.dart';

class UserManagementFormScreen extends StatefulWidget {
  const UserManagementFormScreen({
    super.key,
    required this.service,
    this.initialUser,
    this.options = const <String, dynamic>{},
  });

  final UserManagementService service;
  final Map<String, dynamic>? initialUser;
  final Map<String, dynamic> options;

  bool get isEdit => initialUser != null;

  @override
  State<UserManagementFormScreen> createState() =>
      _UserManagementFormScreenState();
}

class _UserManagementFormScreenState extends State<UserManagementFormScreen> {
  final _formKey = GlobalKey<FormState>();

  late final TextEditingController _nameController;
  late final TextEditingController _emailController;
  late final TextEditingController _contactController;
  late final TextEditingController _addressController;

  final _passwordController = TextEditingController();

  String _role = 'resident';
  int? _barangayId;
  PlatformFile? _profilePhoto;
  bool _saving = false;
  bool _showPassword = false;

  List<Map<String, dynamic>> get _roleOptions =>
      _mapList(widget.options['roles']);

  List<Map<String, dynamic>> get _barangayOptions =>
      _mapList(widget.options['barangays']);

  @override
  void initState() {
    super.initState();

    final user = widget.initialUser ?? <String, dynamic>{};

    _nameController = TextEditingController(text: _text(user['name'], ''));

    _emailController = TextEditingController(text: _text(user['email'], ''));

    _contactController = TextEditingController(
      text: _text(user['contact_number'], ''),
    );

    _addressController = TextEditingController(
      text: _text(user['address'], ''),
    );

    _role = _text(user['role'], 'resident');

    _barangayId = _nullableInt(user['barangay_id']);
  }

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _contactController.dispose();
    _addressController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _pickProfilePhoto() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowMultiple: false,
      allowedExtensions: const <String>['jpg', 'jpeg', 'png', 'webp'],
    );

    if (result == null || result.files.isEmpty || !mounted) {
      return;
    }

    final file = result.files.single;

    if (file.path == null) {
      _show('The selected profile picture could not be accessed.');
      return;
    }

    const maxBytes = 5 * 1024 * 1024;

    if (file.size > maxBytes) {
      _show('The profile picture must not exceed 5 MB.');
      return;
    }

    setState(() {
      _profilePhoto = file;
    });
  }

  Future<void> _save() async {
    if (_saving || !_formKey.currentState!.validate()) {
      return;
    }

    if (!widget.isEdit) {
      final passwordError = _passwordValidation(_passwordController.text);

      if (passwordError != null) {
        _show(passwordError);
        return;
      }
    }

    setState(() {
      _saving = true;
    });

    try {
      final response = widget.isEdit
          ? await widget.service.update(
              userId: _int(widget.initialUser!['id']),
              name: _nameController.text,
              email: _emailController.text,
              contactNumber: _nullable(_contactController.text),
              barangayId: _barangayId,
              address: _nullable(_addressController.text),
              role: _role,
              profilePhotoPath: _profilePhoto?.path,
            )
          : await widget.service.create(
              name: _nameController.text,
              email: _emailController.text,
              contactNumber: _nullable(_contactController.text),
              barangayId: _barangayId,
              address: _nullable(_addressController.text),
              role: _role,
              password: _passwordController.text,
              profilePhotoPath: _profilePhoto?.path,
            );

      if (!mounted) {
        return;
      }

      Navigator.of(context).pop(<String, dynamic>{
        'message':
            response['message']?.toString() ??
            (widget.isEdit
                ? 'User account updated successfully.'
                : 'User account created successfully.'),
        'data': response['data'],
      });
    } catch (exception) {
      if (!mounted) {
        return;
      }

      setState(() {
        _saving = false;
      });

      _show(exception.toString().replaceFirst('AuthException: ', ''));
    }
  }

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    final user = widget.initialUser ?? <String, dynamic>{};

    final active = user['is_active'] != false;

    return Scaffold(
      backgroundColor: palette.pageBackground,
      appBar: AppBar(title: Text(widget.isEdit ? 'Edit User' : 'Add User')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 18, 16, 32),
          children: <Widget>[
            _Surface(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    widget.isEdit ? 'Edit User' : 'Add User',
                    style: TextStyle(
                      color: palette.textMain,
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    widget.isEdit
                        ? 'Update account information and access level.'
                        : 'Create an account for admin, official, tanod, or resident users.',
                    style: TextStyle(color: palette.textMuted),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            _Surface(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    'Profile Picture',
                    style: TextStyle(
                      color: palette.textMain,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: <Widget>[
                      _FormAvatar(
                        service: widget.service,
                        user: user,
                        selectedPhoto: _profilePhoto,
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            OutlinedButton.icon(
                              onPressed: _saving ? null : _pickProfilePhoto,
                              icon: const Icon(Icons.photo_camera_outlined),
                              label: Text(
                                _profilePhoto == null
                                    ? 'Choose Picture'
                                    : 'Change Picture',
                              ),
                            ),
                            const SizedBox(height: 7),
                            Text(
                              'JPG, PNG, or WEBP. Maximum size: 5 MB.${widget.isEdit && user['has_profile_photo'] == true ? ' A new image replaces the current profile picture.' : ''}',
                              style: TextStyle(
                                color: palette.textMuted,
                                fontSize: 11,
                                height: 1.4,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  if (_profilePhoto != null) ...<Widget>[
                    const SizedBox(height: 8),
                    Text(
                      _profilePhoto!.name,
                      style: TextStyle(
                        color: palette.textSoft,
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 14),
            _Surface(
              child: Column(
                children: <Widget>[
                  TextFormField(
                    controller: _nameController,
                    maxLength: 255,
                    textCapitalization: TextCapitalization.words,
                    decoration: const InputDecoration(labelText: 'Full Name *'),
                    validator: (value) => _required(value, 'Full Name'),
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: _emailController,
                    maxLength: 255,
                    keyboardType: TextInputType.emailAddress,
                    autocorrect: false,
                    decoration: const InputDecoration(labelText: 'Email *'),
                    validator: (value) {
                      final text = value?.trim() ?? '';

                      if (text.isEmpty) {
                        return 'Email is required.';
                      }

                      if (!RegExp(
                        r'^[^@\s]+@[^@\s]+\.[^@\s]+$',
                      ).hasMatch(text)) {
                        return 'Enter a valid email address.';
                      }

                      return null;
                    },
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: _contactController,
                    maxLength: 30,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      labelText: 'Contact Number',
                      hintText: 'Example: 09123456789',
                    ),
                    validator: (value) {
                      final text = value?.trim() ?? '';

                      if (text.isEmpty) {
                        return null;
                      }

                      if (!RegExp(r'^[0-9+()\-\s]*$').hasMatch(text)) {
                        return 'Use only valid phone-number characters.';
                      }

                      return null;
                    },
                  ),
                  const SizedBox(height: 10),
                  TextFormField(
                    controller: _addressController,
                    maxLength: 1000,
                    minLines: 2,
                    maxLines: 4,
                    decoration: const InputDecoration(
                      labelText: 'Address',
                      hintText: 'Enter complete address',
                      alignLabelWithHint: true,
                    ),
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    initialValue: _barangayId?.toString() ?? '',
                    decoration: const InputDecoration(labelText: 'Barangay'),
                    items: <DropdownMenuItem<String>>[
                      const DropdownMenuItem<String>(
                        value: '',
                        child: Text('Not specified'),
                      ),
                      ..._barangayOptions.map(
                        (option) => DropdownMenuItem<String>(
                          value: _int(option['id']).toString(),
                          child: Text(_text(option['label'], 'Barangay')),
                        ),
                      ),
                    ],
                    onChanged: _saving
                        ? null
                        : (value) {
                            setState(() {
                              _barangayId = value == null || value.isEmpty
                                  ? null
                                  : int.tryParse(value);
                            });
                          },
                  ),
                  const SizedBox(height: 14),
                  DropdownButtonFormField<String>(
                    initialValue: _role,
                    decoration: const InputDecoration(labelText: 'Role *'),
                    items:
                        (_roleOptions.isEmpty
                                ? const <Map<String, dynamic>>[
                                    <String, dynamic>{
                                      'value': 'admin',
                                      'label': 'Admin',
                                    },
                                    <String, dynamic>{
                                      'value': 'official',
                                      'label': 'Official',
                                    },
                                    <String, dynamic>{
                                      'value': 'tanod',
                                      'label': 'Tanod',
                                    },
                                    <String, dynamic>{
                                      'value': 'resident',
                                      'label': 'Resident',
                                    },
                                  ]
                                : _roleOptions)
                            .map(
                              (option) => DropdownMenuItem<String>(
                                value: _text(option['value'], 'resident'),
                                child: Text(_text(option['label'], 'Resident')),
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
                              _role = value;
                            });
                          },
                  ),
                  const SizedBox(height: 14),
                  _ReadOnlyStatusCard(active: widget.isEdit ? active : true),
                  if (_role == 'tanod') ...<Widget>[
                    const SizedBox(height: 12),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(13),
                      decoration: BoxDecoration(
                        color: palette.accentSoft,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        'Tanod account synchronization: a linked Employee record and Tanod Roster placeholder are created/updated automatically. Duty, shift, purok, and other operational details remain managed in Tanod Roster.',
                        style: TextStyle(
                          color: palette.accentText,
                          fontSize: 11,
                          height: 1.45,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                  if (!widget.isEdit) ...<Widget>[
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _passwordController,
                      obscureText: !_showPassword,
                      autocorrect: false,
                      enableSuggestions: false,
                      decoration: InputDecoration(
                        labelText: 'Initial Password *',
                        helperText:
                            'At least 12 characters with uppercase, lowercase, number, and symbol.',
                        suffixIcon: IconButton(
                          onPressed: () {
                            setState(() {
                              _showPassword = !_showPassword;
                            });
                          },
                          icon: Icon(
                            _showPassword
                                ? Icons.visibility_off_outlined
                                : Icons.visibility_outlined,
                          ),
                        ),
                      ),
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'Initial Password is required.';
                        }

                        return _passwordValidation(value);
                      },
                    ),
                  ],
                  const SizedBox(height: 14),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(13),
                    decoration: BoxDecoration(
                      color: palette.surfaceMuted,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      'Staff accounts such as admin, official, and tanod are created by admin only. Public sign-up remains resident-only.',
                      style: TextStyle(
                        color: palette.textSoft,
                        fontSize: 11,
                        height: 1.45,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),
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
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : Text(
                                  widget.isEdit
                                      ? 'Save Changes'
                                      : 'Create User',
                                ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _show(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class _FormAvatar extends StatelessWidget {
  const _FormAvatar({
    required this.service,
    required this.user,
    required this.selectedPhoto,
  });

  final UserManagementService service;
  final Map<String, dynamic> user;
  final PlatformFile? selectedPhoto;

  @override
  Widget build(BuildContext context) {
    final name = _text(user['name'], 'User');

    if (selectedPhoto != null && selectedPhoto!.path != null) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(18),
        child: Image.file(
          File(selectedPhoto!.path!),
          width: 88,
          height: 88,
          fit: BoxFit.cover,
        ),
      );
    }

    if (user['has_profile_photo'] == true && _int(user['id']) > 0) {
      return FutureBuilder<Uint8List>(
        future: service.profilePhotoBytes(_int(user['id'])),
        builder: (context, snapshot) {
          if (snapshot.hasData) {
            return ClipRRect(
              borderRadius: BorderRadius.circular(18),
              child: Image.memory(
                snapshot.data!,
                width: 88,
                height: 88,
                fit: BoxFit.cover,
              ),
            );
          }

          return _InitialAvatar(name: name, size: 88);
        },
      );
    }

    return _InitialAvatar(name: name, size: 88);
  }
}

class _InitialAvatar extends StatelessWidget {
  const _InitialAvatar({required this.name, required this.size});

  final String name;
  final double size;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: palette.accentSoft,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: palette.border),
      ),
      child: Text(
        name.isEmpty ? 'U' : name.substring(0, 1).toUpperCase(),
        style: TextStyle(
          color: palette.accentText,
          fontSize: 30,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _ReadOnlyStatusCard extends StatelessWidget {
  const _ReadOnlyStatusCard({required this.active});

  final bool active;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    final background = active
        ? (palette.isDark ? const Color(0xFF064E3B) : const Color(0xFFECFDF5))
        : palette.surfaceMuted;

    final foreground = active
        ? (palette.isDark ? const Color(0xFFA7F3D0) : const Color(0xFF047857))
        : palette.textSoft;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: palette.border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(
            active ? Icons.verified_user_outlined : Icons.block_outlined,
            color: foreground,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'Status: ${active ? 'Active' : 'Inactive'}',
                  style: TextStyle(
                    color: foreground,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  active
                      ? 'Approved and allowed to access the system.'
                      : 'Not approved or currently blocked. Use the dedicated Activate Account action.',
                  style: TextStyle(
                    color: foreground,
                    fontSize: 11,
                    height: 1.4,
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

class _Surface extends StatelessWidget {
  const _Surface({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final palette = TabangNowTheme.of(context);

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: palette.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: palette.border),
      ),
      child: child,
    );
  }
}

String? _required(String? value, String label) {
  if (value == null || value.trim().isEmpty) {
    return '$label is required.';
  }

  return null;
}

String? _passwordValidation(String value) {
  if (value.length < 12) {
    return 'Password must be at least 12 characters.';
  }

  if (!RegExp(r'[A-Z]').hasMatch(value)) {
    return 'Password must include an uppercase letter.';
  }

  if (!RegExp(r'[a-z]').hasMatch(value)) {
    return 'Password must include a lowercase letter.';
  }

  if (!RegExp(r'[0-9]').hasMatch(value)) {
    return 'Password must include a number.';
  }

  if (!RegExp(r'[^A-Za-z0-9]').hasMatch(value)) {
    return 'Password must include a symbol.';
  }

  return null;
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

int? _nullableInt(Object? value) {
  if (value == null) {
    return null;
  }

  if (value is int) {
    return value;
  }

  final parsed = int.tryParse(value.toString());

  return parsed != null && parsed > 0 ? parsed : null;
}

String _text(Object? value, String fallback) {
  final text = value?.toString().trim() ?? '';

  return text.isEmpty ? fallback : text;
}

String? _nullable(String value) {
  final text = value.trim();

  return text.isEmpty ? null : text;
}
