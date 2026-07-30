<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Secure upload policies
    |--------------------------------------------------------------------------
    |
    | Sensitive evidence and profile files are stored on the private "local"
    | disk. Existing public files remain readable through authorised controller
    | routes for backward compatibility, but new uploads are not exposed through
    | the public /storage symbolic link.
    |
    */

    'policies' => [

        'incident_evidence' => [
            'disk' => 'local',
            'directory' => 'incidents/evidence',
            'max_kilobytes' => 10240,
            'max_files' => 5,
            'allowed_extensions' => [
                'jpg',
                'jpeg',
                'png',
                'webp',
                'pdf',
            ],
            'allowed_mime_types' => [
                'image/jpeg',
                'image/png',
                'image/webp',
                'application/pdf',
            ],
            'max_width' => 8000,
            'max_height' => 8000,
            'max_pixels' => 40000000,
        ],

        'complaint_evidence' => [
            'disk' => 'local',
            'directory' => 'resident-complaints',
            'max_kilobytes' => 10240,
            'allowed_extensions' => [
                'jpg',
                'jpeg',
                'png',
                'webp',
            ],
            'allowed_mime_types' => [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            'max_width' => 8000,
            'max_height' => 8000,
            'max_pixels' => 40000000,
        ],

        'complaint_proof' => [
            'disk' => 'local',
            'directory' => 'resident-complaints/proofs',
            'max_kilobytes' => 10240,
            'allowed_extensions' => [
                'jpg',
                'jpeg',
                'png',
                'webp',
            ],
            'allowed_mime_types' => [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            'max_width' => 8000,
            'max_height' => 8000,
            'max_pixels' => 40000000,
        ],

        'profile_photo' => [
            'disk' => 'local',
            'directory' => 'profile-photos',
            'max_kilobytes' => 5120,
            'allowed_extensions' => [
                'jpg',
                'jpeg',
                'png',
                'webp',
            ],
            'allowed_mime_types' => [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            'max_width' => 4096,
            'max_height' => 4096,
            'max_pixels' => 16000000,
        ],

        /*
        |--------------------------------------------------------------------------
        | Public system branding
        |--------------------------------------------------------------------------
        |
        | The logo must remain available before login. It is validated with the
        | same signature and image-decoding checks, then the established
        | controller re-encodes it whenever GD is available.
        |
        */

        'branding_logo' => [
            'disk' => 'public',
            'directory' => 'system-branding',
            'max_kilobytes' => 5120,
            'allowed_extensions' => [
                'jpg',
                'jpeg',
                'png',
                'webp',
            ],
            'allowed_mime_types' => [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            'max_width' => 4096,
            'max_height' => 4096,
            'max_pixels' => 16000000,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy disk fallback
    |--------------------------------------------------------------------------
    |
    | Files uploaded before Phase 8 may already exist on the public disk.
    | Authorised file routes check the private disk first, then this fallback.
    |
    */

    'private_disk' => 'local',

    /*
    |--------------------------------------------------------------------------
    | Sensitive legacy directories
    |--------------------------------------------------------------------------
    |
    | These directories may contain uploads created before private storage was
    | introduced. The migration command can move them to the private disk
    | without changing the database paths already stored by the application.
    |
    */

    'sensitive_prefixes' => [
        'incidents/evidence',
        'resident-complaints',
        'profile-photos',
    ],

    'legacy_read_disks' => [
        'public',
    ],

];
