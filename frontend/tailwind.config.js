// Design tokens — nguồn KHÓA: specs/1.mvp/04-ui.md §1 (muted v3 boss duyệt a670766).
// FE không tự chế; mọi màu/font/size trong component PHẢI qua token này.
export default {
  content: ['./index.html', './src/**/*.{vue,js}'],
  theme: {
    extend: {
      colors: {
        ink: '#1E1B18',
        paper: '#F7F2E7',
        paper2: '#EFE6D3',
        cinnabar: '#B33A2B',
        gold: '#A8802A',
        bamboo: '#3E5C48',
        muted: '#5C554A',
      },
      fontFamily: {
        han: ['"Noto Serif TC"', 'serif'],
        body: ['"Be Vietnam Pro"', 'system-ui'],
      },
      fontSize: {
        body: ['16px', '1.65'],
        h1: ['26px', '1.3'],
        h2: ['20px', '1.4'],
        small: ['13px', '1.5'],
      },
      borderRadius: { card: '14px', sm: '9px' },
      spacing: { gutter: '20px' },
      boxShadow: {
        card: '0 1px 3px rgb(30 27 24 / 0.12)',
        lift: '0 6px 18px rgb(30 27 24 / 0.16)',
      },
    },
  },
  plugins: [],
}
