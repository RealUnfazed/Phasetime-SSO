// Shared Tailwind (Play CDN) config. Include AFTER the cdn.tailwindcss.com script.
// This is the one place the "vault" design system's tokens live.
tailwind.config = {
  theme: {
    extend: {
      colors: {
        ink: {
          DEFAULT: '#0D1321',
          panel: '#171D2E',
          border: '#2A3348',
          soft: '#232B40',
        },
        brass: {
          DEFAULT: '#C89B3C',
          bright: '#DDB35C',
          dim: '#8A6B2A',
        },
        teal: {
          DEFAULT: '#3E8E82',
          bright: '#54ABA0',
        },
        rust: {
          DEFAULT: '#C1594A',
          bright: '#D97160',
        },
        paper: '#EDE9E0',
        mute: '#9AA3B8',
      },
      fontFamily: {
        display: ['"Space Grotesk"', 'sans-serif'],
        body: ['Inter', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'monospace'],
      },
    },
  },
};
