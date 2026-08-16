import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_cropper/image_cropper.dart';

enum GlobalImageCropMode { free, square }

class GlobalImageCropException implements Exception {
  const GlobalImageCropException(this.message);

  final String message;

  @override
  String toString() => message;
}

class GlobalImageCropService {
  const GlobalImageCropService._();

  static const Set<String> supportedImageExtensions = <String>{
    'jpg',
    'jpeg',
    'png',
    'webp',
  };

  static bool isCroppableImage(PlatformFile file) {
    final extension = file.extension?.trim().toLowerCase() ?? '';
    return supportedImageExtensions.contains(extension);
  }

  static Future<PlatformFile?> crop({
    required PlatformFile file,
    required GlobalImageCropMode mode,
    String title = 'Adjust Photo',
  }) async {
    if (!isCroppableImage(file)) {
      return file;
    }

    final sourcePath = file.path?.trim() ?? '';
    if (sourcePath.isEmpty) {
      throw const GlobalImageCropException(
        'The selected photo could not be opened for cropping.',
      );
    }

    if (!Platform.isAndroid && !Platform.isIOS) {
      return file;
    }

    final originalExtension = file.extension?.trim().toLowerCase() ?? '';
    final preservePng = originalExtension == 'png';
    final compressFormat = preservePng
        ? ImageCompressFormat.png
        : ImageCompressFormat.jpg;

    final presets = mode == GlobalImageCropMode.square
        ? const <CropAspectRatioPresetData>[CropAspectRatioPreset.square]
        : const <CropAspectRatioPresetData>[
            CropAspectRatioPreset.original,
            CropAspectRatioPreset.square,
            CropAspectRatioPreset.ratio4x3,
            CropAspectRatioPreset.ratio3x2,
            CropAspectRatioPreset.ratio16x9,
          ];

    final cropped = await ImageCropper().cropImage(
      sourcePath: sourcePath,
      aspectRatio: mode == GlobalImageCropMode.square
          ? const CropAspectRatio(ratioX: 1, ratioY: 1)
          : null,
      compressFormat: compressFormat,
      compressQuality: 92,
      uiSettings: <PlatformUiSettings>[
        AndroidUiSettings(
          toolbarTitle: title,
          toolbarColor: const Color(0xFF1D4ED8),
          toolbarWidgetColor: Colors.white,
          activeControlsWidgetColor: const Color(0xFF2563EB),
          cropFrameColor: Colors.white,
          cropGridColor: Colors.white70,
          showCropGrid: true,
          lockAspectRatio: mode == GlobalImageCropMode.square,
          initAspectRatio: mode == GlobalImageCropMode.square
              ? CropAspectRatioPreset.square
              : CropAspectRatioPreset.original,
          aspectRatioPresets: presets,
        ),
        IOSUiSettings(
          title: title,
          doneButtonTitle: 'Use Photo',
          cancelButtonTitle: 'Cancel',
          aspectRatioLockEnabled: mode == GlobalImageCropMode.square,
          resetAspectRatioEnabled: mode != GlobalImageCropMode.square,
          aspectRatioPickerButtonHidden: mode == GlobalImageCropMode.square,
          aspectRatioPresets: presets,
        ),
      ],
    );

    if (cropped == null) {
      return null;
    }

    final output = File(cropped.path);
    if (!await output.exists()) {
      throw const GlobalImageCropException(
        'The cropped photo could not be read. Please choose the photo again.',
      );
    }

    final size = await output.length();
    final outputExtension = preservePng ? 'png' : 'jpg';
    final originalName = file.name.trim();
    final dot = originalName.lastIndexOf('.');
    final baseName = (dot > 0 ? originalName.substring(0, dot) : originalName)
        .trim();
    final safeBase = baseName.isEmpty ? 'photo' : baseName;

    return PlatformFile(
      path: cropped.path,
      name: '${safeBase}_cropped.$outputExtension',
      size: size,
    );
  }
}
