$ErrorActionPreference = 'Stop'

$path = Join-Path (Get-Location) 'lib\screens\home_screen.dart'

if (-not (Test-Path $path)) {
    throw "home_screen.dart was not found at $path"
}

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$content = [System.IO.File]::ReadAllText($path)

if ($content.Contains('_HomeModule.distressSignal')) {
    Write-Host 'Distress Signal is already integrated into HomeScreen.'
    exit 0
}

$backup = "$path.before-distress-signal"
[System.IO.File]::WriteAllText($backup, $content, $utf8NoBom)

function Replace-ExactOnce {
    param(
        [string]$Name,
        [string]$Old,
        [string]$New
    )

    $count = ([regex]::Matches(
        $script:content,
        [regex]::Escape($Old)
    )).Count

    if ($count -ne 1) {
        throw "$Name expected exactly one match but found $count. No partial file was written."
    }

    $script:content = $script:content.Replace($Old, $New)
}

Replace-ExactOnce `
    -Name 'screen imports' `
    -Old @"
import '../services/resident_complaint_service.dart';
import 'incident_detail_screen.dart';
"@ `
    -New @"
import '../services/resident_complaint_service.dart';
import 'distress_signal_detail_screen.dart';
import 'distress_signal_screen.dart';
import 'incident_detail_screen.dart';
"@

Replace-ExactOnce `
    -Name 'home module enum' `
    -Old @"
  dashboard,
  incidents,
  tanodAlerts,
"@ `
    -New @"
  dashboard,
  incidents,
  distressSignal,
  tanodAlerts,
"@

Replace-ExactOnce `
    -Name 'notification navigation' `
    -Old @"
      case 'tanodAlerts':
"@ `
    -New @"
      case 'distressSignal':
      case 'emergencyAlerts':
        if (ModuleRegistry.canAccess(_appRole, AppModuleId.distressSignal)) {
          if (target.sourceId != null) {
            await Navigator.of(context).push<void>(
              MaterialPageRoute<void>(
                builder: (_) => DistressSignalDetailScreen(
                  authService: _authService,
                  alertId: target.sourceId!,
                ),
              ),
            );

            return;
          }

          _selectModule(_HomeModule.distressSignal);
          return;
        }

        break;

      case 'tanodAlerts':
"@

Replace-ExactOnce `
    -Name 'selected module screen' `
    -Old @"
      case _HomeModule.tanodAlerts:
"@ `
    -New @"
      case _HomeModule.distressSignal:
        return DistressSignalScreen(
          authService: _authService,
          user: widget.user,
        );
      case _HomeModule.tanodAlerts:
"@

Replace-ExactOnce `
    -Name 'module id to home module mapping' `
    -Old @"
    AppModuleId.incidents => _HomeModule.incidents,
"@ `
    -New @"
    AppModuleId.incidents => _HomeModule.incidents,
    AppModuleId.distressSignal => _HomeModule.distressSignal,
"@

Replace-ExactOnce `
    -Name 'home module to module id mapping' `
    -Old @"
    _HomeModule.incidents => AppModuleId.incidents,
"@ `
    -New @"
    _HomeModule.incidents => AppModuleId.incidents,
    _HomeModule.distressSignal => AppModuleId.distressSignal,
"@

[System.IO.File]::WriteAllText($path, $content, $utf8NoBom)

Write-Host 'Distress Signal HomeScreen integration applied successfully.'
Write-Host "Backup: $backup"
