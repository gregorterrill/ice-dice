/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './templates/**/*.twig',
    './src/*.css', 
    './src/*.js'
  ],
  corePlugins: {
    container: false,
  },
  plugins: [],
  theme: {
    // EXTENSIONS OF TAILWIND DEFAULTS
    extend: {
      maxWidth: {
        'reading' : '53.75rem',
        '500' : '31.25rem',
        '620' : '38.75rem',
        '1/2' : '50%',
        '1/3' : '33.333%',
        '2/3' : '66.667%'
      },
      minHeight: {
        '200' : '12.5rem',
        '320' : '20rem',
      },
      zIndex: {
        '60' : '60',
        '70' : '70',
        '80' : '80',
        '90' : '90',
      },
      borderWidth: {
        '3' : '3px',
        '5' : '5px',
        '6' : '6px',
        '10': '10px',
      },
      borderRadius: {
        'DEFAULT': '8px'
      }
    },
    // FULLY CUSTOM VALUES
    screens: {
      'xs': '480px',
      'sm': '640px',
      'md': '768px',
      'lg': '1024px',
      'xl': '1280px',
      '2xl': '1536px',
    },
    fontSize: {
      '12': ['0.75rem', '1.333'], // Small Text - Desktop
      '15': ['0.9375rem', '1.667'], // Body Text - Desktop
      '17': ['1.0625rem', '1.35'], // H6 - Desktop, H6 - Mobile
      '18': ['1.125rem', '1.333'], // H5 - Mobile
      '19': ['1.1875rem', '1.315'], // H5 - Desktop
      '20': ['1.25rem', '1.4'], // Lead Text - Desktop
      '22': ['1.375rem', '1.68'], // Homepage Hero
      '24': ['1.5rem', '1.1667'], // H4 - Desktop, H4 - Mobile
      '28': ['1.75rem', '1.143'], // H3 - Mobile
      '34': ['2.125rem', '1.176'], // H3 - Desktop
      '44': ['2.75rem', '1.045'], // H2 - Mobile
      '48': ['3rem', '1.0833'], // H2 - Desktop
      '60': ['3.75rem','1.1667'], // H1 - Mobile
      '64': ['4rem', '1.15625'], // H1 - Desktop
    },
    fontFamily: {
      heading: [ 'PT Serif', 'Arial Black', 'sans-serif'],
      body: [ 'Poppins', 'Arial', 'sans-serif'],
    },
    colors: {
      'transparent' : 'rgba(0,0,0,0)',
      'white' : '#FFFFFF',
      'light': '#EDE7E3',
      'dark': '#2E282A',
      'black' : '#000000',

      'brand-dark': '#694411',
      'brand': '#E0B62D',
      'brand-light': '#E1CB7C',
      'accent' : '#E02233',
    },
    boxShadow: {
      'DEFAULT' : '0 0 24px rgba(0,0,0,0.16)',
      'lg' : '0 0 24px rgba(0,0,0,0.7)',
      'none' : 'none',
    },
    spacing: {
      '0': '0',
      '1': '0.0625rem',
      '2': '0.125rem',
      '3': '0.1875rem',
      '4': '0.25rem',
      '5': '0.3125rem',
      '6': '0.375rem',
      '8': '0.5rem',
      '10': '0.625rem',
      '12': '0.75rem',
      '14': '0.875rem',
      '16': '1rem',
      '18': '1.125rem',
      '20': '1.25rem',
      '24': '1.5rem',
      '28': '1.75rem',
      '30': '1.875rem',
      '32': '2rem',
      '40': '2.5rem',
      '48': '3rem',
      '50': '3.125rem',
      '60': '3.75rem',
      '64': '4rem',
      '80': '5rem',
      '88': '5.5rem',
      '100': '6.25rem',
      '120': '7.5rem',
      '128': '8rem',
      '140': '8.75rem',
      '160': '10rem',
      '180': '11.25rem',
      '200': '12.5rem',
      '240': '15rem',
      '280': '17.5rem',
      '340': '21.25rem',
      '360': '22.5rem',
      '480': '30rem',
      '600': '37.5rem',
      '1/2': '50%',
      '1/3': '33.333%',
      '2/3': '66.667%',
      '1/4': '25%',
      '3/4': '75%',
      '1/5': '20%',
      '2/5': '40%',
      '3/5': '60%',
      '4/5': '80%',
      'full': '100%',
      'screen': '100vw',
    },
    inset: (theme, { negative }) => ({
      auto: 'auto',
      ...theme('spacing'),
      ...negative(theme('spacing')),
    }),
    width: (theme) => ({
      auto: 'auto',
      ...theme('spacing'),
    }),
    opacity: {
      '0': '0',
      '5': '0.05',
      '10': '0.1',
      '15': '0.15',
      '20': '0.2',
      '30': '0.3',
      '40': '0.4',
      '50': '0.5',
      '60': '0.6',
      '70': '0.7',
      '80': '0.8',
      '85': '0.85',
      '90': '0.9',
      '95': '0.95',
      '100': '1',
    },
  },
}