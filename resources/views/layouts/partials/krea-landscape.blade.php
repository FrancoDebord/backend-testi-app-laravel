{{-- Paysage de la charte ARISE & SHINE Krea (vagues et soleil), décoratif.
     Couleurs : Sun #FCC11D et ses teintes claires, Orange #F7942F, Blue #184797 / #103675.
     Paramètres : $class (hauteur et placement), $viewBox (cadrage ; « 0 50 400 150 » pour un bandeau). Le soleil garde sa forme (slice, jamais none). --}}
<svg class="{{ $class ?? 'block h-40 w-full' }}" viewBox="{{ $viewBox ?? '0 0 400 200' }}" preserveAspectRatio="xMidYMax slice" aria-hidden="true" focusable="false">
    <path d="M0 118 C80 72 170 36 270 34 C335 33 378 44 400 52 V200 H0Z" fill="#FFF8D9"/>
    <path d="M110 150 C170 100 250 70 330 72 C365 73 388 80 400 86 V200 H110Z" fill="#FDE7A0"/>
    <path d="M0 96 C34 86 66 94 96 116 C114 130 124 142 132 156 V200 H0Z" fill="#FCC11D"/>
    <path d="M196 160 C236 130 288 122 330 138 C358 149 382 152 400 148 V200 H196Z" fill="#F7942F"/>
    <path d="M0 124 C60 116 118 132 168 152 C220 172 282 186 334 182 C362 180 386 174 400 170 V200 H0Z" fill="#184797"/>
    <path d="M0 176 C70 168 150 186 240 192 C300 196 360 190 400 186 V200 H0Z" fill="#103675"/>
    <g stroke="#FCC11D" stroke-width="3" stroke-linecap="round">
        <circle cx="318" cy="86" r="13" fill="#FCC11D" stroke="none"/>
        <path d="M318 62 V67 M318 105 V110 M294 86 H299 M337 86 H342 M301 69 L304.5 72.5 M331.5 99.5 L335 103 M301 103 L304.5 99.5 M331.5 72.5 L335 69"/>
    </g>
</svg>
