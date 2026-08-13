import 'package:flutter/material.dart';

import '../core/tabangnow_theme.dart';
import '../services/case_management_service.dart';

class CaseFormScreen extends StatefulWidget {
  const CaseFormScreen({
    super.key,
    required this.service,
    required this.caseTypes,
    required this.caseStatuses,
    required this.incidents,
    this.caseRecord,
  });

  final CaseManagementService service;
  final List<Map<String, dynamic>> caseTypes;
  final List<Map<String, dynamic>> caseStatuses;
  final List<Map<String, dynamic>> incidents;
  final Map<String, dynamic>? caseRecord;

  bool get editing => caseRecord != null;

  @override
  State<CaseFormScreen> createState() => _CaseFormScreenState();
}

class _CaseFormScreenState extends State<CaseFormScreen> {
  final _formKey = GlobalKey<FormState>();

  late final TextEditingController _caseNumber;
  late final TextEditingController _subject;
  late final TextEditingController _contact;
  late final TextEditingController _address;
  late final TextEditingController _incidentTitle;
  late final TextEditingController _handledBy;
  late final TextEditingController _resolution;
  late final TextEditingController _notes;

  String? _caseType;
  String _status = 'open';
  int? _incidentId;
  DateTime? _hearingDate;
  bool _saving = false;

  @override
  void initState() {
    super.initState();

    final record = widget.caseRecord ?? <String, dynamic>{};

    _caseNumber = TextEditingController(
      text: widget.editing ? _text(record['case_number']) : 'AUTO-GENERATED',
    );
    _subject = TextEditingController(text: _text(record['subject_name']));
    _contact = TextEditingController(text: _text(record['contact']));
    _address = TextEditingController(text: _text(record['address']));
    _incidentTitle = TextEditingController(
      text: _text(record['incident_title']),
    );
    _handledBy = TextEditingController(text: _text(record['handled_by']));
    _resolution = TextEditingController(text: _text(record['resolution']));
    _notes = TextEditingController(text: _text(record['notes']));

    final caseType = _text(record['case_type']);
    _caseType = caseType.isEmpty ? null : caseType;

    final status = _text(record['status']);
    _status = status.isEmpty ? 'open' : status;

    _incidentId = _intOrNull(record['incident_id']);

    final hearing = _text(record['hearing_date']);
    _hearingDate = hearing.isEmpty ? null : DateTime.tryParse(hearing);
  }

  @override
  void dispose() {
    _caseNumber.dispose();
    _subject.dispose();
    _contact.dispose();
    _address.dispose();
    _incidentTitle.dispose();
    _handledBy.dispose();
    _resolution.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _pickHearingDate() async {
    final now = DateTime.now();

    final selected = await showDatePicker(
      context: context,
      initialDate: _hearingDate ?? now,
      firstDate: DateTime(now.year - 10),
      lastDate: DateTime(now.year + 20),
    );

    if (selected == null || !mounted) {
      return;
    }

    setState(() {
      _hearingDate = selected;
    });
  }

  void _selectIncident(int? id) {
    setState(() {
      _incidentId = id;

      if (id == null) {
        _incidentTitle.text = '';
        return;
      }

      final match = widget.incidents.where(
        (incident) => _intOrNull(incident['id']) == id,
      );

      if (match.isNotEmpty) {
        _incidentTitle.text = _text(match.first['title']);
      }
    });
  }

  Future<void> _save() async {
    if (_saving || !_formKey.currentState!.validate()) {
      return;
    }

    if (_caseType == null || _caseType!.isEmpty) {
      _show('Case Type is required.');
      return;
    }

    final payload = <String, dynamic>{
      'case_number': widget.editing
          ? _caseNumber.text.trim()
          : 'AUTO-GENERATED',
      'case_type': _caseType,
      'subject_name': _subject.text.trim(),
      'contact': _nullable(_contact.text),
      'address': _nullable(_address.text),
      'incident_id': _incidentId,
      'incident_title': _nullable(_incidentTitle.text),
      'status': _status,
      'hearing_date': _hearingDate == null ? null : _dateOnly(_hearingDate!),
      'handled_by': _nullable(_handledBy.text),
      'resolution': _nullable(_resolution.text),
      'notes': _nullable(_notes.text),
    };

    setState(() {
      _saving = true;
    });

    try {
      final response = widget.editing
          ? await widget.service.update(
              _intOrNull(widget.caseRecord!['id']) ?? 0,
              payload,
            )
          : await widget.service.create(payload);

      if (!mounted) {
        return;
      }

      Navigator.of(
        context,
      ).pop(response['message']?.toString() ?? 'Case saved successfully.');
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

    return Scaffold(
      backgroundColor: palette.pageBackground,
      appBar: AppBar(
        title: Text(widget.editing ? 'Edit Case' : 'Create New Case'),
      ),
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
                    widget.editing ? 'Edit Case' : 'Create New Case',
                    style: TextStyle(
                      color: palette.textMain,
                      fontSize: 22,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Barangay blotter and case file information.',
                    style: TextStyle(color: palette.textMuted),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            _Surface(
              child: Column(
                children: <Widget>[
                  TextFormField(
                    controller: _caseNumber,
                    readOnly: !widget.editing,
                    decoration: InputDecoration(
                      labelText: 'Case Number',
                      helperText: widget.editing
                          ? null
                          : 'Leave as auto-generated.',
                    ),
                    validator: (value) {
                      if (widget.editing &&
                          (value == null || value.trim().isEmpty)) {
                        return 'Case Number is required.';
                      }

                      return null;
                    },
                  ),
                  const SizedBox(height: 14),
                  DropdownButtonFormField<String>(
                    initialValue: _caseType,
                    decoration: const InputDecoration(labelText: 'Case Type *'),
                    items: widget.caseTypes
                        .map(
                          (option) => DropdownMenuItem<String>(
                            value: _text(option['value']),
                            child: Text(_text(option['label'])),
                          ),
                        )
                        .toList(growable: false),
                    onChanged: _saving
                        ? null
                        : (value) {
                            setState(() {
                              _caseType = value;
                            });
                          },
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _subject,
                    decoration: const InputDecoration(
                      labelText: 'Subject Name *',
                      hintText: 'Name of person involved',
                    ),
                    validator: (value) {
                      if (value == null || value.trim().isEmpty) {
                        return 'Subject Name is required.';
                      }

                      return null;
                    },
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _contact,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      labelText: 'Contact',
                      hintText: '09XXXXXXXXX',
                    ),
                    validator: (value) {
                      final text = value?.trim() ?? '';

                      if (text.isEmpty) {
                        return null;
                      }

                      if (!RegExp(r'^[0-9+()\-\.\s]*$').hasMatch(text)) {
                        return 'Use only phone-number characters.';
                      }

                      return null;
                    },
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _address,
                    decoration: const InputDecoration(
                      labelText: 'Address',
                      hintText: 'Full address',
                    ),
                  ),
                  const SizedBox(height: 14),
                  DropdownButtonFormField<int?>(
                    initialValue: _incidentId,
                    decoration: const InputDecoration(
                      labelText: 'Related Incident',
                    ),
                    items: <DropdownMenuItem<int?>>[
                      const DropdownMenuItem<int?>(
                        value: null,
                        child: Text('No linked incident'),
                      ),
                      ...widget.incidents.map(
                        (incident) => DropdownMenuItem<int?>(
                          value: _intOrNull(incident['id']),
                          child: Text(
                            _text(incident['label']),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ),
                    ],
                    onChanged: _saving ? null : _selectIncident,
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _incidentTitle,
                    decoration: const InputDecoration(
                      labelText: 'Incident Title',
                      hintText: 'Related incident',
                    ),
                  ),
                  const SizedBox(height: 14),
                  DropdownButtonFormField<String>(
                    initialValue: _status,
                    decoration: const InputDecoration(labelText: 'Status'),
                    items: widget.caseStatuses
                        .map(
                          (option) => DropdownMenuItem<String>(
                            value: _text(option['value']),
                            child: Text(_text(option['label'])),
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
                              _status = value;
                            });
                          },
                  ),
                  const SizedBox(height: 14),
                  InkWell(
                    onTap: _saving ? null : _pickHearingDate,
                    borderRadius: BorderRadius.circular(12),
                    child: InputDecorator(
                      decoration: InputDecoration(
                        labelText: 'Hearing Date',
                        suffixIcon: _hearingDate == null
                            ? const Icon(Icons.calendar_month_rounded)
                            : IconButton(
                                tooltip: 'Clear hearing date',
                                onPressed: _saving
                                    ? null
                                    : () {
                                        setState(() {
                                          _hearingDate = null;
                                        });
                                      },
                                icon: const Icon(Icons.clear_rounded),
                              ),
                      ),
                      child: Text(
                        _hearingDate == null
                            ? 'No hearing date'
                            : _dateOnly(_hearingDate!),
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _handledBy,
                    decoration: const InputDecoration(
                      labelText: 'Handled By',
                      hintText:
                          'Brgy. Lupon Chair / Brgy. Captain / Assigned Officer',
                    ),
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _resolution,
                    minLines: 3,
                    maxLines: 7,
                    decoration: const InputDecoration(
                      labelText: 'Resolution',
                      hintText: 'Case resolution details...',
                      alignLabelWithHint: true,
                    ),
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    controller: _notes,
                    minLines: 3,
                    maxLines: 7,
                    decoration: const InputDecoration(
                      labelText: 'Notes',
                      hintText: 'Additional notes...',
                      alignLabelWithHint: true,
                    ),
                  ),
                  const SizedBox(height: 20),
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
                                  widget.editing ? 'Update Case' : 'Save Case',
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
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: palette.border),
      ),
      child: child,
    );
  }
}

String _text(Object? value) {
  return value?.toString().trim() ?? '';
}

int? _intOrNull(Object? value) {
  if (value == null) {
    return null;
  }

  if (value is int) {
    return value;
  }

  return int.tryParse(value.toString());
}

String? _nullable(String value) {
  final text = value.trim();

  return text.isEmpty ? null : text;
}

String _dateOnly(DateTime date) {
  return '${date.year.toString().padLeft(4, '0')}-'
      '${date.month.toString().padLeft(2, '0')}-'
      '${date.day.toString().padLeft(2, '0')}';
}
