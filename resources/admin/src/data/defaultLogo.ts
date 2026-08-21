// JW Studio Default Neon Brand Logo (SVG Data URI)
export const DEFAULT_JW_STUDIO_LOGO = `data:image/svg+xml;utf8,${encodeURIComponent(`
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="512" height="512">
  <defs>
    <linearGradient id="jwNeonGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#00f0ff" />
      <stop offset="35%" stop-color="#38bdf8" />
      <stop offset="70%" stop-color="#818cf8" />
      <stop offset="100%" stop-color="#c084fc" />
    </linearGradient>
    <filter id="neonGlow" x="-30%" y="-30%" width="160%" height="160%">
      <feGaussianBlur stdDeviation="8" result="blur" />
      <feMerge>
        <feMergeNode in="blur" />
        <feMergeNode in="blur" />
        <feMergeNode in="SourceGraphic" />
      </feMerge>
    </filter>
  </defs>
  <!-- Sleek Dark Futuristic Base -->
  <rect width="512" height="512" rx="96" fill="#040711" />
  
  <!-- Outer Neon Geometric Contour with Open Gaps -->
  <path d="M 96 230 L 96 96 L 416 96 L 416 390" 
        fill="none" 
        stroke="url(#jwNeonGrad)" 
        stroke-width="18" 
        stroke-linecap="round" 
        stroke-linejoin="round"
        filter="url(#neonGlow)" />
        
  <path d="M 260 416 L 416 416" 
        fill="none" 
        stroke="url(#jwNeonGrad)" 
        stroke-width="18" 
        stroke-linecap="round" 
        filter="url(#neonGlow)" />
        
  <!-- Center Neon Glowing 'J' Glyph -->
  <path d="M 276 185 L 276 305 C 276 348 248 375 208 375 C 172 375 148 352 142 328" 
        fill="none" 
        stroke="url(#jwNeonGrad)" 
        stroke-width="28" 
        stroke-linecap="round" 
        stroke-linejoin="round"
        filter="url(#neonGlow)" />
</svg>
`)}`;
