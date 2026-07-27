@props([
    'systemName' => 'TabangNow',
])

<div class="tn-auth-scene">
    <div class="tn-auth-scene-copy">
        <span class="tn-auth-scene-eyebrow">
            Barangay response network
        </span>

        <h2>
            Report faster.<br>
            Respond smarter.<br>
            Keep the community safer.
        </h2>

        <p>
            {{ $systemName }} connects residents, barangay officials, and
            response personnel through one coordinated community platform.
        </p>
    </div>

    <div class="tn-auth-scene-canvas">
        <svg viewBox="0 0 760 520"
     class="tn-auth-map"
     role="img"
     aria-label="Barangay emergency-response illustration"
     aria-describedby="tn-auth-map-description">

    <desc id="tn-auth-map-description">
        A response unit travels from the barangay hall toward a
        reported incident while community facilities remain connected.
    </desc>

            <defs>
                <linearGradient id="tnGroundGradient"
                                x1="0"
                                y1="0"
                                x2="1"
                                y2="1">
                    <stop offset="0%" stop-color="#dbeafe"/>
                    <stop offset="100%" stop-color="#bfdbfe"/>
                </linearGradient>

                <linearGradient id="tnRoadGradient"
                                x1="0"
                                y1="0"
                                x2="1"
                                y2="0">
                    <stop offset="0%" stop-color="#64748b"/>
                    <stop offset="100%" stop-color="#475569"/>
                </linearGradient>

                <linearGradient id="tnHallGradient"
                                x1="0"
                                y1="0"
                                x2="0"
                                y2="1">
                    <stop offset="0%" stop-color="#3b82f6"/>
                    <stop offset="100%" stop-color="#1d4ed8"/>
                </linearGradient>

                <filter id="tnSoftShadow"
                        x="-30%"
                        y="-30%"
                        width="160%"
                        height="160%">
                    <feDropShadow dx="0"
                                  dy="10"
                                  stdDeviation="12"
                                  flood-color="#0f172a"
                                  flood-opacity=".18"/>
                </filter>

                <pattern id="tnMapGrid"
                         width="32"
                         height="32"
                         patternUnits="userSpaceOnUse">
                    <path d="M32 0H0V32"
                          fill="none"
                          stroke="#ffffff"
                          stroke-opacity=".16"
                          stroke-width="1"/>
                </pattern>

                <path id="tnDispatchRoute"
                      d="M164 350
                         C250 300 270 222 355 220
                         S480 312 565 260
                         S635 182 682 165"/>
            </defs>

            <rect x="0"
                  y="0"
                  width="760"
                  height="520"
                  rx="36"
                  fill="url(#tnGroundGradient)"/>

            <rect x="0"
                  y="0"
                  width="760"
                  height="520"
                  rx="36"
                  fill="url(#tnMapGrid)"/>

            <g class="tn-auth-cloud tn-auth-cloud--one">
                <ellipse cx="112" cy="78" rx="42" ry="19" fill="#ffffff" opacity=".72"/>
                <circle cx="89" cy="69" r="19" fill="#ffffff" opacity=".72"/>
                <circle cx="120" cy="61" r="27" fill="#ffffff" opacity=".72"/>
                <circle cx="146" cy="72" r="18" fill="#ffffff" opacity=".72"/>
            </g>

            <g class="tn-auth-cloud tn-auth-cloud--two">
                <ellipse cx="590" cy="82" rx="39" ry="17" fill="#ffffff" opacity=".5"/>
                <circle cx="570" cy="74" r="17" fill="#ffffff" opacity=".5"/>
                <circle cx="598" cy="66" r="24" fill="#ffffff" opacity=".5"/>
                <circle cx="620" cy="75" r="16" fill="#ffffff" opacity=".5"/>
            </g>

            <path d="M-30 420
                     C110 330 185 395 284 315
                     C370 246 428 346 528 278
                     C624 212 687 214 800 123"
                  fill="none"
                  stroke="#334155"
                  stroke-width="70"
                  stroke-linecap="round"
                  opacity=".17"/>

            <path d="M-30 420
                     C110 330 185 395 284 315
                     C370 246 428 346 528 278
                     C624 212 687 214 800 123"
                  fill="none"
                  stroke="url(#tnRoadGradient)"
                  stroke-width="52"
                  stroke-linecap="round"/>

            <path d="M-30 420
                     C110 330 185 395 284 315
                     C370 246 428 346 528 278
                     C624 212 687 214 800 123"
                  fill="none"
                  stroke="#f8fafc"
                  stroke-width="3"
                  stroke-dasharray="16 18"
                  stroke-linecap="round"
                  opacity=".8"/>

            <path class="tn-auth-dispatch-path"
                  d="M164 350
                     C250 300 270 222 355 220
                     S480 312 565 260
                     S635 182 682 165"
                  fill="none"
                  stroke="#facc15"
                  stroke-width="6"
                  stroke-linecap="round"
                  stroke-dasharray="12 12"/>

            {{-- Barangay Hall --}}
            <g transform="translate(82 277)"
               filter="url(#tnSoftShadow)">
                <rect x="0"
                      y="38"
                      width="128"
                      height="96"
                      rx="12"
                      fill="url(#tnHallGradient)"/>

                <path d="M-8 43 64 0l72 43Z"
                      fill="#1e3a8a"/>

                <rect x="48"
                      y="79"
                      width="32"
                      height="55"
                      rx="4"
                      fill="#dbeafe"/>

                <rect x="15"
                      y="66"
                      width="22"
                      height="20"
                      rx="3"
                      fill="#bfdbfe"/>

                <rect x="91"
                      y="66"
                      width="22"
                      height="20"
                      rx="3"
                      fill="#bfdbfe"/>

                <rect x="19"
                      y="103"
                      width="90"
                      height="15"
                      rx="5"
                      fill="#1e40af"/>

                <text x="64"
                      y="114"
                      text-anchor="middle"
                      font-size="10"
                      font-weight="800"
                      fill="#ffffff">
                    BARANGAY HALL
                </text>
            </g>

            {{-- Health Centre --}}
            <g transform="translate(310 94)"
               filter="url(#tnSoftShadow)">
                <rect x="0"
                      y="30"
                      width="116"
                      height="82"
                      rx="12"
                      fill="#ffffff"/>

                <path d="M-6 34 58 0l64 34Z"
                      fill="#0f766e"/>

                <rect x="43"
                      y="61"
                      width="30"
                      height="51"
                      rx="4"
                      fill="#ccfbf1"/>

                <path d="M50 42h16v10h10v16H66v10H50V68H40V52h10Z"
                      fill="#ef4444"/>

                <text x="58"
                      y="100"
                      text-anchor="middle"
                      font-size="9"
                      font-weight="800"
                      fill="#0f766e">
                    HEALTH CENTRE
                </text>
            </g>

            {{-- Evacuation Centre --}}
            <g transform="translate(530 332)"
               filter="url(#tnSoftShadow)">
                <rect x="0"
                      y="34"
                      width="132"
                      height="88"
                      rx="12"
                      fill="#ffffff"/>

                <path d="M-7 38 66 0l73 38Z"
                      fill="#7c3aed"/>

                <rect x="50"
                      y="69"
                      width="32"
                      height="53"
                      rx="4"
                      fill="#ede9fe"/>

                <circle cx="25"
                        cy="73"
                        r="10"
                        fill="#c4b5fd"/>

                <circle cx="106"
                        cy="73"
                        r="10"
                        fill="#c4b5fd"/>

                <text x="66"
                      y="107"
                      text-anchor="middle"
                      font-size="9"
                      font-weight="800"
                      fill="#6d28d9">
                    EVACUATION
                </text>
            </g>

            {{-- Homes --}}
            <g class="tn-auth-home" transform="translate(230 365)">
                <rect x="0" y="26" width="74" height="57" rx="8" fill="#fff7ed"/>
                <path d="M-4 30 37 2l41 28Z" fill="#f97316"/>
                <rect x="29" y="54" width="17" height="29" rx="3" fill="#fed7aa"/>
                <rect x="9" y="44" width="14" height="14" rx="2" fill="#ffedd5"/>
                <rect x="51" y="44" width="14" height="14" rx="2" fill="#ffedd5"/>
            </g>

            <g class="tn-auth-home" transform="translate(444 123)">
                <rect x="0" y="26" width="74" height="57" rx="8" fill="#f0fdf4"/>
                <path d="M-4 30 37 2l41 28Z" fill="#16a34a"/>
                <rect x="29" y="54" width="17" height="29" rx="3" fill="#bbf7d0"/>
                <rect x="9" y="44" width="14" height="14" rx="2" fill="#dcfce7"/>
                <rect x="51" y="44" width="14" height="14" rx="2" fill="#dcfce7"/>
            </g>

            {{-- Incident marker --}}
            <g transform="translate(681 164)">
                <circle class="tn-auth-marker-ring"
                        cx="0"
                        cy="0"
                        r="31"
                        fill="none"
                        stroke="#ef4444"
                        stroke-width="4"/>

                <circle class="tn-auth-marker-ring tn-auth-marker-ring--delay"
                        cx="0"
                        cy="0"
                        r="31"
                        fill="none"
                        stroke="#ef4444"
                        stroke-width="4"/>

                <circle cx="0"
                        cy="0"
                        r="19"
                        fill="#ef4444"/>

                <path d="M0-10v13"
                      stroke="#ffffff"
                      stroke-width="4"
                      stroke-linecap="round"/>

                <circle cx="0"
                        cy="9"
                        r="2.5"
                        fill="#ffffff"/>
            </g>

            {{-- Moving response vehicle --}}
            <g class="tn-auth-moving-unit">
                <animateMotion dur="11s"
                               repeatCount="indefinite"
                               rotate="auto">
                    <mpath href="#tnDispatchRoute"/>
                </animateMotion>

                <g transform="translate(-22 -17)"
                   filter="url(#tnSoftShadow)">
                    <rect x="0"
                          y="7"
                          width="43"
                          height="25"
                          rx="8"
                          fill="#1d4ed8"/>

                    <path d="M10 7 17 0h15l8 7Z"
                          fill="#bfdbfe"/>

                    <rect x="17"
                          y="11"
                          width="10"
                          height="8"
                          rx="2"
                          fill="#f8fafc"/>

                    <rect x="19"
                          y="2"
                          width="5"
                          height="5"
                          rx="1.5"
                          fill="#ef4444"/>

                    <rect x="25"
                          y="2"
                          width="5"
                          height="5"
                          rx="1.5"
                          fill="#38bdf8"/>

                    <circle cx="10"
                            cy="32"
                            r="5"
                            fill="#0f172a"/>

                    <circle cx="34"
                            cy="32"
                            r="5"
                            fill="#0f172a"/>
                </g>
            </g>
        </svg>

        <div class="tn-auth-status-card tn-auth-status-card--incident">
            <span class="tn-auth-status-icon tn-auth-status-icon--red">
                !
            </span>

            <span>
                <strong>Incident received</strong>
                <small>Report validated</small>
            </span>
        </div>

        <div class="tn-auth-status-card tn-auth-status-card--dispatch">
            <span class="tn-auth-status-icon tn-auth-status-icon--blue">
                ✓
            </span>

            <span>
                <strong>Response dispatched</strong>
                <small>Unit en route</small>
            </span>
        </div>

        <div class="tn-auth-status-card tn-auth-status-card--secure">
            <span class="tn-auth-status-icon tn-auth-status-icon--green">
                ✓
            </span>

            <span>
                <strong>Secure coordination</strong>
                <small>Community connected</small>
            </span>
        </div>
    </div>
</div>