
const mix = require('laravel-mix')
const tailwindcss = require('tailwindcss')

const domain = 'ice-dice.test';
const homedir = require('os').homedir();

mix.options({
  terser: {
    extractComments: false
  }
});


mix.js(['./src/global.js'], './web/dist/js/global.js')
  .postCss('./src/styles.css', './web/dist/css/styles.css', [
    tailwindcss('./tailwind.config.js')
  ]);