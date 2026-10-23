
import { createApp } from 'vue'
import { createVuetify } from 'vuetify'
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'
import App from './App.vue'

const vuetify = createVuetify({
  theme: {
    defaultTheme: 'campusLight',

    themes: {
      campusLight: {
        dark: false,

        colors: {
          primary: '#164E80',
          secondary: '#0B1F3A',
          background: '#F4F7FB',
          surface: '#FFFFFF',
          info: '#DCE8F5',
          'on-background': '#263445',
          'on-surface': '#263445',
        },
      },
    },
  },
})

createApp(App)
  .use(vuetify)
  .mount('#app')
