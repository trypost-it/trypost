/** @type {import('@maizzle/framework').Config} */
export default {
  build: {
    content: ['templates/**/*.html'],
    static: {
      source: ['images/**/*.*'],
      destination: '../../../public/images/emails',
    },
    output: {
      path: '../resources/views/mail',
      extension: 'blade.php',
      from: 'templates',
    },
  },
  posthtml: {
    expressions: {
      unescapeDelimiters: ['{!!', '!!}'],
    },
  },
  css: {
    inline: true,
    purge: true,
    shorthand: true,
  },
  prettify: true,
};