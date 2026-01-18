/** @type {import('@maizzle/framework').Config} */
export default {
  build: {
    content: ['templates/**/*.html'],
    static: {
      source: ['images/**/*.*'],
      destination: 'images',
    },
    output: {
      path: 'build_local',
    },
  },
  posthtml: {
    expressions: {
      unescapeDelimiters: ['{!!', '!!}'],
    },
  },
};