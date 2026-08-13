enum AppRole { admin, official, tanod, resident, unknown }

extension AppRoleX on AppRole {
  static AppRole fromRaw(Object? raw) {
    final value = raw?.toString().trim().toLowerCase();

    switch (value) {
      case 'admin':
        return AppRole.admin;
      case 'official':
      case 'dao':
        return AppRole.official;
      case 'tanod':
        return AppRole.tanod;
      case 'resident':
        return AppRole.resident;
      default:
        return AppRole.unknown;
    }
  }

  String get label {
    switch (this) {
      case AppRole.admin:
        return 'Admin';
      case AppRole.official:
        return 'Official';
      case AppRole.tanod:
        return 'Tanod';
      case AppRole.resident:
        return 'Resident';
      case AppRole.unknown:
        return 'Unknown';
    }
  }
}
